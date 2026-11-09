# Design Midtrans Dynamic QRIS Payment and Webhook Reconciliation

Type: grilling
Status: resolved
Blocked by: 01, 03

## Question

How should the Midtrans Core API Dynamic QRIS charge payload be formed, and how should inbound webhooks validate SHA-512 signatures, guarantee idempotent updates, and trigger pessimistic settlement commits?

## Answer

All architectural questions regarding Midtrans Core API Dynamic QRIS payment generation, webhook verification, state reconciliation, and client status synchronization are resolved:

1. **Midtrans Core API Dynamic QRIS Charge Payload**:
   - Outbound charge requests to `POST https://api.midtrans.com/v2/charge` (or sandbox) are formatted as:
     ```json
     {
       "payment_type": "qris",
       "transaction_details": {
         "order_id": "ADT-20261115-3921",
         "gross_amount": 300000
       },
       "qris": {
         "acquirer": "gopay"
       },
       "custom_expiry": {
         "expiry_duration": 10,
         "unit": "minute"
       }
     }
     ```
   - Parameter `custom_expiry` is strictly aligned with the Redis slot lock TTL (600 seconds), ensuring the payment gateway QRIS code and server-side slot hold expire concurrently.
   - The returned `actions` (QR string / QR code image URL) and transaction metadata are persisted into the `payments` table and returned to the player checkout view.

2. **Strict SHA-512 Signature Hash Verification**:
   - Inbound webhook requests to `/api/webhooks/midtrans` compute the expected signature string:
     ```php
     $calculatedSignature = hash('sha512', $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . config('services.midtrans.server_key'));
     ```
   - The system verifies the inbound `signature_key` using timing-attack safe comparison `hash_equals($calculatedSignature, $payload['signature_key'])`.
   - Any signature discrepancy immediately halts execution, logs a critical security alert, and returns HTTP 401 Unauthorized without modifying database state.

3. **Idempotent Webhook Processing & Concurrency Protection**:
   - Webhook processing executes inside a database transaction acquiring a row-level lock on the target booking:
     `$booking = Booking::where('booking_code', $orderId)->lockForUpdate()->first();`
   - If `$booking->payment_status === 'paid'` (or an already finalized terminal state), the system immediately performs an idempotent bypass: updates the raw webhook payload in the `payments` audit table and returns HTTP 200 OK without re-running state transitions or double-releasing locks.

4. **Deterministic Status Transition Mapping**:
   - `settlement` (or `capture` with `fraud_status: "accept"`): Transitions `booking.payment_status = 'paid'`, records `booking.paid_at = now()`, commits slots to `booked` via pessimistic lock, updates `payments.transaction_status = 'settlement'`, and releases temporary Redis hold locks.
   - `expire`: Transitions `booking.payment_status = 'expired'`, releases slots back to `available` (if unbooked), updates `payments.transaction_status = 'expire'`, and releases Redis locks.
   - `cancel` or `deny`: Transitions `booking.payment_status = 'cancelled'`, resets slots to `available`, updates `payments.transaction_status`, and releases Redis locks.
   - `pending`: Retains `payment_status = 'pending'` and records the intermediate gateway payload.

5. **Client-Side Real-Time Status Synchronization**:
   - **Frontend Short Polling**: The checkout page polls `GET /checkout/{booking_code}/status` every 3 seconds while the QRIS modal is active.
   - **Direct Inquiry Fallback**: If the player clicks "Check Payment Status" manually or polling nears timeout without webhook receipt, the backend directly calls Midtrans Status API (`GET /v2/{order_id}/status`), validates the signature, and triggers immediate settlement reconciliation on demand.

