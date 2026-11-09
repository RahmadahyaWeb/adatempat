# Specify Passwordless Magic Link Authentication Flow

Type: grilling
Status: resolved
Blocked by: 01

## Question

How should the passwordless email magic link flow be implemented using Laravel temporary signed routes (`URL::temporarySignedRoute`), single-use token invalidation, rate limiting (5 attempts / 15 mins), and stateful session cookies without SMS/WA OTP?

## Answer

All architectural questions regarding the Passwordless Magic Link Email authentication engine are resolved:

1. **Frictionless Just-in-Time (JIT) User Onboarding**:
   - When a player submits an email on login or checkout (`POST /auth/magic-link`), the system executes `User::firstOrCreate(['email' => $email], ['name' => Str::before($email, '@'), 'role' => UserRole::Player])`.
   - This eliminates friction and drop-off associated with separate multi-step registration forms, instantly enabling guest-to-member checkout flows.

2. **Cryptographic Signed URL & Single-Use Nonce Invalidation**:
   - The system generates a cryptographic 40-character random nonce (`$token = Str::random(40)`).
   - Stores the nonce in Redis with a strict 15-minute TTL: `Cache::put("magic_link:token:{$user->id}:{$token}", true, now()->addMinutes(15))`.
   - Creates a tamper-proof temporary signed route:
     ```php
     $url = URL::temporarySignedRoute(
         'auth.magic-link.verify',
         now()->addMinutes(15),
         ['user' => $user->id, 'token' => $token, 'intended' => $request->input('intended', '/dashboard')]
     );
     ```
   - During verification (`GET /auth/magic-link/verify/{user}`):
     - Validates cryptographic signature: `abort_unless($request->hasValidSignature(), 403, 'Invalid or expired signature.')`.
     - Atomically consumes the nonce: `$valid = Cache::pull("magic_link:token:{$user->id}:{$request->token}");`.
     - If `$valid` is null (already clicked or expired), the system rejects authentication with a user-friendly flash message: *"This login link has already been used or expired. Please request a new one."*

3. **Strict Multi-Tier Rate Limiting**:
   - The link generation endpoint applies a custom rate limiter:
     `RateLimiter::attempt("magic-link:send:{$ip}:{$email}", 5, function () { ... }, 900);`
   - Exceeding 5 attempts within 15 minutes returns HTTP 429 Too Many Requests with a `Retry-After` header.
   - The frontend enforces a 60-second cooldown timer before enabling the "Resend Link" action.

4. **Stateful Session Authentication & Fixation Defense**:
   - Upon successful signature and nonce verification:
     - `Auth::login($user, remember: true)` establishes a stateful, encrypted session cookie (`laravel_session`).
     - `$request->session()->regenerate()` is executed immediately to invalidate pre-auth session IDs and prevent session fixation attacks.
     - The user is redirected to `$request->query('intended', route('dashboard'))`, preserving checkout cart continuity.

5. **Anti-Enumeration & Privacy Guard**:
   - The `POST /auth/magic-link` endpoint invariably responds with HTTP 200 OK and a generic payload:
     `{ "status": "success", "message": "If this email address is valid, a login link has been sent to your inbox." }`
   - This prevents malicious actors from probing database records to enumerate existing users.

