# ADR 0001: Two-Phase Locking and Deadlock Prevention for Multi-Slot Checkout

## Status
Accepted

## Context
In sports venue booking, customers frequently reserve multiple adjacent or non-adjacent court slots across one or more courts in a single checkout session. Under peak concurrent traffic (e.g., Friday evening peak hour releases), multiple players may attempt to checkout overlapping slots simultaneously.

Without deterministic locking:
1. Inconsistent lock acquisition order across concurrent requests leads to classic distributed deadlocks.
2. Direct database row locks alone cause high contention, database thread exhaustion, and sluggish user experience.
3. Over-relying purely on cache locks risks data corruption if cache eviction or timeout occurs mid-transaction.

## Decision
We implemented a two-phase concurrency engine combining distributed Redis atomic locks and MySQL pessimistic row locks (`SELECT ... FOR UPDATE`):

1. **Deterministic Lock Ordering**: Court slot IDs in a multi-slot checkout batch are sorted in numerical ascending order prior to lock acquisition.
2. **Phase 1 (Distributed Cache Lock)**: Acquire atomic Redis locks with a 600-second (10-minute) TTL (`court_slot_lock:{slot_id}`).
   - If any slot in the batch is already locked or unavailable, execute immediate fail-fast rollback releasing all previously acquired locks in the batch and abort with HTTP 409 Conflict.
3. **Phase 2 (Pessimistic Database Hold)**: Open a MySQL transaction with row-level locks on the selected slots, verify slot bookability, mark slots as `held` with `hold_expires_at = now() + 10 min`, and record a pending draft booking.
4. **Dual Cleanup Engine**: Expired holds are dynamically treated as available during real-time queries (covering index scan) while an automated artisan command (`slots:prune-holds`) periodically purges stale holds.

## Consequences
- **Positive**: Zero deadlocks, zero double-bookings, high concurrency tolerance, and sub-second checkout hold response times.
- **Trade-off**: Requires running both Redis and MySQL instances in all testing and deployment environments.
