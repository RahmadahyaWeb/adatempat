<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGatewayContract;
use App\Exceptions\Payments\PaymentGatewayException;
use App\Models\Payment;
use Carbon\CarbonImmutable;

class MockPaymentGateway implements PaymentGatewayContract
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
}
