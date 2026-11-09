# Formulate Pest PHP Automated Verification Test Matrix

Type: grilling
Status: resolved
Blocked by: 03, 05, 08

## Question

What is the comprehensive Pest PHP automated test suite specification (unit tests, HTTP feature tests, mock webhook assertions, and concurrent checkout race simulations) required to guarantee zero regressions for the backend?

## Answer

All architectural questions regarding the Pest PHP automated test suite blueprint and verification matrix are resolved:

1. **Three-Tier Test Suite Architecture**:
   - `tests/Unit/`: Pure domain logic tests without HTTP or database overhead. Covers `SlotStatus` and `BookingStatus` state machine transition rules, `PricingRule` resolution algorithms (weekday vs. weekend, peak vs. regular), SHA-512 signature hash calculation, and the 24-hour H-1 reschedule cutoff logic.
   - `tests/Feature/`: Comprehensive integration tests utilizing `RefreshDatabase`. Covers full HTTP request cycles, validation rules, middleware boundaries, multi-slot checkout holds, and database commits.
   - `tests/ArchTest.php`: Architecture governance tests leveraging `pestphp/pest-plugin-arch`. Enforces that models extend Eloquent, enums are string-backed, controllers delegate domain logic to services, strict types are enforced, and debug calls (`dd`, `dump`, `ray`) are strictly absent.

2. **Isolated Offline Mock Boundaries (`Http::fake()` & `Mail::fake()`)**:
   - Full CI and local testing independence is guaranteed by hermetic mock boundaries:
     - `Http::fake()` intercepts Midtrans Core API `/v2/charge` and `/v2/{order_id}/status`, responding with realistic JSON fixtures for QRIS strings and transaction metadata.
     - `Mail::fake()` intercepts `LoginMagicLinkMail` and `BookingReceiptMail`, allowing exact assertions on recipients, token attachments, and signed route payloads without external SMTP network calls.

3. **Concurrency & Race Condition Verification Suite (`DoubleBookingConcurrencyTest.php`)**:
   - Simulates parallel checkout requests targeting identical court slots: asserts that exactly one request succeeds with HTTP 200/201 while competing requests receive HTTP 409 Conflict with an immediate release of any partially acquired locks.
   - Tests intersecting multi-slot reservations (Player A [Slot 1, Slot 2] vs. Player B [Slot 2, Slot 1]) to verify that deterministic numerical sorting (`sort($slotIds, SORT_NUMERIC)`) prevents distributed Redis and MySQL deadlocks.

4. **Payment Webhook & Callback Idempotency Suite (`MidtransWebhookTest.php`)**:
   - Validates that requests with tampered or invalid SHA-512 signatures abort with HTTP 401 Unauthorized without modifying booking or slot states.
   - Validates that legitimate `settlement` payloads atomically commit court slots to `booked` and bookings to `paid`, while releasing Redis locks.
   - Asserts idempotency: delivering duplicate or triplicate webhook payloads for an already `paid` booking returns HTTP 200 OK without re-executing state transitions or corrupting audit timestamps.

5. **Multi-Tenant Scoping & Security Boundary Suite (`TenantScopingTest.php`)**:
   - Asserts cross-tenant isolation: Operator A cannot view, mutate, or block slots on Operator B's venue or courts (HTTP 403 Forbidden).
   - Asserts check-in boundary security: Operator A scanning a ticket issued by Operator B receives HTTP 403 Forbidden.
   - Asserts subscription state gating: Operators of `suspended` or `expired` venues can view historical records but are blocked from creating courts or blocking slots (HTTP 403 Forbidden).
   - Asserts player privacy: Players attempting to view another customer's digital ticket or booking details receive HTTP 403 Forbidden.

