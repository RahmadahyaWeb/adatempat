# 10: Superadmin Platform Curation and Architecture Test Suite

**What to build:**
Centralized platform administration panel endpoints for Superadmins alongside an architecture governance test suite. Superadmins access routes under `/admin/*` guarded by superadmin middleware and benefit from a global policy bypass (`Gate::before`) for platform oversight and audit investigations. Superadmins can curate master data (approved sport types and Indonesian cities/districts), onboard new venues, assign venue operator user accounts, and update venue SaaS subscription statuses (`trial`, `active`, `suspended`, `expired`). In addition, an architecture test suite (`tests/ArchTest.php`) using Pest enforces strict codebase standards: strict type declarations, Eloquent model inheritance, string-backed enums, service layer delegation, and total elimination of debugging statements.

**Blocked by:** 02: Venue and Court Management with Operator Scoping, 09: Operator Walk-in Slot Blocking and Schedule Timeline Control

**Status:** ready-for-agent

- [ ] Superadmin routes under `/admin/*` are protected by authentication and superadmin role middleware.
- [ ] Authorization layer configures a global bypass for Superadmins via `Gate::before`, enabling platform-wide auditing and administrative overrides.
- [ ] Superadmin can curate supported sport types and Indonesian city master data.
- [ ] Superadmin can onboard new venues and link Venue Operator user accounts.
- [ ] Superadmin can transition venue SaaS subscription status between trial, active, suspended, and expired with expiration timestamps.
- [ ] Changes to venue subscription status immediately affect public marketplace search indexing and operator write permissions.
- [ ] Pest architecture test file enforces strict typing (`declare(strict_types=1);`) across all PHP classes.
- [ ] Architecture test verifies that all domain models extend Eloquent Model and that all status enums are string-backed.
- [ ] Architecture test verifies that controllers delegate business logic to dedicated services rather than executing direct transaction logic.
- [ ] Architecture test asserts that debugging statements (`dd`, `dump`, `ray`) are completely absent across the application.
- [ ] Full automated test suite passes end-to-end across all units, features, and architecture rules.
