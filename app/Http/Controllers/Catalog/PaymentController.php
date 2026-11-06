<?php

namespace App\Http\Controllers\Catalog;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Bookings\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(
        protected BookingService $bookingService,
        protected PaymentGatewayInterface $paymentGateway,
    ) {}

    /**
     * Display the payment page for a booking code.
     */
    public function show(Request $request, string $code): Response
    {
        /** @var Booking $booking */
        $booking = Booking::query()
            ->with([
                'venue',
                'space',
                'slots',
                'addOns',
                'user',
            ])
            ->where('code', $code)
            ->firstOrFail();

        $this->authorizeBookingAccess($request, $booking);
        // On-the-fly expiration check if booking hold has elapsed
        if ($booking->status === BookingStatus::PendingPayment && $booking->expires_at !== null && now()->isAfter($booking->expires_at)) {
            $this->bookingService->expireBooking($booking);
            $booking->refresh();
            $booking->load(['venue', 'space', 'slots', 'addOns', 'user']);
        }

        if ($booking->status === BookingStatus::PendingPayment) {
            $this->paymentGateway->createPayment($booking);
        }

        return Inertia::render('public/payment-pending', [
            'is_demo_mode' => ! app()->isProduction(),
            'is_confirmed' => $booking->status === BookingStatus::Confirmed,
            'booking' => [
                'code' => $booking->code,
                'status' => $booking->status->value,
                'status_label' => $booking->status->label(),
                'booking_date' => $booking->booking_date->toDateString(),
                'start_time' => substr($booking->start_time, 0, 5),
                'end_time' => substr($booking->end_time, 0, 5),
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'customer_email' => $booking->customer_email,
                'notes' => $booking->notes,
                'subtotal_amount' => (float) $booking->subtotal_amount,
                'addons_amount' => (float) $booking->addons_amount,
                'total_amount' => (float) $booking->total_amount,
                'payment_method' => $booking->payment_method->value,
                'payment_method_label' => $booking->payment_method->label(),
                'qr_checkin_token' => $booking->qr_checkin_token,
                'expires_at' => $booking->expires_at?->toIso8601String(),
                'venue' => [
                    'id' => $booking->venue->id,
                    'name' => $booking->venue->name,
                    'slug' => $booking->venue->slug,
                    'city' => $booking->venue->city,
                    'address' => $booking->venue->address,
                    'contact_phone' => $booking->venue->contact_phone,
                ],
                'space' => [
                    'id' => $booking->space->id,
                    'name' => $booking->space->name,
                    'slug' => $booking->space->slug,
                    'category' => $booking->space->category->value,
                    'category_label' => $booking->space->category->label(),
                ],
                'slots' => $booking->slots->map(fn ($slot) => [
                    'start_time' => substr($slot->start_time, 0, 5),
                    'end_time' => substr($slot->end_time, 0, 5),
                    'price' => (float) $slot->price,
                ])->values(),
                'addOns' => $booking->addOns->map(fn ($addon) => [
                    'name' => $addon->name,
                    'unit_price' => (float) $addon->unit_price,
                    'quantity' => $addon->quantity,
                    'total_price' => (float) $addon->total_price,
                ])->values(),
                'cancellation_policy' => $booking->cancellation_policy_snapshot,
            ],
        ]);
    }

    /**
     * Return real-time payment and booking status via JSON for client polling.
     */
    public function status(Request $request, string $code): JsonResponse
    {
        /** @var Booking $booking */
        $booking = Booking::query()
            ->where('code', $code)
            ->firstOrFail();

        $this->authorizeBookingAccess($request, $booking);

        if ($booking->status === BookingStatus::PendingPayment && $booking->expires_at !== null && now()->isAfter($booking->expires_at)) {
            $this->bookingService->expireBooking($booking);
            $booking->refresh();
        }

        if ($booking->status === BookingStatus::PendingPayment) {
            $gatewayStatus = $this->paymentGateway->getPaymentStatus($booking->code);
            if ($gatewayStatus === 'PAID') {
                $this->bookingService->confirmBooking($booking);
                $booking->refresh();
            }
        }

        return response()->json([
            'code' => $booking->code,
            'status' => $booking->status->value,
            'status_label' => $booking->status->label(),
            'is_confirmed' => $booking->status === BookingStatus::Confirmed,
            'is_expired' => $booking->status === BookingStatus::Expired,
            'expires_at' => $booking->expires_at?->toIso8601String(),
        ]);
    }

    /**
     * Simulate payment success in non-production environments.
     */
    public function simulate(Request $request, string $code): JsonResponse|RedirectResponse
    {
        if (app()->isProduction()) {
            abort(403, 'Simulasi pembayaran hanya tersedia di lingkungan non-produksi.');
        }

        /** @var Booking $booking */
        $booking = Booking::query()
            ->where('code', $code)
            ->firstOrFail();

        $this->authorizeBookingAccess($request, $booking);

        $this->paymentGateway->simulatePaymentSuccess($booking->code);
        $confirmed = $this->bookingService->confirmBooking($booking);

        if (! $confirmed && $booking->status === BookingStatus::Expired) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pesanan telah kedaluwarsa dan tidak dapat dibayar.',
                    'status' => $booking->status->value,
                ], 422);
            }

            return redirect()->back()->with('error', 'Pesanan telah kedaluwarsa.');
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'status' => $booking->status->value,
                'status_label' => $booking->status->label(),
            ]);
        }

        return redirect()->back()->with('success', 'Pembayaran berhasil disimulasikan.');
    }

    /**
     * Ensure user has authorization to view or act on the booking.
     */
    protected function authorizeBookingAccess(Request $request, Booking $booking): void
    {
        $user = $request->user();

        if (! $user || ($booking->user_id !== $user->id && ! in_array($user->role->value, ['superadmin', 'staff'], true))) {
            abort(403, 'Anda tidak memiliki akses ke pesanan ini.');
        }
    }
}
