<?php

namespace App\Services\Subscriptions;

use App\Models\Business;
use App\Models\Subscription;
use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class SubscriptionEligibilityService
{
    public function eligibleForBusiness(Business $business, ?CarbonInterface $at = null): ?Subscription
    {
        $at = $at ? CarbonImmutable::instance($at)->utc() : CarbonImmutable::now('UTC');

        if (
            $business->status !== Business::STATUS_ACTIVE
            || $business->published_at === null
            || $business->owner_id === null
        ) {
            return null;
        }

        $subscription = Subscription::query()
            ->where('owner_id', $business->owner_id)
            ->where('starts_at', '<=', $at)
            ->where(function ($query) use ($at): void {
                $query->where(function ($active) use ($at): void {
                    $active->whereIn('status', [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE])
                        ->where('ends_at', '>', $at);
                })->orWhere(function ($grace) use ($at): void {
                    $grace->where('status', Subscription::STATUS_GRACE_PERIOD)
                        ->where('grace_ends_at', '>=', $at);
                });
            })
            ->where(function ($query) use ($business): void {
                $query->whereNull('retained_business_id')
                    ->orWhere('retained_business_id', $business->getKey());
            })
            ->with('plan')
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'trialing' THEN 1 ELSE 2 END")
            ->orderByDesc('ends_at')
            ->first();

        return $subscription;
    }

    public function assertBusinessBookable(Business $business, ?CarbonInterface $at = null): Subscription
    {
        $subscription = $this->eligibleForBusiness($business, $at);

        if (! $subscription) {
            throw ValidationException::withMessages([
                'subscription' => 'Usaha belum memiliki langganan yang memenuhi syarat untuk menerima booking.',
            ]);
        }

        return $subscription;
    }

    public function canCreateBusiness(int $ownerId, ?CarbonInterface $at = null): bool
    {
        $at = $at ? CarbonImmutable::instance($at)->utc() : CarbonImmutable::now('UTC');
        $subscription = $this->eligibleForOwner($ownerId, $at);

        if (! $subscription) {
            return false;
        }

        $businessCount = Business::query()
            ->where('owner_id', $ownerId)
            ->whereNotIn('status', [Business::STATUS_SUSPENDED, Business::STATUS_REJECTED, Business::STATUS_ARCHIVED_BY_DOWNGRADE])
            ->count();

        return $businessCount < (int) $subscription->plan->max_businesses;
    }

    public function canCreateVenue(Business $business, ?CarbonInterface $at = null): bool
    {
        $at = $at ? CarbonImmutable::instance($at)->utc() : CarbonImmutable::now('UTC');
        $subscription = $this->eligibleForBusiness($business, $at);

        if (! $subscription) {
            return false;
        }

        $venueCount = $business->venues()
            ->whereNotIn('status', ['archived', 'rejected'])
            ->count();

        return $venueCount < (int) $subscription->plan->max_venues_per_business;
    }

    private function eligibleForOwner(int $ownerId, CarbonInterface $at): ?Subscription
    {
        return Subscription::query()
            ->where('owner_id', $ownerId)
            ->where('starts_at', '<=', $at)
            ->where(function ($query) use ($at): void {
                $query->where(function ($active) use ($at): void {
                    $active->whereIn('status', [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE])
                        ->where('ends_at', '>', $at);
                })->orWhere(function ($grace) use ($at): void {
                    $grace->where('status', Subscription::STATUS_GRACE_PERIOD)
                        ->where('grace_ends_at', '>=', $at);
                });
            })
            ->with('plan')
            ->orderByDesc('ends_at')
            ->first();
    }
}
