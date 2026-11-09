# Define Passwordless Magic Link Authentication and Session Management

Type: grilling
Status: open
Blocked by: 01

## Question

How should the passwordless email magic link flow be designed in Laravel 12 using `URL::temporarySignedRoute`, 15-minute token expiry, rate-limiting (5 req/15 min), single-use invalidation, and stateful web session cookies?
