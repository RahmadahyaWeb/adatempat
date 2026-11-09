# 03: Venue Onboarding and Court Pricing Configuration

**What to build:** Venue operators can register multiple courts under their assigned venue with designated sport categories (badminton, padel, futsal, tennis, mini soccer) and define tiered pricing rules by day classification (weekday vs. weekend) and operational time windows (regular vs. peak). Operators are strictly isolated to their own venue.

**Blocked by:** 02: Passwordless Magic Link Authentication Flow

**Status:** ready-for-agent

- [ ] Venue operator can create and manage multiple courts under their assigned venue.
- [ ] Pricing rules can be registered per court for weekday and weekend day types with specific start and end times.
- [ ] Unique constraints and validation prevent overlapping pricing intervals for the same court and day type.
- [ ] Multi-tenant isolation guarantees venue operators can only view and manage courts belonging to their assigned venue.
- [ ] Automated Pest feature tests covering court creation, tiered pricing rule configuration, overlap rejection, and tenant isolation.
