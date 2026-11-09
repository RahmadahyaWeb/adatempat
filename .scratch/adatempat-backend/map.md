## Destination

A fully resolved technical architecture, domain specification, MySQL 8.0+ schema, and test blueprint for the adatempat Laravel 12 monolith backend, ready for implementation without architectural ambiguities or unvetted trade-offs.

## Notes

- **Domain**: Indonesian sports venue marketplace & SaaS Backoffice management ([GLOSSARY.md](file:///Users/muhammadachyadirahmat/Developer/adatempat-v2/GLOSSARY.md), [PRD.md](file:///Users/muhammadachyadirahmat/Developer/adatempat-v2/docs/PRD.md)).
- **Primary Skills**: `wayfinder`, `domain-modeling`, `codebase-design`, `tdd`.
- **Standing Preferences**:
  - Architecture: Laravel 12 monolith backend-only scope (Inertia controller responses with structured props + JSON responses for webhooks/checkout).
  - Database & Cache: MySQL 8.0+ InnoDB (`utf8mb4_unicode_ci`) + Redis 7 for atomic slot locks.
  - Authentication: Passwordless Magic Link email via Laravel stateful session cookies and temporary signed routes.
  - Testing: Pest PHP comprehensive automated test suite (Unit & Feature tests).
  - Technical docs & code: English; user discussions: Bahasa Indonesia.

## Decisions so far

<!-- the index: one line per closed ticket, enough to judge relevance, then zoom the link for the detail the ticket holds -->

## Not yet specified

- Worker queue topology and supervisor configuration (database queue vs Redis Horizon) under concurrent load.
- Midtrans mock testing harness behavior for offline/CI environment without external network calls.
- Audit trail logging format for backoffice operator actions (walk-in slot blocking, check-ins, manual status overrides).

## Out of scope

- Frontend UI development (React 18, Inertia page views, Tailwind CSS styling, client components).
- Multi-payment channels beyond Dynamic QRIS (Credit Cards, Virtual Accounts, Paylater, E-Wallets deep links).
- Cash refunds or bank transfer payout automation.
- WhatsApp Business API notifications (Fonnte/Wablas) for MVP.
- Automated SaaS in-app recurring subscription billing.
- AI matchmaking & dynamic surge pricing algorithms.
