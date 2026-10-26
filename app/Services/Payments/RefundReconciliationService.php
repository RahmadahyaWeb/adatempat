<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayContract;
use App\Models\AuditLog;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Throwable;

class RefundReconciliationService
{
    public function __construct(
        private readonly PaymentGatewayContract $gateway,
        private readonly RefundService $refunds,
    ) {
    }

    /**
     * Retry failed requests with the same idempotency key and query existing processing refunds.
     *
     * @return array{scanned:int,succeeded:int,processing:int,failed:int,skipped:int}
     */
    public function reconcile(int $limit = 100): array
    {
        $refundIds = Refund::query()
            ->whereIn('status', [Refund::STATUS_PROCESSING, Refund::STATUS_FAILED])
            ->orderBy('id')
            ->limit(max(1, min($limit, 500)))
            ->pluck('id');

        $result = [
            'scanned' => $refundIds->count(),
            'succeeded' => 0,
            'processing' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($refundIds as $refundId) {
            $refund = Refund::query()->find($refundId);

            if (! $refund) {
                $result['skipped']++;

                continue;
            }

            if ($refund->status === Refund::STATUS_FAILED) {
                $updated = $this->refunds->submit($refund);

                if ($updated->status === Refund::STATUS_SUCCEEDED) {
                    $result['succeeded']++;
                } elseif ($updated->status === Refund::STATUS_PROCESSING) {
                    $result['processing']++;
                } else {
                    $result['failed']++;
                }

                continue;
            }

            try {
                $status = $this->gateway->getRefundStatus($refund);
            } catch (Throwable $exception) {
                $result['skipped']++;

                continue;
            }

            $gatewayRefundId = $status['gateway_refund_id'] ?? $refund->gateway_refund_id;
            $gatewayState = $status['status'] ?? 'unknown';

            if ($gatewayState === Refund::STATUS_SUCCEEDED) {
                $this->refunds->markSucceeded($refund, $gatewayRefundId);
                $result['succeeded']++;

                continue;
            }

            if ($gatewayState === Refund::STATUS_FAILED) {
                DB::transaction(function () use ($refund, $gatewayRefundId): void {
                    $locked = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();

                    if ($locked->status === Refund::STATUS_SUCCEEDED) {
                        return;
                    }

                    $before = $locked->status;
                    $locked->update([
                        'status' => Refund::STATUS_FAILED,
                        'gateway_refund_id' => $gatewayRefundId ?: $locked->gateway_refund_id,
                    ]);

                    AuditLog::query()->create([
                        'actor_user_id' => null,
                        'actor_role' => 'system',
                        'action' => 'refund.reconciliation_failed',
                        'auditable_type' => Refund::class,
                        'auditable_id' => $locked->getKey(),
                        'reason' => 'Gateway status query confirmed refund failure; the obligation remains open.',
                        'before_state_json' => ['status' => $before],
                        'after_state_json' => ['status' => Refund::STATUS_FAILED],
                        'ip_hash' => null,
                        'created_at' => now('UTC'),
                    ]);
                }, attempts: 3);

                $result['failed']++;

                continue;
            }

            if ($gatewayRefundId && $gatewayRefundId !== $refund->gateway_refund_id) {
                $refund->update(['gateway_refund_id' => $gatewayRefundId]);
            }

            $result['processing']++;
        }

        return $result;
    }
}
