# Design MySQL 8.0+ Database Schema and Indexing Strategy

Type: grilling
Status: resolved
Blocked by: 01

## Question

How should the relational database schema, data types (MySQL 8.0 InnoDB), composite unique constraints (e.g., `(court_id, date, start_time)`), foreign key `onDelete` rules, and query optimization indexes be structured in Laravel 12 migrations to guarantee <80ms slot availability queries under concurrent load?

## Answer

All architectural questions regarding MySQL 8.0+ InnoDB database schema, column data types, constraints, foreign key referential integrity, and indexing strategies are resolved:

1. **Currency & Monetary Value Representation**:
   - All Indonesian Rupiah (IDR) values (`price_per_hour`, `price`, `total_amount`, `price_locked`, `gross_amount`) are stored as `unsignedBigInteger` (integer Rupiah).
   - This eliminates floating-point precision loss, minimizes storage, matches Midtrans integer payloads (`gross_amount: 300000`), and aligns with Indonesian banking standards (no sub-unit/sen).

2. **Booking Item Constraint Realignment**:
   - Table `booking_items` utilizes a composite unique constraint `(booking_id, court_slot_id)` rather than a single unique column on `court_slot_id`.
   - This preserves complete audit trails for expired, cancelled, or rescheduled bookings without throwing MySQL duplicate key exceptions when a slot is released back to `available` and subsequently booked by another player.
   - Anti-double-booking protection is strictly guaranteed by atomic Redis locks (hold phase) and `court_slots.status` state machine commits (`booked`).

3. **High-Performance Slot Availability Indexing (<80ms Target)**:
   - **Composite Unique Constraint**: `uq_court_slot (court_id, date, start_time)` prevents duplicate slot generation for the same court and operational hour.
   - **Covering Index**: `idx_slots_availability (court_id, date, status, start_time, price)` allows public slot discovery queries (`WHERE court_id = ? AND date = ? AND status = 'available' ORDER BY start_time ASC`) to execute entirely within InnoDB B-Tree index pages (Index-Only Scan), avoiding clustered table lookups and ensuring sub-80ms response times under 1,000 concurrent requests.

4. **Foreign Key Integrity & Deletion Rules**:
   - `courts.venue_id` -> `CASCADE`
   - `court_pricing_rules.court_id` -> `CASCADE`
   - `court_slots.court_id` -> `RESTRICT` (prevents hard deletion of a court with active or historical slots)
   - `bookings.user_id` & `bookings.venue_id` -> `RESTRICT` (guarantees transaction and order audit retention)
   - `booking_items.booking_id` -> `CASCADE` (cleans up items if uncommitted draft booking is purged)
   - `booking_items.court_slot_id` -> `RESTRICT` (prevents deletion of slots tied to bookings)
   - `payments.booking_id` -> `RESTRICT` (financial gateway records are strictly immutable)
   - `users.venue_id` -> `SET NULL` (operator account remains intact if venue is deactivated)
   - `bookings.checked_in_by` -> `SET NULL` (preserves check-in timestamp even if operator account is altered)

5. **Soft Deletes vs. Operational Visibility**:
   - Tables `venues` and `courts` combine `is_active` (boolean flag for instant marketplace filtering) with Laravel `SoftDeletes` (`deleted_at`).
   - This ensures safe archiving of facilities without triggering foreign key constraint violations against historical booking or payment records.

6. **Marketplace Discovery & Backoffice Indexing Matrix**:
   - `venues`: `idx_venues_discovery (is_active, subscription_status, city)` and `idx_venues_slug (slug UNIQUE)`.
   - `courts`: `idx_courts_search (venue_id, sport_type, is_active)`.
   - `court_pricing_rules`: `idx_pricing_lookup (court_id, day_type, start_time, end_time)`.
   - `bookings`: `uq_bookings_code (booking_code UNIQUE)`, `uq_bookings_qr (qr_token UNIQUE)`, `idx_bookings_player (user_id, payment_status, created_at)`, and `idx_bookings_venue_timeline (venue_id, created_at)`.
   - `payments`: `idx_payments_order (order_id)`, `idx_payments_trx (transaction_id)`, and `idx_payments_booking (booking_id)`.

