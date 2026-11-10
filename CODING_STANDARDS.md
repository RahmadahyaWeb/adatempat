# Coding Standards & Architecture Guardrails

This document defines architectural conventions and non-mechanical judgement calls for the `adatempat` codebase. It is primarily consulted during code reviews (`/code-review`).

---

## 1. Controller & Service Layer Boundary

- **Thin Controllers**: Controllers coordinate HTTP requests, invoke authorization policies, and return JSON responses or view redirects.
- **Service Layer Delegation**: All domain business logic, multi-model orchestrations, and database transactions must live inside dedicated single-responsibility service classes in `app/Services/`.
- **Zero Direct Transactions in Controllers**: Controllers must never call `DB::transaction()`, `DB::beginTransaction()`, `DB::commit()`, or `DB::rollBack()`. This is mechanically enforced by `tests/ArchTest.php`.

---

## 2. Concurrency & Deadlock Prevention

- **Deterministic Lock Ordering**: When acquiring locks on multiple resources (such as multiple court slots in a single booking), always sort entity IDs in numeric ascending order before acquiring locks.
- **Two-Phase Lock Engine**:
  1. Phase 1 (Distributed): Acquire temporary Redis atomic locks with explicit TTL (e.g., 600s for checkout hold) using fail-fast rollback on contention.
  2. Phase 2 (Pessimistic DB): Acquire row-level locks (`SELECT ... FOR UPDATE`) inside database transactions during status mutation or settlement.
- **Fail-Fast Rollback**: If any slot or entity in a multi-item batch cannot be acquired, immediately release all previously acquired locks in the batch and return `HTTP 409 Conflict`.

---

## 3. Financial & Payment Idempotency

- **Cryptographic Signature Verification**: Inbound payment gateway notifications (e.g., Midtrans webhooks) must authenticate signatures using timing-safe comparisons (`hash_equals`).
- **Strict Idempotent Settlement**: Payment webhooks must be idempotent. If a webhook delivery arrives for an already settled booking (`PaymentStatus::PAID`), return `HTTP 200 OK` immediately without re-executing slot state transitions or corrupting audit logs.
- **Single-Use Synchronized Expiry**: Payment charges (such as Midtrans Dynamic QRIS) must synchronize their expiration windows with the underlying slot hold duration.

---

## 4. Multi-Tenant Scoping & Policy Authorization

- **Operator Tenant Scoping**: Venue Operators are strictly bound to their assigned venue (`assigned_venue_id`). All backoffice operations (courts, pricing rules, timeline, walk-in blocking, attendance check-in) must verify venue ownership through Laravel Policies (`app/Policies/`).
- **Cross-Tenant Isolation**: Requests attempting to view or manipulate resources of another venue must be rejected with `HTTP 403 Forbidden` or `HTTP 404 Not Found`.
- **Superadmin Oversight**: Only the Superadmin role possesses cross-tenant oversight via global authorization bypass (`Gate::before`).
- **SaaS Subscription Gating**: Inactive or expired venue subscriptions must prevent marketplace booking and restrict operator backoffice operations to read-only mode.

---

## 5. Reschedule & Operational Policies

- **Strict H-1 Cutoff**: Customer self-service rescheduling is strictly permitted only up to 24 hours prior to the earliest booked slot start time.
- **Exact Price Parity**: Replacement slots must match the exact locked hourly price of the original booking.
- **Single-Use Rule**: Bookings can be rescheduled at most once (`rescheduled_at` timestamp check).
- **Atomic Slot Swap**: Rescheduling must execute an atomic slot swap within a single database transaction: the previous slot transitions back to `available`, and the new slot transitions to `booked`.

---

## 6. Testing & Quality Standards

- **Strict Type Declarations**: All PHP files must declare `declare(strict_types=1);` (enforced by `tests/ArchTest.php`).
- **String-Backed Enums**: All domain status enums must be string-backed (`enum Name: string`) and reside in `app/Enums/`.
- **Pest Test Structure**:
  - `tests/Unit/`: Fast tests for enums, models, policies, and service logic with isolated mocks or controlled transactions.
  - `tests/Feature/`: HTTP integration tests asserting authentication, validation, authorization, status codes, and database state.
  - `tests/ArchTest.php`: Codebase architecture integrity rules.
- **Zero Debugging Artifacts**: Never leave `dd()`, `dump()`, or `ray()` in the codebase (enforced by `tests/ArchTest.php`).
