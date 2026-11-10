# 06: Midtrans Dynamic QRIS Payment and Idempotent Webhook Settlement

**What to build:**
Automated payment generation and reconciliation using Midtrans Core API Dynamic QRIS. When a Player initiates payment for a held booking, the system creates a dynamic QRIS charge with a 10-minute custom expiry synchronized with the slot hold TTL. Inbound webhooks sent by Midtrans are authenticated using timing-safe SHA-512 signature hash validation. Valid settlement webhooks acquire row-level pessimistic database locks (`SELECT ... FOR UPDATE`), transition booking status to paid, commit court slots to booked, persist payment audit logs, and release temporary Redis hold locks. Webhook processing is strictly idempotent, returning HTTP 200 without duplicate state transitions when retried. The checkout interface supports 3-second status polling and manual inquiry fallback against the Midtrans Status API.

**Blocked by:** 05: Two-Phase Multi-Slot Checkout Hold Engine

**Status:** ready-for-human

- [x] Payment entity models gateway audit trails, storing order reference, Midtrans transaction ID, gross amount in integer Rupiah, payment type, status, QR string, and raw webhook payloads.
- [x] Outbound charge request to Midtrans Core API formats a dynamic QRIS payload with 10-minute custom expiry matching the hold duration.
- [x] Charge response persists QR string and metadata to the payment record and returns them to the checkout client.
- [x] Webhook endpoint verifies inbound SHA-512 signature using timing-safe comparison (`hash_equals`), rejecting discrepancies with HTTP 401 Unauthorized.
- [x] Webhook settlement processing acquires a row-level lock on the booking and asserts valid status transitions.
- [x] Legitimate settlement transitions booking payment status to paid, records paid-at timestamp, commits slots to booked, and releases Redis hold locks.
- [x] Duplicate or triplicate webhook notifications for an already paid booking return HTTP 200 OK idempotently without re-triggering transitions or corrupting audit data.
- [x] Payment expiration or cancellation webhooks transition booking status accordingly, reset court slots to available, and release Redis locks.
- [x] Checkout status polling endpoint allows clients to check payment status every 3 seconds.
- [x] Manual status inquiry endpoint queries the Midtrans Status API directly, verifies signatures, and reconciles booking state on demand.
- [x] Automated test suite verifies QRIS charge formation, signature tampering detection, pessimistic settlement locking, duplicate webhook idempotency, and status polling fallback.
