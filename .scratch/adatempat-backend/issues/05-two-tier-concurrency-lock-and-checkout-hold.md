# 05: Two-Tier Concurrency Lock and Checkout Hold Engine

**What to build:** Authenticated players can initiate checkout holding one or multiple consecutive available slots. The system acquires an atomic Redis lock (`Cache::lock`) for 600 seconds (10 minutes) across all selected slots. If any slot is already held or booked, the entire hold transaction is aborted with HTTP 409 Conflict. A scheduled background cleanup worker releases expired holds and resets slot availability.

**Blocked by:** 04: Court Slot Generation and Real-time Availability Matrix

**Status:** ready-for-agent

- [ ] Endpoint `POST /checkout/hold` acquires atomic Redis lock with 600-second TTL for all requested slot IDs.
- [ ] Multi-slot holds are atomic: failure to acquire any single slot releases all previously acquired locks in the batch.
- [ ] Concurrent checkout requests on identical slots yield exactly 1 success and N-1 HTTP 409 Conflict responses.
- [ ] Expired hold cleanup job releases Redis locks and updates stale pending booking records.
- [ ] Automated Pest concurrency simulation tests validating atomic multi-slot hold, contention rejection, and expiry cleanup.
