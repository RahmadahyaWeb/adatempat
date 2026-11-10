# 07: Digital Ticket Attendance Verification and Front-Desk Check-in

**What to build:**
A digital ticket issuance and front-desk attendance verification engine for confirmed bookings. Upon payment settlement, Players receive an immutable digital ticket featuring a unique booking code, venue details, scheduled court slot times, and a cryptographic QR token (`qr_token`). Venue Operators can verify player arrival at the front desk by scanning the QR token or manually entering the booking code. The verification engine enforces four strict gates: verifying tenant match (venue ownership), paid financial status, match with current operational date, and cryptographic token validity. A duplicate check-in audit guard prevents reuse of checked-in tickets, and successful check-ins log the operator ID and timestamp. A scheduled artisan command periodically transitions elapsed reservations to completed status.

**Blocked by:** 06: Midtrans Dynamic QRIS Payment and Idempotent Webhook Settlement

**Status:** ready-for-human

- [x] Successful payment settlement generates a digital ticket record with a cryptographic 64-character QR verification token.
- [x] Players can retrieve their digital tickets and viewing history through authenticated profile endpoints.
- [x] Digital ticket access is protected by authorization policies: players cannot inspect tickets belonging to other players (HTTP 403 Forbidden).
- [x] Front-desk check-in endpoint allows Venue Operators to verify attendance via scanned QR token or manual booking code entry.
- [x] Check-in engine validates that the ticket belongs to the operator's assigned venue, rejecting cross-venue tickets with HTTP 403 Forbidden.
- [x] Check-in engine validates that the booking has paid financial status and that the slot date matches the current calendar date.
- [x] Duplicate check-in attempts are rejected with HTTP 422 Unprocessable Entity, returning the original check-in timestamp and staff identifier.
- [x] Valid check-ins update check-in status to checked-in, recording the operator's user ID and timestamp for operational audits.
- [x] Scheduled artisan command runs periodically to mark bookings whose court slot operational hours have completely passed as completed.
- [x] Automated test suite verifies digital ticket issuance, player privacy policies, four-gate check-in validation, duplicate check-in prevention, and scheduled lifecycle completion.
