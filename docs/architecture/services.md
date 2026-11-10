# Architecture: Domain Service Catalog

This document defines the service boundaries and responsibilities for all domain services in `app/Services/`. Controllers coordinate HTTP requests, enforce policies, and delegate all business transactions to these services.

---

## 1. Inventory & Slot Lifecycle

### `CourtSlotAvailabilityService`
- **Location**: `app/Services/CourtSlotAvailabilityService.php`
- **Responsibility**: Single entry point for querying court slot availability. Triggers transparent JIT generation via `SlotGenerationService` if slots are missing, runs MySQL covering index scan (`idx_slots_availability`), dynamically evaluates Redis hold expirations, and returns formatted slot dtos.
- **Consumers**: `PublicCourtSlotController`, `OperatorCourtSlotController`, `OperatorTimelineService`.

### `SlotGenerationService`
- **Location**: `app/Services/SlotGenerationService.php`
- **Responsibility**: Generates discrete 60-minute court slots bounded strictly by venue opening/closing times up to the venue's booking horizon. Runs idempotently (never overwrites booked or blocked slots).
- **Consumers**: `GenerateCourtSlotsCommand` (AOT 30-day horizon), `CourtSlotAvailabilityService` (JIT on-demand).

### `SlotBlockingService`
- **Location**: `app/Services/SlotBlockingService.php`
- **Responsibility**: Venue operator walk-in reservation and maintenance court slot blocking/unblocking. Validates slot ownership, prevents blocking held or booked slots, and clears block reasons on unblock.
- **Consumers**: `OperatorSlotBlockingController`.

### `SlotPricingPropagationService`
- **Location**: `app/Services/SlotPricingPropagationService.php`
- **Responsibility**: Propagates new or modified court pricing rules across future unbooked and unheld `available` slots.
- **Consumers**: `OperatorCourtPricingRuleController`.

### `PricingResolutionService`
- **Location**: `app/Services/PricingResolutionService.php`
- **Responsibility**: Resolves effective hourly price for a court slot by matching date/time against tiered pricing rules (weekday regular, weekday peak, weekend regular, weekend peak), falling back to court `default_hourly_rate`.
- **Consumers**: `SlotGenerationService`, `SlotPricingPropagationService`.

---

## 2. Booking, Concurrency & Checkout

### `BookingConcurrencyService`
- **Location**: `app/Services/BookingConcurrencyService.php`
- **Responsibility**: Orchestrates two-phase anti-double-booking checkout holds. Phase 1 acquires Redis atomic locks (600s TTL) with deterministic numeric ID sorting and fail-fast rollback on contention. Phase 2 locks MySQL rows, creates `Booking` and `BookingItem` records, transitions slots to `held`, and releases Redis locks. Also handles hold release and expired hold cleanup.
- **Consumers**: `CheckoutController`, `PruneExpiredHoldsCommand`.

### `RescheduleService`
- **Location**: `app/Services/RescheduleService.php`
- **Responsibility**: Executes customer self-service rescheduling under strict 11-gate validation (H-1 / 24h advance cutoff, paid status, un-checked-in, single-use, same venue, same sport type, exact price parity, availability). Performs atomic slot swap in a single database transaction.
- **Consumers**: `PlayerRescheduleController`.

---

## 3. Payment & Gateway Integration

### `PaymentReconciliationService`
- **Location**: `app/Services/PaymentReconciliationService.php`
- **Responsibility**: Ingests Midtrans webhook callbacks, validates SHA-512 signatures, and executes idempotent settlement. Pessimistically locks booking and slots (`SELECT ... FOR UPDATE`), transitions booking to `paid` and slots to `booked`, generates digital ticket QR token, and provides manual status inquiry reconciliation.
- **Consumers**: `CheckoutController` (webhook & status inquiry).

### `MidtransGatewayService`
- **Location**: `app/Services/MidtransGatewayService.php`
- **Responsibility**: Gateway adapter communicating with Midtrans Core API. Generates Dynamic QRIS charge payload with synchronized 10-minute expiry and queries transaction status.
- **Consumers**: `PaymentReconciliationService`.

---

## 4. Attendance & Backoffice Operations

### `CheckInVerificationService`
- **Location**: `app/Services/CheckInVerificationService.php`
- **Responsibility**: Verifies player arrival at the venue through a 4-gate validation pipeline (paid booking, matching venue, attendance date window, duplicate prevention). Transitions booking item `check_in_status` to `checked_in`.
- **Consumers**: `OperatorCheckInController`.

### `OperatorTimelineService`
- **Location**: `app/Services/OperatorTimelineService.php`
- **Responsibility**: Generates full daily 06:00–24:00 timeline matrix for venue courts, populating customer reservation details for booked slots and block reasons for blocked slots.
- **Consumers**: `OperatorTimelineController`.

---

## 5. Platform Administration & Tenancy

### `MasterDataCurationService`
- **Location**: `app/Services/MasterDataCurationService.php`
- **Responsibility**: Platform-level Superadmin curation of supported sport types and Indonesian master cities/districts.
- **Consumers**: `AdminSportTypeController`, `AdminCityController`.

### `VenueManagementService`
- **Location**: `app/Services/VenueManagementService.php`
- **Responsibility**: Superadmin onboarding of venues, linking and unlinking venue operator accounts, and managing SaaS subscription lifecycle transitions (`trial`, `active`, `suspended`, `expired`).
- **Consumers**: `AdminVenueController`, `AdminVenueOperatorController`, `AdminVenueSubscriptionController`.
