## Destination

A fully resolved technical architecture, domain specification, MySQL 8.0+ schema, and test blueprint for the adatempat Laravel 12 monolith backend, ready for implementation without architectural ambiguities or unvetted trade-offs.

## Notes

- **Domain**: Indonesian sports venue marketplace & B2B SaaS Backoffice management ([GLOSSARY.md](file:///Users/muhammadachyadirahmat/Developer/adatempat-v2/GLOSSARY.md), [PRD.md](file:///Users/muhammadachyadirahmat/Developer/adatempat-v2/docs/PRD.md)).
- **Primary Skills**: `wayfinder`, `domain-modeling`, `codebase-design`, `grilling`.
- **Standing Preferences**:
  - Architecture: Laravel 12 monolith backend-only scope (Inertia controller responses with structured props + JSON responses for API/webhooks/checkout).
  - Database & Cache: MySQL 8.0+ InnoDB (`utf8mb4_unicode_ci`) + Redis 7 for atomic slot locks.
  - Authentication: Passwordless Magic Link email via Laravel stateful session cookies and temporary signed routes (no password, no SMS/WA OTP).
  - Concurrency: Two-tier locking (Redis Atomic Lock 600s TTL for checkout hold + MySQL pessimistic locking `SELECT ... FOR UPDATE` for settlement commit).
  - Payment: Midtrans Dynamic QRIS single-channel with SHA-512 signature validation and idempotent webhook handling.
  - Testing: Pest PHP comprehensive automated test suite (Unit & Feature tests) with mock boundaries (`Http::fake()`, `Mail::fake()`).
  - Language Protocol: English for technical docs, identifiers, and code; Bahasa Indonesia for discussions with Rahmat.

## Decisions so far

- [Settle Domain Entities, Status Enums, and Booking State Machine](file:///Users/muhammadachyadirahmat/Developer/adatempat-v2/.scratch/adatempat-backend/wayfinding/01-domain-glossary-and-entities.md): Resolved single User model with roles, BookingItem pivot for multi-slot checkout, separate Payment gateway audit record, and strict SlotStatus/BookingStatus state machines.
- [Design MySQL 8.0+ Database Schema and Indexing Strategy](file:///Users/muhammadachyadirahmat/Developer/adatempat-v2/.scratch/adatempat-backend/wayfinding/02-mysql-schema-and-indexing.md): Established unsignedBigInteger IDR representation, composite unique (booking_id, court_slot_id), covering index idx_slots_availability for sub-80ms slot queries, strict foreign key onDelete rules, and soft deletes for venues/courts.
- [Define Two-Phase Anti-Double-Booking Locking Architecture](file:///Users/muhammadachyadirahmat/Developer/adatempat-v2/.scratch/adatempat-backend/wayfinding/03-two-phase-locking-concurrency-engine.md): Established deterministic multi-slot lock sorting, Redis lock (600s TTL) with MySQL `held` state sync, dual JIT/scheduled cleanup for expired holds, pessimistic `lockForUpdate` webhook settlement commit, and atomic reschedule slot swap.
- [Specify Court Slot Generation and Tiered Pricing Matrix](file:///Users/muhammadachyadirahmat/Developer/adatempat-v2/.scratch/adatempat-backend/wayfinding/04-slot-generation-and-pricing-matrix.md): Established 30-day rolling window AOT generation with idempotent JIT fallback, strict 60-minute interval bounds (opening/closing time), tiered pricing resolution with baseline default_hourly_rate fallback, and unbooked-only future price propagation.
- [Design Midtrans Dynamic QRIS Payment and Webhook Reconciliation](file:///Users/muhammadachyadirahmat/Developer/adatempat-v2/.scratch/adatempat-backend/wayfinding/05-midtrans-qris-integration-and-webhook.md): Established custom_expiry 10-minute QRIS charge payload, SHA-512 signature validation via hash_equals, row-level lock idempotent webhook reconciliation, deterministic status transition mapping, and hybrid client polling + direct inquiry fallback.
- [Specify Passwordless Magic Link Authentication Flow](file:///Users/muhammadachyadirahmat/Developer/adatempat-v2/.scratch/adatempat-backend/wayfinding/06-passwordless-magic-link-auth-flow.md): Established JIT player creation, URL::temporarySignedRoute with Redis atomic Cache::pull single-use nonce invalidation, 5 req/15 min rate limiter with 60s cooldown, session regeneration, and anti-enumeration response.
- [Establish Multi-Tenant Scoping and Role Authorization](file:///Users/muhammadachyadirahmat/Developer/adatempat-v2/.scratch/adatempat-backend/wayfinding/07-tenant-scoping-and-authorization.md): Established operator isolation via route middleware and policies, subscription-based marketplace exclusion and read-only backoffice mode, Superadmin dedicated routes with Gate::before bypass, and strict check-in/walk-in blocking authorization.
- [Define Reschedule Policy Engine and Check-in Verification Logic](file:///Users/muhammadachyadirahmat/Developer/adatempat-v2/.scratch/adatempat-backend/wayfinding/08-reschedule-and-attendance-verification.md): Established strict H-1 (>=24h advance) cutoff, exact price parity requirement with 1-time limit, 4-gate QR token attendance validation, duplicate check-in audit prevention, and automated completed status lifecycle transitions.
- [Formulate Pest PHP Automated Verification Test Matrix](file:///Users/muhammadachyadirahmat/Developer/adatempat-v2/.scratch/adatempat-backend/wayfinding/09-pest-test-matrix-and-verification-blueprint.md): Established 3-tier test suite architecture (Unit, Feature, Arch), hermetic mock boundaries (Http::fake, Mail::fake), parallel checkout race and deadlock verification, duplicate webhook idempotency test assertions, and multi-tenant security boundary testing.

## Not yet specified

- Asynchronous queue worker topology and supervisor configuration (database queue vs Redis Horizon) under peak concurrent checkout loads.
- Audit trail logging format for backoffice operator actions (walk-in slot blocking, check-ins, manual status overrides).

## Out of scope

- Frontend UI client implementation (React 18, Tailwind CSS, Inertia page views).
- Multi-payment channels beyond Dynamic QRIS (Credit Cards, Virtual Accounts, Paylater, E-Wallets deep links).
- Cash refunds or bank transfer payout automation.
- WhatsApp Business API notifications (Fonnte/Wablas) for MVP.
- Automated SaaS in-app recurring subscription billing.
- AI matchmaking & dynamic surge pricing algorithms.
