# 08: Operator Daily Timeline and Walk-in Slot Blocking

**What to build:** Venue operators can view a comprehensive daily timeline calendar of all courts in their facility. Operators can block open slots for walk-in players or facility maintenance with an optional note, immediately removing them from online marketplace search. Operators can also unblock previously blocked slots, returning them to marketplace availability.

**Blocked by:** 04: Court Slot Generation and Real-time Availability Matrix, 07: Digital Ticket Issuance and Front-Desk Attendance Verification

**Status:** ready-for-agent

- [ ] Endpoint provides daily timeline view grouping slots by court across 60-minute operational intervals.
- [ ] Endpoint allows operator to block available slots with a mandatory or optional note (`block_reason`).
- [ ] Blocked slots are immediately excluded from public marketplace booking queries.
- [ ] Endpoint allows operator to unblock non-booked slots, returning status to `available`.
- [ ] Multi-tenant scoping strictly prevents operators from viewing or blocking slots belonging to other venues.
- [ ] Automated Pest feature tests covering timeline retrieval, walk-in blocking, unblocking, and multi-tenant authorization.
