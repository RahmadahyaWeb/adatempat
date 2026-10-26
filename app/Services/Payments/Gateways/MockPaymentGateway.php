<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGatewayContract;
use App\Contracts\Payments\PaymentWebhookContract;
use App\Exceptions\Payments\PaymentGatewayException;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class MockPaymentGateway implements PaymentGatewayContract, PaymentWebhookContract
{
    public function createQrisCharge(Payment $payment, CarbonImmutable $expiresAt): array
    {
        if ($expiresAt->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
            throw new PaymentGatewayException('Payment deadline has passed before QRIS initialization.', true);
        }

        $transactionId = 'MOCK-' . strtoupper(substr(hash('sha256', $payment->idempotency_key), 0, 24));

        return [
            'gateway_provider' => 'mock',
            'gateway_transaction_id' => $transactionId,
            'qris_payload' => 'MOCK-QRIS:' . $transactionId . ':EXPIRES:' . $expiresAt->toIso8601String(),
            'gateway_fee_amount' => 0,
            'status' => Payment::STATUS_PENDING,
        ];
    }

    public function getChargeStatus(Payment $payment): array
    {
        return [
            'status' => Payment::STATUS_PENDING,
            'gateway_transaction_id' => $payment->gateway_transaction_id,
            'gateway_fee_amount' => (int) $payment->gateway_fee_amount,
            'paid_at' => null,
        ];
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        if (! is_string($signature) || trim($signature) === '') {
            return false;
        }

        $provided = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;
        $secret = (string) config('services.payment_gateway.webhook_secret', '');

        if ($secret === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $provided);
    }

    public function normalizeWebhookPayload(array $payload): array
    {
        $eventId = $payload['event_id'] ?? null;
        $transactionId = $payload['gateway_transaction_id'] ?? null;
        $status = $payload['status'] ?? null;
        $fee = $payload['gateway_fee_amount'] ?? 0;
        $paidAt = $payload['paid_at'] ?? null;

        if (
            ! is_string($eventId) || trim($eventId) === '' || mb_strlen($eventId) > 140
            || ! is_string($transactionId) || trim($transactionId) === '' || mb_strlen($transactionId) > 120
            || ! in_array($status, [Payment::STATUS_PAID, Payment::STATUS_FAILED, Payment::STATUS_EXPIRED], true)
            || ! is_numeric($fee) || (int) $fee < 0
        ) {
            throw new InvalidArgumentException('Webhook payload does not match the mock gateway event schema.');
        }

        if ($status === Payment::STATUS_PAID) {
            if (! is_string($paidAt) || trim($paidAt) === '') {
                throw new InvalidArgumentException('A paid webhook must include the gateway paid_at timestamp.');
            }

            try {
                $paidAt = CarbonImmutable::parse($paidAt)->utc()->toIso8601String();
            } catch (\\Throwable $exception) {
                throw new InvalidArgumentException('The gateway paid_at timestamp is invalid.', previous: $exception);
            }
        } else {
            $paidAt = null;
        }

        return [
            'event_id' => trim($eventId),
            'gateway_transaction_id' => trim($transactionId),
            'status' => $status,
            'paid_at' => $paidAt,
            'gateway_fee_amount' => (int) $fee,
        ];
    }
}
