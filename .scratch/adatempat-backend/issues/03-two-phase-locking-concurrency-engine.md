# Define Two-Phase Anti-Double-Booking Locking Architecture

Type: grilling
Status: open
Blocked by: 02

## Question

How must the two-phase anti-double-booking locking algorithm (Redis atomic lock with 600s TTL + MySQL `SELECT ... FOR UPDATE` pessimistic transaction) coordinate multi-slot checkouts, handle lock contention, and clean up expired holds?
