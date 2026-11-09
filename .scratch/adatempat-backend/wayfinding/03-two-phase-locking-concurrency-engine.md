# Define Two-Phase Anti-Double-Booking Locking Architecture

Type: grilling
Status: resolved
Blocked by: 01, 02

## Question

How must the two-phase anti-double-booking locking algorithm (Redis atomic lock with 600s TTL + MySQL `SELECT ... FOR UPDATE` pessimistic transaction) coordinate multi-slot checkouts, handle lock contention, and clean up expired holds?

## Answer

All architectural questions regarding the Two-Phase Anti-Double-Booking Locking Engine are resolved:

1. **Multi-Slot Lock Ordering & Deadlock Prevention**:
   - To eliminate distributed deadlocks when multiple players concurrently attempt to reserve intersecting multi-slot intervals, slot IDs are strictly sorted in ascending numerical order (`sort($slotIds, SORT_NUMERIC)`) prior to lock acquisition.
   - Redis atomic locks use the key pattern `court_slot:lock:{$slotId}` with a 600-second (10-minute) TTL and owner token matching the checkout session/booking reference.
   - If lock acquisition fails for any individual slot in the requested set, the engine executes an immediate fail-fast rollback, releasing all previously acquired locks in that request before throwing a `SlotUnavailableException` (HTTP 409 Conflict).

2. **Bidirectional State Synchronization (Redis Lock -> MySQL `held` State)**:
   - Once all Redis locks in a checkout request are acquired, the engine executes a rapid MySQL transaction:
     - Verifies `status === 'available'` (or `status === 'held'` with `locked_until <= now()`).
     - Updates `court_slots` rows: `status = 'held'`, `locked_until = now()->addSeconds(600)`.
     - Inserts the `bookings` draft (`payment_status = 'pending'`, `total_amount = ...`) and associated `booking_items`.
   - This allows marketplace and backoffice calendar queries to instantly display the "In Checkout" warning state directly from the database without incurring $N$ Redis queries per request.

3. **Dual Expired Hold Cleanup Strategy**:
   - **Just-In-Time (JIT) Evaluation**: Read queries for slot availability dynamically interpret any slot with `status = 'held' AND locked_until <= NOW()` as `available`, ensuring zero latency for customer booking even if background cleanup has not yet fired.
   - **Scheduled Cleanup Command**: A Laravel scheduled command (`php artisan court-slots:prune-expired-holds`) runs every minute via the Laravel Scheduler to update abandoned bookings (`payment_status = 'expired'`), release database slot rows (`status = 'available'`, `locked_until = NULL`), and force-release any lingering Redis lock keys.

4. **Pessimistic Settlement Commit (`lockForUpdate`)**:
   - Upon receipt of a valid Midtrans QRIS webhook (`transaction_status: "settlement"`):
     - The transaction opens `DB::transaction()`.
     - Acquires row-level pessimistic locks via `CourtSlot::whereIn('id', $slotIds)->lockForUpdate()->get()`.
     - Asserts that none of the target slots have already transitioned to `booked` by another transaction.
     - Atomically commits `court_slots.status = 'booked'`, `bookings.payment_status = 'paid'`, `bookings.paid_at = now()`, and records the payment audit log in `payments`.
     - Post-commit, triggers `forceRelease()` on the temporary Redis locks `court_slot:lock:{$slotId}`.

5. **Atomic Reschedule Slot Swap**:
   - Customer self-service reschedule acquires a Redis lock on the target replacement slot.
   - Inside `DB::transaction()`:
     - Acquires pessimistic locks via `lockForUpdate()` on both the existing slot and the replacement slot.
     - Validates that the original slot belongs to the authenticated player's booking, and that the new slot is available and belongs to an identical price tier.
     - Atomically swaps states: original slot transitions to `available`, replacement slot transitions to `booked`.
     - Updates `booking_items.court_slot_id` to point to the replacement slot.
     - Commits the transaction and releases the Redis lock on the replacement slot.

