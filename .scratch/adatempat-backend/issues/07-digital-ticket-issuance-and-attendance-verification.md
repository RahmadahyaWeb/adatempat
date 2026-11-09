# 07: Digital Ticket Issuance and Front-Desk Attendance Verification

**What to build:** Confirmed bookings generate a digital ticket containing a unique human-readable booking code and a cryptographic `qr_token`, and dispatch an email confirmation receipt. Venue receptionists can check in arriving players either via one-click manual confirmation or by submitting the scanned `qr_token`. The system records check-in timestamp and rejects duplicate check-in attempts with an amber warning.

**Blocked by:** 06: Midtrans Dynamic QRIS Payment and Webhook Settlement

**Status:** ready-for-agent

- [ ] Paid bookings issue a unique booking code (e.g. `ADT-20261109-8821`) and unique 64-character cryptographic `qr_token`.
- [ ] Transactional confirmation email with booking breakdown and digital ticket link dispatched upon settlement.
- [ ] Front-desk check-in endpoint allows manual one-click confirmation by booking ID.
- [ ] Front-desk check-in endpoint allows QR token submission, validating booking state and updating `check_in_status` to `checked_in`.
- [ ] Repeated check-in attempts on already checked-in tickets are rejected with initial check-in timestamp details.
- [ ] Automated Pest feature tests covering ticket generation, email dispatch, manual check-in, QR check-in, and duplicate check-in prevention.
