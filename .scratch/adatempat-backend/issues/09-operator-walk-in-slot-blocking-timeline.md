# 09: Operator Walk-in Slot Blocking and Schedule Timeline Control

**What to build:**
Venue Operator timeline controls and manual slot blocking capabilities for walk-in players and court maintenance. Venue Operators can view an interactive daily court schedule timeline displaying slot statuses (available, held, booked, blocked), customer names, booking references, and check-in statuses. Operators can manually transition available slots to blocked status with an explanatory reason (e.g., walk-in cash customer or court maintenance). The blocking action strictly prevents overriding slots that are already booked or actively held in checkout (returning HTTP 409 Conflict). Operators can also unblock maintenance slots back to available for marketplace booking.

**Blocked by:** 07: Digital Ticket Attendance Verification and Front-Desk Check-in

**Status:** ready-for-human

- [x] Operator backoffice timeline view endpoint returns daily court schedule data with discrete 60-minute slot statuses and reservation details for the operator's assigned venue.
- [x] Timeline responses include customer identifiers, payment status, and check-in timestamps for booked slots.
- [x] Slot blocking endpoint allows operators to transition an available slot to blocked status with a mandatory or optional explanatory reason.
- [x] Blocking service asserts that the target slot is currently in available status and belongs to the operator's assigned venue.
- [x] Attempting to block a slot that is currently held in checkout or already booked by a customer is rejected with HTTP 409 Conflict.
- [x] Slot unblocking endpoint allows operators to transition a previously blocked maintenance slot back to available status.
- [x] Unblocking action verifies that the slot is currently in blocked status and clears the block reason.
- [x] All blocking and unblocking operations are guarded by venue operator authorization policies, rejecting cross-venue operations with HTTP 403 Forbidden.
- [x] Automated test suite verifies timeline data structure, successful walk-in slot blocking, conflict rejection on held/booked slots, maintenance unblocking, and multi-tenant authorization guards.
