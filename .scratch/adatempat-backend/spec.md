# Status: ready-for-agent

# Specification: adatempat Monolith Backend

## Problem Statement

In Indonesia, sports enthusiasts (Players) face widespread friction when attempting to find and book court venues (badminton, padel, futsal, tennis, mini soccer). Booking processes are fragmented across WhatsApp chats, manual bank transfers, and uncoordinated paper ledgers, resulting in frequent double-bookings, delayed confirmations, and payment disputes.

Simultaneously, Venue Operators manage facilities using manual, error-prone workflows. Without real-time slot synchronization, they risk overbooking during peak operational hours, struggle to collect walk-in revenue accurately, and lack automated tools to enforce booking rules (such as advance notice for schedule changes or digital attendance verification).

Existing venue management solutions either fail to integrate localized payment channels (such as Dynamic QRIS), rely on heavy registration forms that cause high drop-off rates, or lack multi-tenant isolation and robust concurrency controls capable of handling high-demand checkout rushes without race conditions or deadlocks.

## Solution

The adatempat backend is a high-performance, single-tenant-scoped, multi-tenant Laravel 12 monolith application engineered specifically for the Indonesian sports venue ecosystem.

1. **Frictionless Passwordless Authentication**: Players authenticate instantly via single-use, cryptographic magic link emails without passwords or SMS/WA OTP overhead, automatically converting guest checkouts into authenticated accounts without interrupting the checkout journey.
2. **Sub-80ms Real-Time Slot Discovery**: A specialized MySQL 8.0+ covering index and ahead-of-time slot generation engine deliver sub-80ms slot availability responses across dates and sport types.
3. **Two-Phase Anti-Double-Booking Concurrency Engine**: Combines distributed Redis atomic locks (10-minute hold window) with deterministic slot ordering and MySQL pessimistic row-level transaction locks (`SELECT ... FOR UPDATE`), strictly guaranteeing zero double-bookings and zero deadlocks during peak concurrent checkout traffic.
4. **Automated Midtrans Dynamic QRIS Payment & Idempotent Reconciliation**: Generates single-use Dynamic QRIS charges with synchronized 10-minute expiry windows, verifying SHA-512 webhook signatures and executing atomic, idempotent settlement commits.
5. **Cryptographic Digital Tickets & Anti-Fraud Front-Desk Check-in**: Produces unique booking references and cryptographic QR tokens for digital tickets, protected by a four-gate verification engine that prevents cross-venue validation and duplicate check-ins.
6. **Strict H-1 Self-Service Reschedule Policy**: Empowers Players to swap their reserved court slots up to 24 hours prior to play, enforcing price parity and single-use limits atomically.
7. **Multi-Tenant Operator Backoffice & SaaS Subscription Gating**: Restricts Venue Operators to their dedicated venue operations (courts, pricing rules, timeline, walk-in slot blocking, check-in), while Superadmins manage platform master data and SaaS subscription lifecycles.

---

## User Stories

### Authentication & Account Onboarding
1. As a Player, I want to authenticate by entering my email address so that I do not have to create or remember a password.
2. As a Player, I want to receive a single-use login magic link valid for 15 minutes so that my session is established securely.
3. As a Player, I want an account to be created automatically on my first login or checkout attempt so that I experience zero registration friction.
4. As a Player, I want my intended destination (such as an in-progress checkout) preserved upon magic link verification so that I am redirected back without losing my cart context.
5. As a Player, I want expired or reused magic links to display a clear error message with an option to request a new link so that I understand why authentication failed.
6. As a Platform Administrator, I want magic link request endpoints to enforce rate limits (5 attempts per 15 minutes) and 60-second client cooldowns so that malicious actors cannot spam mail delivery services.
7. As a Platform Administrator, I want the authentication initiation endpoint to respond with a generic success response regardless of email existence so that user enumeration attacks are prevented.

### Venue & Court Discovery
8. As a Player, I want to search and filter active sports venues by city and sport type so that I can quickly discover suitable facilities near me.
9. As a Player, I want to view detailed venue profiles including address, operational hours, facilities, and contact details so that I have all necessary venue information before booking.
10. As a Player, I want to view available courts within a venue filtered by sport type so that I can choose my preferred court surface and setup.
11. As a Player, I want only venues with active or trial SaaS subscriptions to appear in search results so that I never reserve slots at suspended or inactive venues.

### Court Slot Scheduling & Tiered Pricing
12. As a Player, I want to browse discrete 60-minute court slots for a specific court and calendar date so that I can plan my games accurately.
13. As a Player, I want court slots to reflect dynamic hourly pricing based on weekday versus weekend and peak versus regular time windows so that I see exact, transparent rates before checkout.
14. As a Player, I want court slot availability queries to execute in under 80 milliseconds so that browsing the venue schedule feels instantaneous.
15. As a Player, I want court slots that are currently in another player's checkout hold to display as unavailable so that I do not attempt to reserve a contested slot.
16. As a Player, I want to be restricted from browsing or booking court slots beyond the venue's configured booking horizon (e.g., 30 days) so that schedules remain reliable.
17. As a Venue Operator, I want discrete 60-minute slots to be generated automatically up to 30 days in advance so that I do not need to manually configure daily calendars.
18. As a Venue Operator, I want ungenerated dates within the booking horizon to populate idempotently on demand when viewed so that new courts or calendar extensions are instantly bookable without gaps.
19. As a Venue Operator, I want to configure tiered pricing rules by day type and time window, backed by a baseline hourly court rate, so that every operational hour has an unambiguous price.
20. As a Venue Operator, I want pricing rule updates to propagate exclusively to future unbooked slots so that existing holds, bookings, and financial audits are never retroactively corrupted.

### Multi-Slot Checkout & Two-Phase Concurrency Engine
21. As a Player, I want to select multiple non-contiguous or contiguous court slots across different courts in a single checkout session so that I can organize multi-court or multi-hour tournaments.
22. As a Player, I want the system to acquire a temporary 10-minute hold on all my selected court slots upon entering checkout so that no other player can take them while I complete payment.
23. As a Player, I want to see a 10-minute countdown timer on the checkout page matching my slot hold duration so that I know exactly how much time I have to pay.
24. As a Player, I want an immediate conflict notice if any of my selected slots become unavailable during checkout initiation so that I can select alternative slots without hanging.
25. As a Player, I want all my held slots to be released immediately back to available if my payment countdown expires without completion so that other players can book them.
26. As a Player, I want to be able to explicitly abandon a checkout session so that my held slots are immediately released for others without waiting for the full timeout.
27. As a Venue Operator, I want the system to sort multi-slot locks deterministically by slot ID so that concurrent checkouts across overlapping slots never trigger deadlocks or race conditions.
28. As a Venue Operator, I want slots with expired holds to be treated as available in real-time queries even before background cleanup workers execute so that booking availability is never artificially blocked.

### Dynamic QRIS Payment & Webhook Settlement
29. As a Player, I want to receive a Dynamic QRIS code generated for the exact booking total so that I can pay using any Indonesian banking or e-wallet application (GoPay, BCA, Mandiri, ShopeePay, OVO).
30. As a Player, I want the Dynamic QRIS code to expire in exactly 10 minutes, synchronized with my slot hold duration, so that expired QR codes cannot be paid after the hold is lost.
31. As a Player, I want the checkout page to poll for payment status every 3 seconds and automatically redirect me to my digital ticket upon successful payment so that I do not have to refresh manually.
32. As a Player, I want a manual "Check Payment Status" button on the checkout screen so that I can trigger an instant gateway status inquiry if the automated poll is delayed.
33. As a Platform Administrator, I want inbound Midtrans webhooks to be authenticated using SHA-512 signature hash verification so that forged payment notifications are rejected with HTTP 401 Unauthorized.
34. As a Platform Administrator, I want webhook settlement processing to execute inside a row-level pessimistic database transaction (`SELECT ... FOR UPDATE`) so that concurrent or duplicate webhook calls never double-credit bookings.
35. As a Platform Administrator, I want duplicate webhook deliveries for already settled bookings to return HTTP 200 OK idempotently without re-triggering state changes or altering audit records.
36. As a Platform Administrator, I want failed, expired, or cancelled payment gateway notifications to automatically transition the booking to cancelled or expired and release all associated court slots.

### Digital Ticket & Cryptographic Attendance Verification
37. As a Player, I want to access a digital ticket displaying my unique booking reference, venue details, court slot schedule, and a cryptographic QR token upon payment completion so that I can present it at the venue.
38. As a Player, I want to view my booking history and digital tickets under my personal profile so that I can easily retrieve past and upcoming reservations.
39. As a Venue Operator, I want to verify player attendance at the front desk by scanning the digital ticket's QR token with a camera or entering the booking code manually so that players are admitted quickly.
40. As a Venue Operator, I want the check-in verification engine to reject digital tickets belonging to a different venue with HTTP 403 Forbidden so that cross-venue ticket misuse is impossible.
41. As a Venue Operator, I want the check-in verification engine to reject unpaid, expired, or cancelled tickets so that unpaid players cannot access courts.
42. As a Venue Operator, I want the check-in verification engine to reject tickets for games scheduled on future or past dates so that entry is restricted to the booked calendar date.
43. As a Venue Operator, I want the check-in verification engine to reject duplicate check-in attempts and display the original check-in timestamp and staff identifier so that fraudulent ticket reuse is prevented.
44. As a Venue Operator, I want valid check-ins to record my user ID and the current timestamp on the booking record so that attendance audits are fully traceable.

### Customer Self-Service Reschedule
45. As a Player, I want to reschedule my paid booking to an alternative available court slot at the same venue directly from my digital ticket view so that I can adapt to unexpected schedule conflicts.
46. As a Player, I want rescheduling to be permitted only if the venue allows it and at least 24 hours (H-1) remain before the earliest booked slot start time so that venue operations are not disrupted last-minute.
47. As a Player, I want rescheduling to enforce exact price parity so that the replacement slot has the exact same hourly price as my original locked price.
48. As a Player, I want each booking to be eligible for rescheduling at most once so that infinite booking postponement is prohibited.
49. As a Venue Operator, I want reschedule operations to execute an atomic slot swap within a single database transaction so that the old slot is freed and the new slot is booked simultaneously without race conditions.

### Venue Operator Backoffice & Operational Controls
50. As a Venue Operator, I want a dedicated backoffice dashboard to manage my assigned venue's courts, pricing rules, schedules, and daily reservation timeline.
51. As a Venue Operator, I want all backoffice routes and actions to be strictly scoped to my assigned venue so that I can never view, edit, or delete another venue's courts or bookings.
52. As a Venue Operator, I want to manually block available court slots for offline walk-in bookings or routine court maintenance with an explanatory note so that the slots are immediately closed to marketplace players.
53. As a Venue Operator, I want the system to reject walk-in block requests on slots that are already booked or currently held in customer checkout so that active checkouts are protected.
54. As a Venue Operator, I want to unblock previously blocked maintenance slots so that they return to available status for marketplace booking.
55. As a Venue Operator, I want to view a real-time daily court calendar showing slot states (available, held, booked, blocked) with customer details and check-in statuses so that front-desk operations run smoothly.

### Multi-Tenant Scoping & SaaS Subscription Lifecycle
56. As a Venue Operator, I want to receive an administrative renewal notice if my venue's SaaS subscription expires or is suspended so that I am informed about account status.
57. As a Venue Operator, I want read-only access to historical bookings and financial records while my venue subscription is suspended so that operational history remains accessible even if new bookings are disabled.
58. As a Venue Operator, I want court creation, pricing changes, and walk-in blocking actions to be blocked while my venue subscription is inactive so that platform subscription terms are enforced.
59. As a Superadmin, I want a centralized administration panel to manage master data (approved sport types, Indonesian cities), onboard new venues, assign venue operator accounts, and oversee venue subscription statuses.
60. As a Superadmin, I want global authorization bypass capabilities across all venue policies so that I can conduct platform audits, investigate customer disputes, and resolve technical emergencies.

### Background Automation & Lifecycle Management
61. As a System Administrator, I want a scheduled command running every minute to prune expired slot holds, transition abandoned bookings to expired, and release lingering Redis lock keys so that database and cache states remain clean.
62. As a System Administrator, I want a daily scheduled command to generate court slots 30 days ahead for all active courts so that forward availability is maintained automatically.
63. As a System Administrator, I want a scheduled command to transition bookings whose slot duration has completely elapsed into completed status so that venue occupancy metrics are accurate.

---

## Implementation Decisions

### Architectural Foundation & Technology Stack
- **Framework & Scope**: Laravel 12 monolith backend-only scope. Backoffice pages utilize Inertia controller responses with structured props; marketplace discovery, checkout, magic link auth, and webhook reconciliation utilize JSON API endpoints.
- **Data Persistence**: MySQL 8.0+ InnoDB with `utf8mb4_unicode_ci` default charset.
- **Cache & Concurrency Layer**: Redis 7 utilized for atomic slot locks (`court_slot:lock:{id}`), temporary magic link nonces, and checkout session coordination.
- **Queue & Scheduled Workers**: Laravel default database queue driver for MVP with Redis queue support configurable via environment; Laravel Scheduler handles periodic background maintenance tasks.
- **Language & Documentation Protocol**: Strict English for all code identifiers, database columns, docstrings, and technical specs; Bahasa Indonesia for discussions with Rahmat.

### Domain Entities & Eloquent Architecture
- **User & Roles**:
  - Unified `User` model backed by `UserRole` string enum: `player`, `venue_operator`, `superadmin`.
  - Field `venue_id` is a nullable foreign key on `users`, populated exclusively for `venue_operator` accounts.
- **Venue**:
  - Represents a physical sports venue facility.
  - Attributes: `name`, `slug` (unique), `city`, `address`, `phone`, `description`, `facilities` (JSON array of strings), `booking_horizon_days` (default 30), `allow_reschedule` (boolean, default true), `is_active` (boolean, default true), `subscription_status` (`trial`, `active`, `suspended`, `expired`), `subscription_expires_at` (nullable datetime).
  - Relationships: `hasMany(Court::class)`, `hasMany(PricingRule::class)`, `hasMany(Booking::class)`, `hasMany(User::class, 'venue_id')`.
  - Traits: `SoftDeletes`.
- **Court**:
  - Represents an individual playing area within a venue.
  - Attributes: `venue_id`, `name`, `sport_type` (`badminton`, `padel`, `futsal`, `tennis`, `mini_soccer`), `opening_time` (TIME, `06:00:00`), `closing_time` (TIME, `23:00:00`), `default_hourly_rate` (unsignedBigInteger IDR), `is_active` (boolean, default true).
  - Relationships: `belongsTo(Venue::class)`, `hasMany(CourtPricingRule::class)`, `hasMany(CourtSlot::class)`.
  - Traits: `SoftDeletes`.
- **CourtPricingRule**:
  - Attributes: `court_id`, `day_type` (`weekday`, `weekend`), `start_time` (TIME), `end_time` (TIME), `price_per_hour` (unsignedBigInteger IDR).
  - Relationships: `belongsTo(Court::class)`.
- **CourtSlot**:
  - Discrete 60-minute time slice.
  - Attributes: `court_id`, `date` (DATE), `start_time` (TIME), `end_time` (TIME), `price` (unsignedBigInteger IDR), `status` (`available`, `held`, `booked`, `blocked`), `locked_until` (nullable datetime), `block_reason` (nullable string).
  - Relationships: `belongsTo(Court::class)`, `hasMany(BookingItem::class)`.
- **Booking**:
  - Core reservation record.
  - Attributes: `user_id`, `venue_id`, `booking_code` (unique, e.g., `ADT-YYYYMMDD-XXXX`), `payment_status` (`pending`, `paid`, `rescheduled`, `cancelled`, `expired`, `completed`), `total_amount` (unsignedBigInteger IDR), `qr_token` (unique 64-character hash), `check_in_status` (`pending`, `checked_in`), `checked_in_at` (nullable datetime), `checked_in_by` (nullable foreign key to `users`), `rescheduled_at` (nullable datetime), `paid_at` (nullable datetime).
  - Relationships: `belongsTo(User::class)`, `belongsTo(Venue::class)`, `belongsTo(User::class, 'checked_in_by')`, `hasMany(BookingItem::class)`, `hasOne(Payment::class)`.
- **BookingItem**:
  - Relational pivot connecting `Booking` and `CourtSlot`.
  - Attributes: `booking_id`, `court_slot_id`, `price_locked` (unsignedBigInteger IDR).
  - Relationships: `belongsTo(Booking::class)`, `belongsTo(CourtSlot::class)`.
- **Payment**:
  - Financial gateway audit record.
  - Attributes: `booking_id`, `order_id` (unique, matches `booking.booking_code`), `transaction_id` (nullable string from Midtrans), `gross_amount` (unsignedBigInteger IDR), `payment_type` (default `qris`), `transaction_status` (string: `pending`, `settlement`, `expire`, `cancel`, `deny`), `qr_string` (nullable text), `qr_url` (nullable text), `expiry_time` (nullable datetime), `raw_payload` (nullable JSON).
  - Relationships: `belongsTo(Booking::class)`.

### Domain State Machines

#### SlotStatus Transition Lifecycle
```
                 ┌────────────────────────────────┐
                 │                                │
                 ▼                                │
          [ available ] ──────────────► [ blocked ]
                 │      (walk-in/maint)   │
   (checkout hold)│                       │ (unblock)
                 ▼                       ▼
              [ held ] ───────────────► [ available ]
                 │      (timeout/abandon)
(webhook settle) │
                 ▼
             [ booked ] ──────────────► [ available ]
                        (reschedule swap)
```
- `available` -> `held`: Transitioned upon checkout hold acquisition (10-minute hold window).
- `held` -> `available`: Transitioned upon payment countdown expiry, explicit checkout abandonment, or scheduled cleanup.
- `held` -> `booked`: Committed atomically upon valid Midtrans settlement webhook.
- `available` <-> `blocked`: Manual operator override for walk-in cash reservations or maintenance.
- `booked` -> `available`: Committed exclusively via atomic slot swap during self-service reschedule.

#### BookingStatus Transition Lifecycle
```
                 ┌─────────────────────────────────┐
                 │                                 │
                 ▼                                 │
            [ pending ] ──────────────► [ expired ]│
                 │      (10m timeout)              │
(webhook settle) │                                 │
                 ▼                                 │
              [ paid ] ───────────────► [ cancelled ] (manual emergency)
                 │      (reschedule)
                 ├────────────────────► [ rescheduled ]
                 │      (check-in / slot elapsed)
                 ▼
            [ completed ]
```
- `pending` -> `paid`: Midtrans QRIS webhook settlement confirmed.
- `pending` -> `expired`: 10-minute payment countdown elapsed without settlement.
- `paid` -> `rescheduled`: Player executes slot exchange under H-1 cutoff and price parity rules.
- `paid` -> `completed`: Player attendance verified via front-desk check-in or slot duration elapsed.
- `paid` -> `cancelled`: Emergency manual override executed by Superadmin or Venue Operator.

### MySQL 8.0+ Schema & Indexing Strategy
- **Currency Representation**: All monetary figures (`price_per_hour`, `price`, `total_amount`, `price_locked`, `gross_amount`) are stored as `unsignedBigInteger` in IDR units. No decimals or floating-point numbers are permitted.
- **Composite Unique Constraints**:
  - `court_slots`: `uq_court_slot (court_id, date, start_time)` prevents generating duplicate slots for the same operational hour.
  - `booking_items`: `uq_booking_slot (booking_id, court_slot_id)` allows released slots to be booked by future bookings without duplicate key collisions on historical records.
  - `bookings`: `uq_bookings_code (booking_code UNIQUE)` and `uq_bookings_qr (qr_token UNIQUE)`.
  - `venues`: `uq_venues_slug (slug UNIQUE)`.
  - `payments`: `uq_payments_order (order_id UNIQUE)`.
- **Covering Index for Sub-80ms Slot Availability**:
  - `idx_slots_availability (court_id, date, status, start_time, price)` on `court_slots`.
  - Enables index-only scans (InnoDB B-tree direct hit) for public slot discovery queries (`WHERE court_id = ? AND date = ? AND status = 'available' ORDER BY start_time ASC`), eliminating clustered index page fetches and guaranteeing sub-80ms execution under high concurrency.
- **Foreign Key Referencing & Deletion Rules**:
  - `courts.venue_id` -> `CASCADE`
  - `court_pricing_rules.court_id` -> `CASCADE`
  - `court_slots.court_id` -> `RESTRICT`
  - `bookings.user_id` & `bookings.venue_id` -> `RESTRICT`
  - `booking_items.booking_id` -> `CASCADE`
  - `booking_items.court_slot_id` -> `RESTRICT`
  - `payments.booking_id` -> `RESTRICT`
  - `users.venue_id` -> `SET NULL`
  - `bookings.checked_in_by` -> `SET NULL`
- **Soft Deletes**:
  - Implemented on `venues` and `courts` tables via `SoftDeletes` (`deleted_at`) alongside `is_active` boolean flags to allow safe archival without breaking historical foreign keys.

### Two-Phase Anti-Double-Booking Concurrency Engine
1. **Multi-Slot Deterministic Ordering**:
   - Requested slot IDs are strictly sorted numerically in ascending order prior to lock acquisition: `sort($slotIds, SORT_NUMERIC)`.
   - Eliminates deadlock cycles when multiple players concurrently attempt to reserve overlapping slot sets (e.g., Player 1 requests [10, 11] while Player 2 requests [11, 10]).
2. **Phase 1: Redis Distributed Hold Lock**:
   - Acquires Redis atomic lock with key pattern `court_slot:lock:{slotId}` with 600-second (10-minute) TTL.
   - If any individual slot fails lock acquisition, all previously acquired locks in the batch are immediately released (fail-fast rollback) and HTTP 409 Conflict is returned.
3. **Database Hold Synchronization**:
   - Upon successful Redis locking, a rapid MySQL transaction updates `court_slots`:
     `status = 'held'`, `locked_until = now()->addSeconds(600)`.
   - Inserts draft `bookings` row (`payment_status = 'pending'`) and corresponding `booking_items`.
   - Allows calendar queries to render "In Checkout" states directly from MySQL without requiring multi-key Redis scans.
4. **Phase 2: Pessimistic Settlement Commit**:
   - Midtrans webhook settlement processing initiates a database transaction.
   - Acquires pessimistic row-level locks: `CourtSlot::whereIn('id', $slotIds)->lockForUpdate()->get()`.
   - Asserts none of the slots have transitioned to `booked` by another transaction.
   - Atomically updates:
     - `court_slots.status = 'booked'`, `court_slots.locked_until = NULL`.
     - `bookings.payment_status = 'paid'`, `bookings.paid_at = now()`.
     - `payments.transaction_status = 'settlement'`.
   - Post-commit, invokes `forceRelease()` on the temporary Redis hold keys.
5. **Dual Expired Hold Cleanup**:
   - **Just-in-Time (JIT)**: Read queries dynamically evaluate any slot with `status = 'held' AND locked_until <= NOW()` as `available`, ensuring zero latency for customer checkout even if background cleanup is pending.
   - **Scheduled Sweep**: Scheduled artisan command `court-slots:prune-expired-holds` runs every minute to transition abandoned bookings to `expired`, reset database slot statuses to `available`, and clean lingering Redis keys.

### Slot Generation & Tiered Pricing Matrix
1. **Discrete 60-Minute Intervals**:
   - Slots operate strictly on discrete hour boundaries between `opening_time` and `closing_time` (e.g., `06:00:00` to `07:00:00`). Fractional offsets (e.g., 30-minute intervals) are prohibited.
2. **Hybrid Generation Strategy**:
   - **Ahead-of-Time (AOT)**: Scheduled artisan command `court-slots:generate-window --days=30` executes daily to maintain a rolling 30-day window of discrete slots for all active courts.
   - **Just-in-Time (JIT)**: When a calendar view is requested for a date within the booking horizon that lacks slot records, `SlotGenerationService` generates slots dynamically using `insertOrIgnore` with unique constraint `(court_id, date, start_time)` to prevent duplicates.
3. **Pricing Resolution Algorithm**:
   - Identifies `day_type`: `weekday` (Monday to Friday) vs. `weekend` (Saturday and Sunday).
   - Queries `court_pricing_rules` matching `court_id`, `day_type`, and time interval (`start_time <= slot_start` AND `end_time >= slot_end`).
   - If no specific rule matches, defaults to `courts.default_hourly_rate` (unsignedBigInteger IDR).
4. **Future Price Propagation**:
   - Modifying pricing rules propagates updates exclusively to unbooked future slots where `status = 'available'` and `(date > CURRENT_DATE OR (date = CURRENT_DATE AND start_time > CURRENT_TIME))`.
   - Held, booked, and blocked slots are immutable.
5. **Booking Horizon Enforcement**:
   - Slots cannot be browsed, generated, or booked beyond `now()->addDays($venue->booking_horizon_days)` (default 30 days).

### Midtrans Dynamic QRIS Payment & Webhook Reconciliation
1. **Core API Dynamic QRIS Charge Payload**:
   - Charge request dispatched to Midtrans Core API `/v2/charge`:
     - `payment_type`: `"qris"`
     - `transaction_details.order_id`: Booking code (e.g., `"ADT-20261115-3921"`)
     - `transaction_details.gross_amount`: Total amount in integer IDR
     - `qris.acquirer`: `"gopay"`
     - `custom_expiry.expiry_duration`: 10
     - `custom_expiry.unit`: `"minute"`
   - Gateway expiry is strictly aligned with the 10-minute Redis slot hold.
   - Returned QR string, QR image URL, and transaction ID are stored in `payments`.
2. **Timing-Safe SHA-512 Signature Hash Verification**:
   - Webhook endpoint `/api/webhooks/midtrans` calculates expected hash:
     `hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey)`.
   - Verifies against inbound `signature_key` using `hash_equals()`.
   - Mismatches immediately abort with HTTP 401 Unauthorized without modifying database state.
3. **Idempotent Webhook Processing**:
   - Acquires row lock: `Booking::where('booking_code', $orderId)->lockForUpdate()->first()`.
   - If booking is already `paid`, logs raw payload to `payments` audit record and returns HTTP 200 OK immediately without re-executing state transitions.
4. **Status Mapping**:
   - `settlement` (or `capture` with `fraud_status: "accept"`): Sets `booking.payment_status = 'paid'`, commits slots to `booked`, releases Redis hold locks.
   - `expire`: Sets `booking.payment_status = 'expired'`, releases slots to `available`, releases Redis hold locks.
   - `cancel` or `deny`: Sets `booking.payment_status = 'cancelled'`, resets slots to `available`, releases Redis hold locks.
5. **Client Real-Time Synchronization**:
   - Checkout frontend short-polls `GET /checkout/{booking_code}/status` every 3 seconds while QR modal is active.
   - Manual "Check Payment Status" action triggers backend inquiry directly against Midtrans Status API (`GET /v2/{order_id}/status`) with signature verification and instant reconciliation.

### Passwordless Magic Link Authentication Flow
1. **Just-in-Time (JIT) Player Onboarding**:
   - Submitting an email on login or checkout (`POST /auth/magic-link`) executes `User::firstOrCreate(['email' => $email], ['name' => Str::before($email, '@'), 'role' => UserRole::Player])`.
2. **Cryptographic Temporary Signed Routes & Nonce Consumption**:
   - Generates 40-character random nonce stored in Redis: `magic_link:token:{userId}:{nonce}` with 15-minute TTL.
   - Constructs signed URL using `URL::temporarySignedRoute('auth.magic-link.verify', now()->addMinutes(15), [...])`.
   - Verification endpoint `/auth/magic-link/verify/{user}` validates signature via `$request->hasValidSignature()` and atomically pulls nonce via `Cache::pull()`. If missing or invalid, rejects with friendly error.
3. **Rate Limiting & Anti-Enumeration**:
   - Rate limiter restricts requests to 5 attempts per 15 minutes per IP + email tuple.
   - Endpoint always returns HTTP 200 OK with a generic message regardless of email existence.
4. **Session Security**:
   - Successful verification invokes `Auth::login($user, remember: true)` followed immediately by `$request->session()->regenerate()` to prevent session fixation.
   - Redirects to `intended` parameter (preserving checkout session).

### Multi-Tenant Scoping & Role-Based Authorization
1. **Operator Backoffice Isolation**:
   - Routes under `/operator/*` guarded by `auth` and `EnsureUserIsVenueOperator` middleware.
   - Laravel Policies (`VenuePolicy`, `CourtPolicy`, `CourtSlotPolicy`, `BookingPolicy`) enforce `$user->venue_id === $model->venue_id`. Cross-venue access returns HTTP 403 Forbidden.
   - Public marketplace queries remain completely unobstructed, searching globally across active venues without global scope side effects.
2. **SaaS Subscription Lifecycle**:
   - Public marketplace searches filter exclusively for venues with `is_active = true` AND `subscription_status IN ('trial', 'active')` AND `(subscription_expires_at IS NULL OR subscription_expires_at > NOW())`.
   - Suspended or expired venues are hidden from search; their slots cannot be held or booked.
   - Operators of suspended venues retain read-only backoffice access to historical records; mutations are blocked by `EnsureVenueSubscriptionActive` middleware.
3. **Superadmin Curation & Platform Bypass**:
   - Platform routes under `/admin/*` guarded by `EnsureUserIsSuperadmin`.
   - `Gate::before` grants Superadmins global bypass across all policies for system audits and operational overrides.
4. **Walk-in Slot Blocking**:
   - Operator can transition an `available` slot on their venue to `status = 'blocked'` with a `block_reason`.
   - If slot is currently `held` or `booked`, returns HTTP 409 Conflict.

### Self-Service Reschedule & Attendance Verification
1. **Strict H-1 / 24-Hour Reschedule Cutoff**:
   - Permitted only if `venues.allow_reschedule === true`.
   - Service verifies advance notice: `now()->diffInHours(Carbon::parse("{$earliestSlot->date} {$earliestSlot->start_time}"), false) >= 24`. Calls under 24 hours return HTTP 422 Unprocessable Entity.
2. **Price Parity & Single-Use Restriction**:
   - Replacement slot must have identical price (`new_slot.price === old_item.price_locked`) on an equivalent court at the same venue.
   - Restricted to 1 reschedule per booking (`bookings.rescheduled_at IS NULL`). Once executed, sets `bookings.rescheduled_at = now()`.
   - Executes atomic slot swap in `DB::transaction()` with `lockForUpdate()`.
3. **Four-Gate Digital Ticket Check-In Verification**:
   - Endpoint `POST /operator/check-in` validates:
     1. **Tenant Matching**: `booking.venue_id === Auth::user()->venue_id`.
     2. **Financial Status**: `booking.payment_status IN ('paid', 'rescheduled')`.
     3. **Operational Date**: Booking slot date matches `today()`.
     4. **Cryptographic Validity**: `qr_token` matches stored hash.
4. **Double Check-In Prevention**:
   - If `check_in_status === 'checked_in'`, returns HTTP 422 Unprocessable Entity with audit details (`checked_in_at`, `staff_name`).
   - On first check-in, sets `check_in_status = 'checked_in'`, `checked_in_at = now()`, `checked_in_by = Auth::id()`.
5. **Automated Completion Lifecycle**:
   - Scheduled command `bookings:mark-completed` periodically marks reservations whose slot end time has passed as `completed`.

---

## Testing Decisions

### What Makes a Good Test
- **External Behavioral Verification**: Tests strictly verify observable external behavior (HTTP response codes, JSON payloads, redirected URLs, database state transitions, and cache mutations) rather than internal implementation details or private method execution.
- **Hermetic Mock Boundaries**: All external network dependencies are mocked using first-party Laravel utilities (`Http::fake()` for Midtrans Core API and `Mail::fake()` for magic link notifications), ensuring tests execute offline without real network overhead or flaky third-party failures.
- **Race Condition & Deadlock Simulation**: High-concurrency tests simulate parallel execution to prove that the two-phase locking engine eliminates race conditions and deadlocks.
- **Idempotency Assertions**: Webhook tests deliver duplicate payloads to verify that state machines remain consistent and do not double-process payments or release locks twice.

### Proposed Test Seams
To maintain a clean and maintainable codebase, testing is structured around three well-defined seams (preferring the highest practical seam):

1. **HTTP / Application Seam (Highest Seam - Primary)**:
   - Full-lifecycle Feature tests exercising HTTP routes, middleware, form request validation, policy authorization, controllers, database transactions, and event dispatches using `RefreshDatabase`.
   - Seam covers:
     - Magic link authentication requests and verification redirects.
     - Public venue and court discovery queries.
     - Slot checkout hold acquisition (verifying 600s TTL and MySQL status updates).
     - Midtrans webhook ingestion and settlement reconciliation.
     - Front-desk QR token check-in verification.
     - Self-service reschedule execution.
     - Multi-tenant operator isolation and policy boundary checks.
2. **Domain Service & Concurrency Seam**:
   - Focused integration tests simulating concurrent transactions against the database and Redis cache.
   - Seam covers:
     - Two concurrent requests attempting to hold the same slot (one succeeds, one receives 409 Conflict).
     - Intersecting multi-slot hold requests (verifying numerical slot sorting eliminates deadlocks).
     - Reschedule atomic slot swap under concurrent slot reservation attempts.
3. **Pure Domain Unit Seam (Lowest Seam)**:
   - Fast, memory-only unit tests for deterministic algorithmic rules.
   - Seam covers:
     - `SlotStatus` and `BookingStatus` backed enum transition guards.
     - Hourly pricing matrix resolution (weekday/weekend and peak/regular interval matching with default rate fallback).
     - Timing-safe SHA-512 signature hash generation and verification.
     - H-1 (24-hour advance) cutoff calculation.
4. **Architecture Governance Seam**:
   - Pest Arch testing (`tests/ArchTest.php`) enforcing:
     - Strict type declarations (`declare(strict_types=1);`).
     - Models extending Eloquent, enums backed by strings.
     - Controllers delegating complex domain logic to service classes.
     - Total absence of debugging artifacts (`dd`, `dump`, `ray`).

### Modules to Be Tested
- `Auth`: Magic link request, rate limiting, temporary signed route verification, session fixation defense.
- `Discovery`: City and sport type search, covering index performance assertion (<80ms), subscription filtering.
- `Pricing & Slot Generation`: Tiered pricing matrix, 30-day AOT generation, JIT fallback, price propagation.
- `Checkout & Locking`: Redis atomic hold, multi-slot sorting, fail-fast rollback, expired hold cleanup (JIT and scheduled).
- `Payment & Webhook`: Midtrans Dynamic QRIS charge payload, SHA-512 signature verification, idempotent settlement commit, client polling inquiry.
- `Check-in & Reschedule`: 4-gate QR check-in, duplicate check-in audit guard, H-1 reschedule policy, atomic slot swap.
- `Multi-Tenancy`: Operator venue isolation, policy denials (HTTP 403), SaaS subscription gating, Superadmin bypass.

### Prior Art & Test Conventions
- Test suite utilizes Pest PHP with `RefreshDatabase`.
- Existing tests in repo: `tests/Feature/HealthcheckTest.php` provides the baseline structure for HTTP feature assertions.

---

## Out of Scope
- Frontend client UI implementation (React 18, Tailwind CSS, Inertia page views).
- Multi-payment channels beyond Midtrans Dynamic QRIS (Credit Cards, Virtual Accounts, Paylater, E-Wallets deep links).
- Cash refunds or bank transfer payout automation for cancellations or price downgrades.
- WhatsApp Business API notifications (Fonnte/Wablas) for MVP.
- Automated SaaS in-app recurring credit card subscription billing.
- AI matchmaking & dynamic surge pricing algorithms.

---

## Further Notes
- **Queue Topology**: For MVP development and testing, Laravel's database queue driver is utilized. Production deployment under peak checkout load may transition to Redis/Horizon via `.env` configuration without altering application domain logic.
- **Operator Audit Trail**: Check-ins, walk-in slot blocking, and manual status overrides record the operator's user ID and timestamp directly on the relevant model (`checked_in_by`, `block_reason`) to satisfy MVP compliance requirements.
