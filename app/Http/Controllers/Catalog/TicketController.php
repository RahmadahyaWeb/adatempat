<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class TicketController extends Controller
{
    /**
     * Display the digital check-in ticket via signed URL or authenticated access.
     */
    public function show(Request $request, string $code): Response
    {
        /** @var Booking $booking */
        $booking = Booking::query()
            ->with(['venue', 'space', 'slots', 'addOns'])
            ->where('code', $code)
            ->firstOrFail();

        // Validate signature, with fallback to authenticated booking owner/staff access
        if (! $request->hasValidSignature()) {
            $user = $request->user();
            $isAuthorizedUser = $user !== null && (
                $user->id === $booking->user_id
                || in_array($user->role->value, ['superadmin', 'staff', 'owner'], true)
            );

            if (! $isAuthorizedUser) {
                abort(403, 'Tautan tiket ini tidak valid atau telah kedaluwarsa.');
            }
        }

        $response = Inertia::render('public/ticket', [
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
                'checked_in_at' => $booking->checked_in_at?->toIso8601String(),
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
        ])->toResponse($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
