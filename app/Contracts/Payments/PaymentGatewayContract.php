<?php

namespace App\Contracts\Payments;

use App\Models\Payment;
use Carbon\CarbonImmutable;

interface PaymentGatewayContract
{
    /**
     * Create or retrieve a QRIS charge using the same stable idempotency key on retries.
     *
     * @return array{
     *   gateway_provider:string,
     *   gateway_transaction_id:string,
     *   qris_payload:string,
     *   gateway_fee_amount:int,
     *   status:string
     * }
     */
    public function createQrisCharge(Payment $payment, CarbonImmutable $expiresAt): array;

    /**
     * Query the authoritative charge state without creating another charge.
     *
     * @return array{
     *   status:string,
     *   gateway_transaction_id:?string,
     *   gateway_fee_amount:int,
     *   paid_at:?CarbonImmutable
     * }
     */
    public function getChargeStatus(Payment $payment): array;
}
