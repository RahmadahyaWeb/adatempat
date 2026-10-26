<?php

namespace App\Http\Controllers;

use App\Contracts\Payments\PaymentWebhookContract;
use App\Services\Payments\PaymentWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JsonException;

class PaymentWebhookController
{
    public function handle(
        Request $request,
        string $provider,
        PaymentWebhookContract $webhook,
        PaymentWebhookProcessor $processor,
    ): JsonResponse {
        abort_unless(hash_equals((string) config('services.payment_gateway.driver', 'mock'), $provider), 404);

        $rawBody = $request->getContent();

        if (! $webhook->verifyWebhookSignature($rawBody, $request->header('X-Payment-Signature'))) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['message' => 'Invalid JSON payload.'], 400);
        }

        if (! is_array($payload)) {
            return response()->json(['message' => 'Webhook payload must be a JSON object.'], 422);
        }

        try {
            $event = $webhook->normalizeWebhookPayload($payload);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => 'Webhook payload schema is invalid.'], 422);
        }

        $result = $processor->process($provider, $event);

        if ($result['status'] === 'failed') {
            return response()->json([
                'message' => 'Webhook was recorded but could not be processed; retry is safe.',
                'duplicate' => $result['duplicate'],
            ], 500);
        }

        return response()->json([
            'status' => $result['status'],
            'duplicate' => $result['duplicate'],
        ]);
    }
}
