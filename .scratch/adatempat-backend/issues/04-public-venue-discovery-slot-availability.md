# 04: Public Venue Discovery and Slot Availability API

**What to build:**
Public marketplace discovery and high-performance court slot availability query APIs for Players. Players can search and filter active sports venues by city and sport type, view rich venue profile details, and retrieve real-time discrete 60-minute court slot availability for selected dates. Availability queries leverage the MySQL covering index to achieve sub-80ms response times. Venues with suspended or expired SaaS subscriptions are automatically filtered out from marketplace discovery. Slots currently held in checkout or blocked for maintenance are accurately reflected so that players only see bookable times.

**Blocked by:** 03: Tiered Pricing Matrix and Hybrid Slot Generation Engine

**Status:** ready-for-human

- [x] Public API endpoint allows Players to discover active venues filtered by city and supported sport types.
- [x] Public API endpoint returns full venue profile details including facilities, operational hours, address, and court listings.
- [x] Venues with inactive, suspended, or expired SaaS subscription statuses are automatically excluded from public discovery and slot browsing.
- [x] Public court slot availability endpoint returns discrete 60-minute slots for a given court and calendar date, ordered chronologically.
- [x] Public availability queries utilize the specialized covering index to execute as index-only scans with sub-80ms target response times.
- [x] Slots currently in checkout hold (where status is held and the hold duration has not elapsed) are marked as unavailable for selection.
- [x] Just-in-time slot generation is triggered transparently when a player queries a valid calendar date that has not yet been generated ahead-of-time.
- [x] Date queries exceeding the venue's booking horizon days return a clear validation error.
- [x] Automated test suite verifies filtering by city and sport, exclusion of suspended venues, covering index execution, sub-80ms performance assertion, and accurate slot status reporting.
