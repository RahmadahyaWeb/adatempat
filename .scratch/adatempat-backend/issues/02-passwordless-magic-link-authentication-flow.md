# 02: Passwordless Magic Link Authentication Flow

**What to build:** End-to-end passwordless email magic link authentication. A player submits their email address to receive a temporary signed URL with an exact 15-minute expiration timestamp. Visiting the link validates the cryptographic signature, invalidates the token against reuse, authenticates the user into an active stateful web session, and redirects to the intended destination. Requests are rate-limited to 5 requests per 15 minutes per IP/email tuple.

**Blocked by:** 01: Baseline Scaffolding and Infrastructure Baseline

**Status:** ready-for-agent

- [ ] Endpoint `POST /auth/magic-link` accepts valid RFC 5322 email and dispatches transactional signed link email.
- [ ] Endpoint `GET /auth/magic-link/verify/{token}` verifies signature, invalidates token, authenticates web session, and redirects.
- [ ] Expired or tampered signed links return HTTP 403 Forbidden with security logging.
- [ ] Request rate limit enforced at 5 attempts per 15 minutes per IP/email.
- [ ] Automated Pest feature tests covering valid login, expired token, tampered token, and rate-limiting.
