<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Services\PaymentReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PaymentController extends Controller
{
    /**
     * Initiate Midtrans Dynamic QRIS payment for a held booking.
     */
    public function pay(Request $request, Booking $booking, PaymentReconciliationService $service): JsonResponse
    {
        $this->authorizeBookingAccess($request, $booking);

        if ($booking->payment_status !== PaymentStatus::Pending) {
            return response()->json([
                'status' => 'error',
                'message' => "Booking cannot be charged in [{$booking->payment_status->value}] status.",
            ], 422);
        }

        if ($booking->qris_expires_at !== null && $booking->qris_expires_at->lte(now())) {
            return response()->json([
                'status' => 'error',
                'message' => 'Checkout hold has expired.',
            ], 422);
        }

        $payment = $service->initiateQrisPayment($booking);
        $booking->refresh();

        return response()->json([
            'status' => 'success',
            'data' => [
                'booking_code' => $booking->booking_code,
                'total_amount' => $booking->total_amount,
                'payment_status' => $booking->payment_status->value,
                'midtrans_transaction_id' => $payment->midtrans_transaction_id,
                'qr_string' => $payment->qr_string,
                'qr_url' => $payment->qr_url,
                'expires_at' => $payment->expiry_time?->toISOString() ?? $booking->qris_expires_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Polling endpoint to check booking payment status (supports 3-second polling).
     */
    public function status(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeBookingAccess($request, $booking);

        return response()->json([
            'status' => 'success',
            'data' => [
                'booking_code' => $booking->booking_code,
                'payment_status' => $booking->payment_status->value,
                'check_in_status' => $booking->check_in_status->value,
                'paid_at' => $booking->paid_at?->toISOString(),
                'expires_at' => $booking->qris_expires_at?->toISOString(),
                'qr_token' => $booking->payment_status === PaymentStatus::Paid ? $booking->qr_token : null,
            ],
        ]);
    }

    /**
     * Manual fallback inquiry against Midtrans Status API.
     */
    public function inquire(Request $request, Booking $booking, PaymentReconciliationService $service): JsonResponse
    {
        $this->authorizeBookingAccess($request, $booking);

        try {
            $reconciled = $service->inquireAndReconcile($booking);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Failed to query Midtrans status: {$e->getMessage()}",
            ], 502);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'booking_code' => $reconciled->booking_code,
                'payment_status' => $reconciled->payment_status->value,
                'paid_at' => $reconciled->paid_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Authorize that the authenticated player owns the booking or is a platform superadmin.
     *
     * @throws HttpException
     */
    protected function authorizeBookingAccess(Request $request, Booking $booking): void
    {
        $user = $request->user();

        if ($booking->user_id !== $user->id && ! $user->isSuperadmin()) {
            abort(403, 'You are not authorized to access this booking.');
        }
    }
}
