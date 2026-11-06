<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class XenditWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $expectedToken = (string) config('services.xendit.callback_token');

        if ($expectedToken === '') {
            return response()->json(['message' => 'Xendit callback token is not configured.'], 503);
        }

        if (! hash_equals($expectedToken, (string) $request->header('x-callback-token', ''))) {
            return response()->json(['message' => 'Invalid callback token.'], 401);
        }

        $payload = $request->validate([
            'event' => ['required', 'string'],
            'data' => ['required', 'array'],
        ]);

        $data = $payload['data'];
        $identifier = $data['payment_id']
            ?? $data['payment_request_id']
            ?? $data['refund_id']
            ?? $data['id']
            ?? hash('sha256', $request->getContent());

        $cacheKey = 'xendit:webhook:'.hash('sha256', $payload['event'].'|'.$identifier);
        $firstDelivery = Cache::add($cacheKey, true, now()->addDays(7));

        Log::info('Xendit webhook received', [
            'event' => $payload['event'],
            'identifier' => $identifier,
            'duplicate' => ! $firstDelivery,
        ]);

        return response()->json([
            'received' => true,
            'duplicate' => ! $firstDelivery,
        ]);
    }
}
