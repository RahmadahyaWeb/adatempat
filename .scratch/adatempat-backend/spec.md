# adatempat Backend Monolith Specification

Status: ready-for-agent

## Problem Statement

Urban sports players in Indonesia face severe friction when reserving sports venues due to opaque court scheduling, slow and manual chat negotiations via messaging apps, and frequent double-booking occurrences. At the same time, sports facility operators lack a unified backend management system to reconcile real-time online reservations with walk-in customers, frequently suffer financial losses from customer no-shows, and are deterred by complex booking platforms. In addition, platform administrators need a reliable multi-tenant structure to onboard venues, manage subscription lifecycles, and curate master sports and city data.

## Solution

A high-performance, robust Laravel 12 monolith backend utilizing MySQL 8.0+ and Redis 7. The backend provides passwordless authentication via Email Magic Links, real-time 60-minute court slot matrices, an anti-double-booking two-tier locking engine (Redis atomic lock with 600s TTL + MySQL pessimistic locking via row-level locks), instant checkout using Midtrans Core API Dynamic QRIS, idempotent payment webhook handling, player self-service rescheduling with strict H-1 (24-hour) advance cutoff and price parity enforcement, daily timeline slot blocking for walk-ins, and dual player check-in verification (manual one-click confirmation and camera QR token validation). The backend exposes structured Inertia responses for web routes and clean JSON responses for checkout and webhook integrations, maintaining full architectural independence from frontend UI presentation.

## User Stories

1. As a Player, I want to request a passwordless login link using my email address, so that I can authenticate securely without remembering passwords or relying on SMS/WhatsApp OTPs.
2. As a Player, I want to click a temporary signed login link in my email, so that I am authenticated into the platform with an active web session and redirected to my intended destination.
3. As a Player, I want expired or tampered login links to be rejected with appropriate error states, so that unauthorized individuals cannot compromise my account.
4. As a Player, I want login link requests to be rate-limited, so that malicious actors cannot spam my inbox or abuse the authentication service.
5. As a Player, I want to filter curated sports facilities by sport category and city, so that I can quickly discover relevant venues near me.
6. As a Player, I want to view active venues and their detailed profiles, so that I can assess location, available amenities, and pricing.
7. As a Player, I want to inspect a date-based 60-minute interval slot matrix for a selected court, so that I can identify available playing hours and their exact hourly prices.
8. As a Player, I want to select multiple consecutive available slots on a court, so that I can book longer playing sessions in a single reservation.
9. As a Player, I want selected slots to be atomically locked for 10 minutes when I initiate checkout, so that other players cannot book them while I am completing payment.
10. As a Player, I want to receive an immediate conflict alert if any of my selected slots are already held or booked, so that I can choose alternative times without ambiguous failures.
11. As a Player, I want a dynamic QRIS payment charge generated for my exact booking total, so that I can pay instantly using any Indonesian banking or e-wallet app.
12. As a Player, I want the held slots to be released automatically if my payment is not completed within the 10-minute hold window, so that inventory is not indefinitely blocked.
13. As a Player, I want to check my payment settlement status on-demand, so that I can verify my booking if payment gateway notifications experience network delay.
14. As a Player, I want to receive a digital booking ticket with a unique booking reference and cryptographic QR verification token once payment settles, so that I have proof of reservation at the facility.
15. As a Player, I want to receive an email confirmation receipt upon successful payment, so that I have an archival record of my transaction and court booking details.
16. As a Player, I want to reschedule a confirmed booking to an alternative available slot at least 24 hours prior to the slot time if the venue allows it, so that I do not forfeit my reservation when personal schedule conflicts arise.
17. As a Player, I want reschedule attempts within 24 hours of the slot start time or attempts with pricing tier discrepancies to be rejected, so that venue booking and revenue policies remain strictly enforced.
18. As a Player, I want my original slots released and new slots claimed atomically when a valid reschedule is executed, so that double-booking or slot leakage never occurs during rescheduling.
19. As a Player, I want to view my upcoming and historical bookings, so that I can easily track my game schedules and access tickets.
20. As a Venue Operator, I want to configure courts under my venue with designated sport categories and operational names, so that our facility capacity is accurately represented online.
21. As a Venue Operator, I want to define tiered pricing rules by day classification (weekday vs. weekend) and operational time windows (regular vs. peak), so that slot prices calculate automatically based on demand.
22. As a Venue Operator, I want newly published pricing rules to propagate immediately to unbooked future court slots, so that our pricing updates take effect without manual schedule regeneration.
23. As a Venue Operator, I want to view a daily timeline matrix across all our venue courts, so that our front desk has real-time visibility into all reservations, walk-ins, and vacant slots.
24. As a Venue Operator, I want to instantly block an open court slot for walk-in players or maintenance with an optional note, so that online marketplace users cannot reserve courts being used on-site.
25. As a Venue Operator, I want to unblock previously blocked slots, so that they immediately become available for online booking if walk-in plans change or maintenance finishes early.
26. As a Venue Operator, I want to manually confirm player check-in with a single action on the daily timeline, so that attendance is quickly recorded during peak front-desk rushes.
27. As a Venue Operator, I want to verify player arrival by scanning their digital ticket QR token using device camera inputs, so that check-in validation is cryptographic, rapid, and tamper-proof.
28. As a Venue Operator, I want the system to reject duplicate check-in attempts on already redeemed tickets with an informative timestamp warning, so that fraudulent double-entry is prevented.
29. As a Venue Operator, I want to toggle customer self-service rescheduling on or off for my facility, so that our venue retains final authority over schedule flexibility.
30. As a Venue Operator, I want all queries, courts, bookings, and pricing rules to be strictly scoped to my assigned venue, so that operational data is fully isolated from other facilities.
31. As a Superadmin, I want to onboard and manage partner venues with geospatial coordinates, imagery references, and owner account assignments, so that only verified venues appear in the platform.
32. As a Superadmin, I want to update SaaS subscription statuses (trial, active, suspended, expired) and expiration dates for venues, so that platform access matches commercial agreements.
33. As a Superadmin, I want venues with suspended or expired subscriptions to be automatically excluded from public marketplace search and slot booking, so that unentitled venues cannot transact.
34. As a Superadmin, I want to manage master taxonomy data for supported sports types and Indonesian cities, so that categorization across the platform remains standardized.

## Implementation Decisions

### 1. Modules and Core Architecture
- **Authentication & Identity Module**: Implements passwordless email magic link flow using temporary signed URLs (`URL::temporarySignedRoute`) with 15-minute expiration, single-use token invalidation, rate limiting (5 attempts per 15 minutes), and standard Laravel stateful session cookies on the `web` middleware group.
- **Venue & Court Configuration Module**: Handles venue profiles, multi-court registration, sport categories taxonomy, and tiered pricing rule definitions across weekdays/weekends and regular/peak timeframes.
- **Scheduling & Slot Generation Module**: Manages 60-minute interval court slot records per court and date, calculates applicable pricing from matching rules, and enforces slot state lifecycle (`available`, `held`, `booked`, `blocked`).
- **Two-Tier Concurrency & Hold Module**: Orchestrates anti-double-booking protection across two distinct phases:
  - *Phase 1 (Checkout Hold)*: Acquires Redis atomic lock (`Cache::lock`) for 600 seconds across all selected slot identifiers. Rejects competing checkout requests with HTTP 409 Conflict.
  - *Phase 2 (Settlement Commit)*: Executes database transaction locking target slot records with pessimistic row-level locking (`SELECT ... FOR UPDATE`), asserting slot state is not already booked, transitioning slot state to `booked` and booking state to `paid`, followed by releasing temporary Redis locks.
  - *Expired Hold Cleanup*: Background job and scheduled cleanup worker releasing expired holds and transitioning stale `pending` bookings to `expired`.
- **Checkout & Midtrans QRIS Integration Module**: Prepares bookings and line items, executes `/v2/charge` requests to Midtrans Core API requesting Dynamic QRIS payloads, stores QR string and expiration timestamps, processes inbound webhooks with SHA-512 signature validation, guarantees idempotent status updates, and provides manual status inquiry fallback.
- **Ticket Issuance & Attendance Verification Module**: Generates unique booking reference numbers and cryptographic verification tokens (`qr_token`), renders digital ticket payloads, dispatches transactional email receipts, and verifies attendance check-ins via single-click manual verification and QR token scanning with duplicate check-in prevention.
- **Reschedule & Policy Engine Module**: Enforces venue-level permission checks (`allow_reschedule`), H-1 (24-hour advance) cutoff validation, price parity verification, and atomic database swapping of previous and newly selected slots.
- **Multi-Tenancy & Authorization Module**: Strictly scopes venue operator queries to their assigned `venue_id` via Eloquent query scopes and controller authorization policies (`CourtPolicy`, `BookingPolicy`), separating venue operator boundaries from player access and platform superadmin controls.

### 2. Interface Contracts
- **Web Navigation Routes**: Deliver structured Inertia responses containing required props (e.g., venue listings, slot availability matrices, backoffice timeline schedules, booking summaries) with minimal view stubs, ensuring immediate frontend integration readiness.
- **API and Webhook Endpoints**: Return standardized JSON responses for checkout creation (`POST /checkout/hold`), payment status inquiries (`GET /bookings/{code}/status`), attendance verification (`POST /backoffice/check-in`), and payment webhooks (`POST /api/webhooks/midtrans`).

### 3. Database Schema and Storage
- Primary relational storage on MySQL 8.0+ using the InnoDB storage engine with strict foreign key constraints and `utf8mb4_unicode_ci` collation.
- Critical composite indexing on:
  - `court_slots(court_id, date, status)` for fast matrix rendering.
  - `court_slots(court_id, date, start_time)` with unique constraint to prevent duplicate slots.
  - `venues(is_active, subscription_status)` for marketplace curation queries.
  - `bookings(booking_code)` and `bookings(qr_token)` for rapid lookup during status checks and check-in scans.

## Testing Decisions

### 1. High-Level Testing Seams
- All feature behavior will be tested at the highest feasible seam: the **HTTP Request/Response Boundary** via Pest PHP Feature Tests.
- Requests are dispatched to real application routes with real middleware pipelines, request validation rules, controller logic, database queries, and cache locks.
- No unit tests asserting internal private methods or implementation details; tests verify observable outcomes (HTTP status codes, response payloads, database state transitions, and dispatched jobs/notifications).

### 2. External Boundary Fakes
- Midtrans Core API HTTP calls are intercepted and mocked using Laravel's native `Http::fake()`, validating request payloads and simulating various payment responses (success, challenge, failure).
- Outgoing transactional emails (Magic Links and Booking Receipts) are tested using `Mail::fake()`, asserting mailable dispatch, recipient address, and signed URL structure without sending real network emails.

### 3. Automated Test Suites
- **Concurrency Test Suite**: Simulates concurrent checkout hold requests on identical court slots, verifying that exactly one request succeeds with HTTP 200/201 and all competing requests receive HTTP 409 Conflict.
- **Webhook Security & Idempotency Suite**: Verifies that Midtrans webhooks with valid SHA-512 signatures transition booking status and slot status atomically, forged signatures are rejected with HTTP 401 Unauthorized, and duplicate webhook submissions return HTTP 200 without duplicate execution.
- **Reschedule Policy Enforcement Suite**: Tests edge cases including reschedule requests made $< 24$ hours before slot start time (rejected), requests to higher/lower price tiers (rejected), and valid reschedule requests (atomically swapping slots).
- **Attendance Verification Suite**: Tests valid QR token check-in, manual timeline check-in, and duplicate check-in attempts (rejected with timestamp warnings).

## Out of Scope

- Client-side frontend UI development (React 18 components, Inertia page components, Tailwind CSS styling).
- Payment channels other than Dynamic QRIS (Credit Cards, Virtual Accounts, Paylater, E-Wallets deep links).
- Cash refunds or bank transfer payout automation.
- Third-party WhatsApp Business API integration (Fonnte/Wablas).
- Automated SaaS recurring in-app subscription billing.
- AI matchmaking and dynamic surge pricing algorithms.

## Further Notes

- Specifications strictly follow domain language codified in [GLOSSARY.md](file:///Users/muhammadachyadirahmat/Developer/adatempat-v2/GLOSSARY.md).
- Aligns directly with requirements defined in [docs/PRD.md](file:///Users/muhammadachyadirahmat/Developer/adatempat-v2/docs/PRD.md).
- Ready for implementation ticket decomposition and test-driven development (TDD) execution.
