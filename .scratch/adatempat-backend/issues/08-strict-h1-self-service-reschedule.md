# 08: Strict H-1 Self-Service Reschedule Engine

**What to build:**
A customer self-service reschedule engine that empowers Players to move their paid reservations to alternative available court slots under strict operational rules. Rescheduling requires that the venue enables the policy (`allow_reschedule = true`), that at least 24 hours (H-1) remain prior to the earliest booked slot start time, that the replacement slot satisfies exact hourly price parity with the original locked price, and that the booking has not been rescheduled previously (single-use limit). The reschedule is executed as an atomic slot swap within a database transaction using pessimistic locking, simultaneously freeing the old court slot back to available and committing the replacement slot to booked.

**Blocked by:** 07: Digital Ticket Attendance Verification and Front-Desk Check-in

**Status:** ready-for-human

- [x] Reschedule initiation validates venue policy configuration, rejecting requests if the venue disables rescheduling.
- [x] Policy engine asserts that the reschedule request occurs at least 24 hours (H-1) before the start time of the earliest booked court slot, rejecting late requests with HTTP 422 Unprocessable Entity.
- [x] System verifies that the target replacement slot belongs to an equivalent court at the same venue and matches the exact locked hourly rate of the original booking item.
- [x] Rescheduling is restricted to at most one occurrence per booking, rejecting subsequent attempts once the rescheduled timestamp is recorded.
- [x] Reschedule execution acquires a temporary Redis lock on the replacement slot.
- [x] Transaction executes an atomic slot swap inside a database transaction with pessimistic row locks (`SELECT ... FOR UPDATE`): original slot transitions to available, replacement slot transitions to booked.
- [x] Booking item record is updated to point to the replacement slot, and booking status transitions to rescheduled with a recorded timestamp.
- [x] Temporary Redis lock on the replacement slot is released post-commit.
- [x] Digital ticket displays updated court slot times and preserves audit history.
- [x] Automated test suite verifies H-1 cutoff enforcement, price parity checks, single-use restriction, concurrent slot swap race condition safety, and atomic rollback on failure.
