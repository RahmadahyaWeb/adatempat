# 03: Tiered Pricing Matrix and Hybrid Slot Generation Engine

**What to build:**
A deterministic court slot generation engine combined with a tiered pricing resolution matrix. Venue Operators can define custom pricing rules for courts based on day type (weekday vs. weekend) and time windows (peak vs. regular hours), falling back to the court's baseline default hourly rate when no rule covers an interval. Court slots are generated in discrete 60-minute blocks strictly aligned on the hour within court operational boundaries. The system maintains a rolling 30-day window of available slots via an automated scheduled command and provides an idempotent just-in-time fallback for ungenerated dates. Updates to pricing rules dynamically propagate exclusively to future unbooked slots, keeping existing holds and confirmed bookings immutable.

**Blocked by:** 02: Venue and Court Management with Operator Scoping

**Status:** ready-for-agent

- [ ] Venue Operators can define court pricing rules specifying day type (weekday or weekend), start time, end time, and price per hour.
- [ ] Court slot entity models discrete 60-minute time intervals on specific dates, tracking price, slot status, locked-until timestamps, and optional block reasons.
- [ ] Composite unique database constraint `(court_id, date, start_time)` prevents duplicate slot creation for identical operational hours.
- [ ] Covering index `idx_slots_availability (court_id, date, status, start_time, price)` is applied to optimize slot availability lookups.
- [ ] Pricing resolution engine matches slot intervals against active pricing rules and reliably falls back to the court's default hourly rate.
- [ ] Ahead-of-time slot generation command produces discrete 60-minute slots up to 30 days ahead across all active courts.
- [ ] Just-in-time slot generation service dynamically creates missing slots idempotently upon first calendar view for valid dates within the booking horizon.
- [ ] Pricing rule updates propagate exclusively to future slots in available status, leaving held, booked, and blocked slots untouched.
- [ ] Slot queries and generation requests beyond the venue's configured booking horizon days are rejected at the policy boundary.
- [ ] Automated test suite verifies discrete hour generation, pricing rule hierarchy, default rate fallback, unique constraint conflict handling, and pricing propagation to future unbooked slots.
