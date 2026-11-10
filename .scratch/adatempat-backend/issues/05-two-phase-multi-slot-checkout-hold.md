# 05: Two-Phase Multi-Slot Checkout Hold Engine

**What to build:**
A high-concurrency two-phase locking engine that enables Players to reserve multiple court slots simultaneously without double-booking or distributed deadlocks. When entering checkout, the player selects one or more slots; the engine sorts slot IDs numerically, acquires atomic Redis locks with a 600-second (10-minute) TTL, and commits a synchronized database hold state marking slots as held and generating a pending draft booking with immutable locked prices. If any slot in the batch is unavailable or fails lock acquisition, the engine executes an immediate fail-fast rollback, releasing all partial locks and returning HTTP 409 Conflict. A dual cleanup strategy guarantees availability: read queries evaluate expired holds just-in-time, while a scheduled artisan command prunes abandoned records every minute.

**Blocked by:** 04: Public Venue Discovery and Slot Availability API

**Status:** ready-for-human

- [x] Booking and BookingItem entities represent customer reservations and locked-price slot line items.
- [x] Table `booking_items` enforces a composite unique constraint `(booking_id, court_slot_id)`, allowing historical retention without unique key conflicts when slots are re-booked.
- [x] Checkout hold initiation accepts an array of court slot IDs and sorts them in ascending numerical order before attempting lock acquisition to prevent deadlocks.
- [x] Redis atomic locks are acquired per slot (`court_slot:lock:{id}`) with a 600-second (10-minute) TTL.
- [x] Any failure to acquire a lock within the requested set triggers an immediate rollback of all previously acquired locks in that request and returns HTTP 409 Conflict.
- [x] Successful Redis locking executes a database transaction updating slot rows to held status with `locked_until` set to 10 minutes in the future.
- [x] A pending draft booking is created with a unique booking code, calculated total amount in integer Rupiah, and corresponding booking items locking the current slot prices.
- [x] Players can explicitly abandon checkout, immediately resetting slot status to available and releasing Redis locks.
- [x] Slot availability queries dynamically treat slots with expired holds (`locked_until <= now()`) as available just-in-time.
- [x] Scheduled artisan command runs every minute to transition abandoned draft bookings to expired status and reset database slot statuses.
- [x] Automated test suite verifies multi-slot lock acquisition, numeric ordering deadlock prevention, fail-fast rollback on contention, JIT expiration evaluation, and scheduled prune cleanup.
