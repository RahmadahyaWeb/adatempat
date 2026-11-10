<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PaymentReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransWebhookController extends Controller
{
    /**
     * Handle incoming Midtrans payment notification webhook.
     */
    public function __invoke(Request $request, PaymentReconciliationService $service): JsonResponse
    {
        $payload = $request->all();

        $result = $service->processWebhook($payload);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment notification processed.',
            'data' => $result,
        ]);
    }
}
