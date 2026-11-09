# 02: Venue and Court Management with Operator Scoping

**What to build:**
Multi-tenant venue and court management for Venue Operators. Venue Operators authenticate and access backoffice management routes strictly scoped to their assigned venue. Operators can configure their venue profile and manage courts (specifying sport type, opening and closing operational times, default hourly rates, and active states). Strict route middleware and authorization policies enforce tenant boundaries: operators cannot view, mutate, or manage courts belonging to any other venue (returning HTTP 403 Forbidden). Platform administrators can onboard venues and toggle SaaS subscription states (`trial`, `active`, `suspended`, `expired`). Inactive or suspended venue subscriptions put the operator's backoffice into a read-only state and display an administrative renewal notice.

**Blocked by:** 01: Passwordless Magic Link Authentication Flow

**Status:** ready-for-human

- [x] Venue entity supports physical facility details, unique slug, operational parameters, booking horizon, reschedule toggle, and SaaS subscription status tracking.
- [x] Court entity represents discrete playing areas tied to a venue, categorized by standardized sport type (Badminton, Padel, Futsal, Tennis, Mini Soccer).
- [x] Courts define opening time, closing time, default hourly rate (stored in integer Indonesian Rupiah), and active toggle.
- [x] Soft deletion is enabled on venues and courts, preserving relational integrity with historical records while supporting archival.
- [x] Operator backoffice routes are guarded by authentication and venue operator role middleware, verifying non-null assigned venue associations.
- [x] Authorization policies strictly verify tenant ownership, preventing operators from accessing or mutating venues or courts outside their assignment.
- [x] When a venue's SaaS subscription is expired or suspended, operator mutations (creating courts, updating details) are intercepted and rejected with an administrative renewal message.
- [x] Venue Operators of suspended venues retain read-only access to historical venue and court information.
- [x] Comprehensive automated test suite verifies operator role gating, multi-tenant policy isolation (cross-venue 403 Forbidden), court configuration validation, and subscription status enforcement.
