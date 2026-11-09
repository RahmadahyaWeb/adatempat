# Establish Multi-Tenant Scoping and Role Authorization

Type: grilling
Status: resolved
Blocked by: 01

## Question

How should multi-tenant isolation be enforced across venue operators so they can only manage their assigned venue's courts, bookings, and timeline, while platform superadmins manage subscriptions and venue onboarding?

## Answer

All architectural questions regarding multi-tenant scoping, role-based authorization, SaaS subscription lifecycle enforcement, and operational controls are resolved:

1. **Multi-Tenant Operator Backoffice Isolation**:
   - Operator endpoints reside under the `/operator/*` route prefix, guarded by `auth` and `EnsureUserIsVenueOperator` middleware.
   - The middleware asserts that the authenticated user possesses `role === UserRole::VenueOperator` and a valid, non-null `venue_id`.
   - Granular authorization is enforced through Laravel Policies (`VenuePolicy`, `CourtPolicy`, `CourtSlotPolicy`, `BookingPolicy`) leveraging route-model scoped binding:
     - All mutations and detailed queries verify `$user->venue_id === $model->venue_id` (or `$user->isSuperadmin()`).
     - Any attempt to access, mutate, or inspect resources belonging to another venue aborts immediately with HTTP 403 Forbidden.
   - Public marketplace queries remain completely unobstructed, searching globally across active venues without global scope side effects.

2. **SaaS Subscription Lifecycle Enforcement**:
   - **Public Marketplace Scope**: Public venue exploration filters exclusively for active and paying facilities:
     `is_active = true` AND `subscription_status IN ('trial', 'active')` AND `(subscription_expires_at IS NULL OR subscription_expires_at > NOW())`.
     Facilities marked `suspended` or `expired` are automatically excluded from search indexes, and their court slots cannot be checked out.
   - **Operator Backoffice Read-Only Mode**: Operators of suspended or expired venues can authenticate to inspect historical reservations, but all state mutations (creating courts, altering pricing, walk-in slot blocking) are intercepted by `EnsureVenueSubscriptionActive` middleware, rendering an administrative renewal notice.

3. **Superadmin Platform Curation & Global Bypass**:
   - Platform administration endpoints reside under `/admin/*`, guarded by `EnsureUserIsSuperadmin` middleware.
   - Superadmins curate master data (approved sports categories, Indonesian cities/districts), onboard new venues, and manage subscription statuses.
   - `Gate::before` grants Superadmins global authorization bypass across all policies for system audits and emergency operational overrides.

4. **Booking & Digital Ticket Access Rules**:
   - **Player Scope**: Players can only view their own reservations (`$booking->user_id === Auth::id()`).
   - **Operator Scope & Audit Trail**: Venue Operators can inspect and check in reservations hosted at their specific facility (`$booking->venue_id === Auth::user()->venue_id`).
   - Verification of check-in records the operator's identifier in `bookings.checked_in_by = Auth::id()` alongside `checked_in_at = now()`.
   - Cross-venue ticket inspection or check-in attempts return HTTP 403 Forbidden.

5. **Walk-in Slot Blocking Authorization**:
   - Operators can transition a slot to `status = 'blocked'` with an optional `block_reason` (e.g. *"Walk-in Cash - Team Garuda"*).
   - The service asserts two conditions:
     1. `$slot->court->venue_id === Auth::user()->venue_id`.
     2. `$slot->status === 'available'`.
   - If the slot is currently `held` by a customer in checkout or already `booked`, the walk-in blocking request is rejected with HTTP 409 Conflict.

