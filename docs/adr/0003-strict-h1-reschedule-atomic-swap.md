# ADR 0003: Strict H-1 Self-Service Reschedule and Atomic Slot Swapping

## Status
Accepted

## Context
Venue operators experience substantial financial loss when players cancel or change bookings at the last minute, leaving prime court slots empty and unsellable. Simultaneously, players need reasonable flexibility to reschedule games when unforeseen schedule conflicts occur.

Uncontrolled rescheduling introduces:
1. Infinite rolling postponements.
2. Revenue disparity when moving between peak and off-peak slots.
3. Race conditions where old slots are freed before new slots are secured.

## Decision
We implemented a strict customer self-service reschedule policy governed by four atomic gates:

1. **Venue Permission Gate**: The venue must explicitly enable customer rescheduling (`allow_reschedule = true`).
2. **Strict H-1 Cutoff**: The reschedule request must be initiated at least 24 hours prior to the earliest booked slot start time. Requests inside the 24-hour cutoff are rejected with HTTP 422 Unprocessable Entity.
3. **Exact Price Parity**: The replacement slot's hourly rate must exactly equal the original locked slot price.
4. **Single-Use Limit**: Each booking may be rescheduled at most once (`rescheduled_at` null check).
5. **Atomic Slot Swap**: Slot reallocation is executed inside a database transaction with pessimistic locking (`SELECT ... FOR UPDATE` on both old and new slots):
   - The old slot transitions back to `available` with cleared booking references.
   - The new slot transitions to `booked` linked to the booking item.
   - The digital ticket schedule and QR tokens are atomically updated.

## Consequences
- **Positive**: Protects venue revenue, eliminates last-minute slot abandonment, prevents price arbitrage, and guarantees zero orphaned slots.
- **Trade-off**: Players cannot reschedule into higher-priced or lower-priced slots without a manual refund/upsell workflow.
