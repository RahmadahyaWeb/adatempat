# 06: Midtrans Dynamic QRIS Payment and Webhook Settlement

**What to build:** A held booking generates a single-channel Dynamic QRIS payment charge via Midtrans Core API `/v2/charge`. Inbound payment webhooks from Midtrans are validated against cryptographic SHA-512 signatures (`hash('sha512', order_id + status_code + gross_amount + server_key)`). Valid settlement callbacks atomically transition target slots to `booked` and the booking to `paid` inside a database transaction utilizing MySQL pessimistic locking (`SELECT ... FOR UPDATE`), with idempotent duplicate handling.

**Blocked by:** 05: Two-Tier Concurrency Lock and Checkout Hold Engine

**Status:** ready-for-agent

- [ ] Checkout integration calls Midtrans Core API `/v2/charge` with payment type `qris` and stores returned QR string and expiration.
- [ ] Inbound webhook endpoint `POST /api/webhooks/midtrans` validates SHA-512 signature hash; forged requests rejected with HTTP 401.
- [ ] Settlement transitions execute inside a database transaction with `lockForUpdate()` on court slots.
- [ ] Idempotent callback handling: duplicate settlement webhooks return HTTP 200 without modifying state or re-firing side effects.
- [ ] On-demand payment status verification endpoint querying Midtrans `/v2/{order_id}/status` as a webhook fallback.
- [ ] Automated Pest feature tests covering QRIS charge generation, SHA-512 signature validation, pessimistic settlement commit, and webhook idempotency.
