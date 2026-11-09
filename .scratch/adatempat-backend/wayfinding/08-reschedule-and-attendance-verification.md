# Define Reschedule Policy Engine and Check-in Verification Logic

Type: grilling
Status: resolved
Blocked by: 03, 07

## Question

How should the customer self-service reschedule policy (H-1 / 24-hour cutoff, price parity constraint, atomic slot swap) and front-desk attendance verification (QR token validation, duplicate check-in prevention) be enforced?

## Answer

All architectural questions regarding the customer self-service reschedule policy and front-desk attendance check-in verification logic are resolved:

1. **Strict H-1 / 24-Hour Cutoff Enforcement**:
   - The reschedule action is visible only when the venue setting `venues.allow_reschedule === true`.
   - The domain service verifies advance notice against the earliest slot start time:
     `now()->diffInHours(Carbon::parse("{$earliestSlot->date} {$earliestSlot->start_time}"), false) >= 24`.
   - If less than 24 hours remain before kickoff, the UI disables the reschedule button and any API call is rejected with HTTP 422 Unprocessable Entity: *"Reschedule is only permitted at least 24 hours prior to the booked playing schedule."*

2. **Price Parity & Single-Use Reschedule Constraint**:
   - **Exact Price Parity**: The replacement slot must possess an identical hourly price (`new_slot.price === old_item.price_locked`) on an equivalent court within the same venue. Price downgrades do not yield cash or balance refunds, and price upgrades are prohibited in MVP.
   - **Single-Use Limit**: Rescheduling is strictly limited to 1 occurrence per reservation (`bookings.rescheduled_at IS NULL`). Once executed, `bookings.rescheduled_at = now()` is set and subsequent reschedule attempts are rejected.

3. **Multi-Layer Digital Ticket Check-In Verification**:
   - The check-in endpoint (`POST /operator/check-in`) accepts either `qr_token` (from camera scan) or `booking_code` (manual entry).
   - The validation engine applies four verification gates:
     1. **Tenant Matching**: `booking.venue_id === Auth::user()->venue_id` (prevents cross-venue admissions).
     2. **Financial Status**: `booking.payment_status IN ('paid', 'rescheduled')` (denies pending, expired, or cancelled reservations).
     3. **Operational Date**: The reservation date matches the current local date (`Carbon::today()`).
     4. **Cryptographic Validity**: `qr_token` matches the 64-character hash stored on the booking record.

4. **Fraud & Double Check-In Prevention**:
   - The service inspects `booking.check_in_status`.
   - If `check_in_status === 'checked_in'`, the system rejects the request with HTTP 422 Unprocessable Entity, returning detailed audit metadata: *"This digital ticket has already been checked in at {checked_in_at} by operator {staff_name}."*
   - On first valid verification, the service records:
     `check_in_status = 'checked_in'`, `checked_in_at = now()`, and `checked_in_by = Auth::id()`.

5. **Automated Lifecycle Completion**:
   - A scheduled command (`php artisan bookings:mark-completed`) runs periodically to transition reservations whose court slot duration has completely elapsed into `completed`, maintaining real-time occupancy reporting accuracy.

