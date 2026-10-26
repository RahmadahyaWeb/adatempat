<?php

namespace App\Services\Subscriptions;

use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionLifecycleService
{
    public function grantManualTrial(User $admin, User $owner, SubscriptionPlan $plan, ?string $reason = null): Subscription
    {
        abort_unless($admin->isSuperAdmin() && $admin->status === 'active', 403);

        if (! $owner->isOwner() || $owner->status !== 'active' || ! $owner->hasVerifiedEmail() || ! $plan->is_active) {
            throw ValidationException::withMessages([
                'subscription' => 'Owner harus aktif dan terverifikasi, serta paket harus aktif.',
            ]);
        }

        $reason = trim((string) $reason);

        if ($reason === '' || mb_strlen($reason) < 12 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan pemberian trial wajib diisi, minimal 12 karakter.',
            ]);
        }

        return DB::transaction(function () use ($admin, $owner, $plan, $reason): Subscription {
            $lockedOwner = User::query()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail();

            $hasCurrentSubscription = Subscription::query()
                ->where('owner_id', $lockedOwner->getKey())
                ->where(function ($query): void {
                    $query->where(function ($active): void {
                        $active->whereIn('status', [
                            Subscription::STATUS_PENDING_PAYMENT,
                            Subscription::STATUS_TRIALING,
                            Subscription::STATUS_ACTIVE,
                            Subscription::STATUS_PENDING_REVERIFICATION,
                        ])->where(function ($period): void {
                            $period->whereNull('ends_at')
                                ->orWhere('ends_at', '>', now('UTC'));
                        });
                    })->orWhere(function ($grace): void {
                        $grace->where('status', Subscription::STATUS_GRACE_PERIOD)
                            ->where('grace_ends_at', '>=', now('UTC'));
                    });
                })
                ->lockForUpdate()
                ->exists();

            if ($hasCurrentSubscription) {
                throw ValidationException::withMessages([
                    'owner' => 'Owner sudah memiliki langganan berjalan atau pembayaran langganan yang belum diselesaikan.',
                ]);
            }

            $startsAt = now('UTC');
            $endsAt = $startsAt->copy()->addDays((int) $plan->duration_days);
            $subscription = Subscription::query()->create([
                'owner_id' => $lockedOwner->getKey(),
                'plan_id' => $plan->getKey(),
                'scheduled_plan_id' => null,
                'retained_business_id' => null,
                'is_trial' => true,
                'status' => Subscription::STATUS_TRIALING,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'grace_ends_at' => $endsAt->copy()->addHours(24),
                'suspended_at' => null,
                'reverified_by' => null,
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $admin->getKey(),
                'actor_role' => $admin->role,
                'action' => 'subscription.manual_trial_granted',
                'auditable_type' => Subscription::class,
                'auditable_id' => $subscription->getKey(),
                'reason' => $reason,
                'before_state_json' => null,
                'after_state_json' => [
                    'owner_id' => $lockedOwner->getKey(),
                    'plan_id' => $plan->getKey(),
                    'status' => $subscription->status,
                    'is_trial' => true,
                    'starts_at' => $subscription->starts_at?->toISOString(),
                    'ends_at' => $subscription->ends_at?->toISOString(),
                    'grace_ends_at' => $subscription->grace_ends_at?->toISOString(),
                ],
                'ip_hash' => request()->ip() ? hash('sha256', (string) request()->ip()) : null,
                'created_at' => now('UTC'),
            ]);

            return $subscription;
        }, attempts: 3);
    }

    /**
     * Move expired active/trial subscriptions into a 24-hour grace period, then suspend
     * them when grace expires. Confirmed bookings are not changed by this service.
     *
     * @return array{grace_started:int,suspended:int,skipped:int}
     */
    public function processDue(int $limit = 200): array
    {
        $limit = max(1, min($limit, 500));
        $result = ['grace_started' => 0, 'suspended' => 0, 'skipped' => 0];

        $expiredIds = Subscription::query()
            ->whereIn('status', [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE])
            ->where('ends_at', '<=', now('UTC'))
            ->orderBy('ends_at')
            ->limit($limit)
            ->pluck('id');

        foreach ($expiredIds as $subscriptionId) {
            $changed = DB::transaction(function () use ($subscriptionId): bool {
                $subscription = Subscription::query()->whereKey($subscriptionId)->lockForUpdate()->firstOrFail();

                if (
                    ! in_array($subscription->status, [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE], true)
                    || $subscription->ends_at === null
                    || $subscription->ends_at->isFuture()
                ) {
                    return false;
                }

                $before = $subscription->status;
                $graceEndsAt = $subscription->ends_at->copy()->utc()->addHours(24);
                $subscription->update([
                    'status' => Subscription::STATUS_GRACE_PERIOD,
                    'grace_ends_at' => $graceEndsAt,
                ]);

                AuditLog::query()->create([
                    'actor_user_id' => null,
                    'actor_role' => 'system',
                    'action' => 'subscription.grace_period_started',
                    'auditable_type' => Subscription::class,
                    'auditable_id' => $subscription->getKey(),
                    'reason' => 'Subscription period ended; 24-hour grace period started.',
                    'before_state_json' => ['status' => $before, 'ends_at' => $subscription->ends_at?->toISOString()],
                    'after_state_json' => ['status' => Subscription::STATUS_GRACE_PERIOD, 'grace_ends_at' => $graceEndsAt->toISOString()],
                    'ip_hash' => null,
                    'created_at' => now('UTC'),
                ]);

                return true;
            }, attempts: 3);

            $result[$changed ? 'grace_started' : 'skipped']++;
        }

        $graceIds = Subscription::query()
            ->where('status', Subscription::STATUS_GRACE_PERIOD)
            ->where('grace_ends_at', '<', now('UTC'))
            ->orderBy('grace_ends_at')
            ->limit($limit)
            ->pluck('id');

        foreach ($graceIds as $subscriptionId) {
            $changed = DB::transaction(function () use ($subscriptionId): bool {
                $subscription = Subscription::query()->whereKey($subscriptionId)->lockForUpdate()->firstOrFail();

                if (
                    $subscription->status !== Subscription::STATUS_GRACE_PERIOD
                    || $subscription->grace_ends_at === null
                    || $subscription->grace_ends_at->greaterThanOrEqualTo(now('UTC'))
                ) {
                    return false;
                }

                $before = $subscription->status;
                $subscription->update([
                    'status' => Subscription::STATUS_SUSPENDED,
                    'suspended_at' => now('UTC'),
                ]);

                AuditLog::query()->create([
                    'actor_user_id' => null,
                    'actor_role' => 'system',
                    'action' => 'subscription.suspended_after_grace',
                    'auditable_type' => Subscription::class,
                    'auditable_id' => $subscription->getKey(),
                    'reason' => 'Subscription grace period expired; new bookings and publication eligibility must be blocked.',
                    'before_state_json' => ['status' => $before, 'grace_ends_at' => $subscription->grace_ends_at?->toISOString()],
                    'after_state_json' => ['status' => Subscription::STATUS_SUSPENDED, 'suspended_at' => $subscription->suspended_at?->toISOString()],
                    'ip_hash' => null,
                    'created_at' => now('UTC'),
                ]);

                return true;
            }, attempts: 3);

            $result[$changed ? 'suspended' : 'skipped']++;
        }

        return $result;
    }
}
