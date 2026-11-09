# 04: Court Slot Generation and Real-time Availability Matrix

**What to build:** Discrete 60-minute interval court slots are generated for courts and dates, with prices automatically resolved from active pricing rules. Players can query the slot availability matrix for a selected court and date, receiving slots in their correct lifecycle states (`available`, `held`, `booked`, `blocked`) with computed prices.

**Blocked by:** 03: Venue Onboarding and Court Pricing Configuration

**Status:** ready-for-agent

- [ ] System generates discrete 60-minute interval court slots (e.g., 07:00-08:00, 08:00-09:00) with no half-hour offsets.
- [ ] Slot hourly prices are dynamically evaluated from matching court pricing rules based on day of the week and start time.
- [ ] Public query endpoint returns slot availability matrix for a specified court and date with accurate state indicators.
- [ ] Sub-80ms response time under concurrent query evaluation.
- [ ] Automated Pest feature tests covering slot generation, pricing rule matching, and slot matrix query responses.
