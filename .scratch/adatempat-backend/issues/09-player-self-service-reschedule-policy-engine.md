# 09: Player Self-Service Reschedule Policy Engine

**What to build:** Players can self-service reschedule a confirmed booking to an alternative open slot if the venue permits it (`allow_reschedule = true`), the request is made at least 24 hours prior to the booked slot start time (H-1 cutoff), and the target slot has an identical price tier. The old slot is released to `available` and the new slot is marked `booked` within an atomic database transaction with strict zero-cash-refund policy.

**Blocked by:** 06: Midtrans Dynamic QRIS Payment and Webhook Settlement, 08: Operator Daily Timeline and Walk-in Slot Blocking

**Status:** ready-for-agent

- [ ] Reschedule requests are rejected if venue setting `allow_reschedule` is false.
- [ ] Reschedule requests submitted with $< 24$ hours remaining before slot start time are rejected with policy violation error.
- [ ] Reschedule requests to different pricing tiers (price upgrades or downgrades) are rejected in MVP.
- [ ] Valid reschedule requests execute an atomic database transaction releasing old slots to `available` and claiming new slots as `booked`.
- [ ] Booking record transitions payment status to `rescheduled` and links to the newly allocated court slots.
- [ ] Automated Pest feature tests covering venue permission gate, 24-hour cutoff enforcement, price parity validation, and atomic slot swap.
