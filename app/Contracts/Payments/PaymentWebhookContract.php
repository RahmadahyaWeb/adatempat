<?php

namespace App\Contracts\Payments;

interface PaymentWebhookContract
{
    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool;

    /**
     * Return a minimal normalized event; do not pass raw provider payload fields
     * into the settlement layer.
     *
     * @return array{
     *   event_id:string,
     *   gateway_transaction_id:string,
     *   status:string,
     *   paid_at:?string,
     *   gateway_fee_amount:int,
     *   amount:int
     * }
     */
    public function normalizeWebhookPayload(array $payload): array;

    /**
     * @return array{event_id:string,gateway_refund_id:string,status:string}
     */
    public function normalizeRefundWebhookPayload(array $payload): array;
}
