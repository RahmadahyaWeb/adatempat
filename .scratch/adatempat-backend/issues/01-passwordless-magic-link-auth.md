# 01: Passwordless Magic Link Authentication Flow

**What to build:**
A frictionless passwordless authentication system using email magic links. Players and Venue Operators can authenticate without passwords or SMS/WA OTP overhead. Submitting an email generates a tamper-proof temporary signed link with a single-use nonce. When clicked, the system verifies the signature and single-use token, regenerates the session to prevent fixation, signs in the user with an encrypted stateful session cookie, and redirects them to their intended destination (such as an in-progress checkout) or default dashboard. The flow includes strict rate limiting (5 requests per 15 minutes), client-side cooldowns (60 seconds), and an anti-enumeration response policy.

**Blocked by:** None (can start immediately)

**Status:** done

- [x] Submitting an email address to the authentication endpoint initiates the magic link flow.
- [x] First-time email submissions automatically create a new Player record just-in-time without separate registration friction.
- [x] Magic links are generated as cryptographic temporary signed routes with a 15-minute expiration window.
- [x] Each magic link embeds a unique cryptographic nonce stored in cache and consumed atomically upon initial verification, preventing replay attacks.
- [x] Accessing an expired, tampered, or already-consumed magic link renders a user-friendly error message advising the user to request a fresh link.
- [x] Successful magic link verification establishes a stateful, authenticated session and immediately regenerates the session ID to defend against session fixation attacks.
- [x] Authenticated users are redirected to their preserved intended destination (preserving checkout continuity) or fallback dashboard.
- [x] Magic link request endpoint enforces a rate limit of at most 5 attempts per 15 minutes per IP and email combination, returning HTTP 429 when exceeded.
- [x] Magic link request endpoint always returns a generic HTTP 200 success response regardless of whether the email previously existed, preventing user enumeration.
- [x] Comprehensive automated test suite validates the full lifecycle (request, mock mail delivery, signed verification, single-use invalidation, rate limiting, and session regeneration).
