# 10: Superadmin Venue Curation and Subscription Enforcement

**What to build:** Superadmins can curate partner venues, configure geospatial coordinates, upload imagery references, assign venue owner accounts, and manage SaaS subscription statuses (`trial`, `active`, `suspended`, `expired`) with expiration timestamps. Venues marked `suspended` or `expired` are automatically excluded from marketplace searches and slot bookings.

**Blocked by:** 03: Venue Onboarding and Court Pricing Configuration, 08: Operator Daily Timeline and Walk-in Slot Blocking

**Status:** ready-for-agent

- [ ] Superadmin can create and update venue profiles with address, coordinates, and owner user association.
- [ ] Superadmin can update venue subscription status and `subscription_expires_at` timestamp.
- [ ] Marketplace venue search and slot availability endpoints filter out venues whose status is not active or subscription is expired/suspended.
- [ ] Superadmin manages master taxonomy of sports categories and Indonesian cities/districts.
- [ ] Automated Pest feature tests covering superadmin onboarding, subscription status updates, marketplace exclusion, and taxonomy management.
