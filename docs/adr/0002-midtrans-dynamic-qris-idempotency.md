# ADR 0002: Midtrans Dynamic QRIS Payment and Idempotent Webhook Settlement

## Status
Accepted

## Context
Payment processing for sports bookings in Indonesia demands high conversion rates and rapid settlement. Dynamic QRIS offers seamless mobile payments across Indonesian banks and digital wallets (BCA, Mandiri, GoPay, ShopeePay, OVO).

However, payment gateway integrations face several critical vulnerabilities:
1. Insecure webhooks susceptible to man-in-the-middle forging or replay attacks.
2. Duplicate webhook deliveries triggering repeated state transitions or double-crediting.
3. Asynchrony between slot hold timeouts and payment QR code validity, allowing customers to pay after slots have expired.

## Decision
We implemented an automated, idempotent payment engine integrated with Midtrans Core API Dynamic QRIS:

1. **Synchronized Expiry**: Dynamic QRIS charges are generated with custom 10-minute expiry timestamps synchronized exactly with the booking's 10-minute slot hold TTL.
2. **Timing-Safe Cryptographic Signature Verification**: Inbound webhooks compute `hash('sha512', order_id + status_code + gross_amount + server_key)` and verify against `signature_key` using timing-safe comparison (`hash_equals`). Invalid signatures are rejected with HTTP 401 Unauthorized.
3. **Pessimistic Row Lock on Settlement**: Settlement webhook processing executes inside a database transaction acquiring a row-level lock (`SELECT ... FOR UPDATE`) on the target booking.
4. **Strict Idempotency**: If a booking is already settled (`PaymentStatus::PAID`), duplicate webhook deliveries immediately return HTTP 200 OK without re-triggering slot transitions or audit logs.
5. **Gateway Status Inquiry Fallback**: Checkout clients can poll payment status every 3 seconds, backed by a manual inquiry button that queries the Midtrans Status API directly in case of webhook network delay.

## Consequences
- **Positive**: Cryptographically secure payment processing, tamper-proof webhook handling, zero double-settlements, and instant fallback for players.
- **Trade-off**: Requires configuration of valid Midtrans Server Keys and merchant ID in environment variables.
