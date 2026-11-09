# adatempat Domain Glossary

adatempat is a curated sports venue marketplace and B2B SaaS management platform in Indonesia, facilitating real-time court reservations and venue operations.

## Roles & Actors

**Player**:
An end-user who discovers venues, reserves court slots, and attends sports sessions.
_Avoid_: Customer, user, client, athlete

**Venue Operator**:
A facility owner, manager, or receptionist responsible for court configuration, pricing, walk-in blocking, and player check-in.
_Avoid_: Merchant, host, vendor, partner, admin

**Superadmin**:
An adatempat platform operator who curates master data, onboards venues, and oversees SaaS subscriptions.
_Avoid_: Administrator, root, owner

## Venue & Court Management

**Venue**:
A physical sports facility operating one or more courts under single management.
_Avoid_: Facility, location, place, club

**Court**:
A specific designated playing area within a venue dedicated to a particular sport type.
_Avoid_: Field, pitch, arena

**Sport Type**:
A standardized sport category supported by the platform (Badminton, Padel, Futsal, Tennis, Mini Soccer).
_Avoid_: Sport category, game, activity

**Pricing Rule**:
A rule defining hourly court rental pricing based on day type (weekday vs. weekend) and time window (regular vs. peak).
_Avoid_: Rate, price schedule, tariff

## Scheduling & Concurrency

**Court Slot**:
A discrete 60-minute operational time unit for a court on a specific date.
_Avoid_: Time slot, session, hour, schedule item

**Slot Status**:
The lifecycle state of a court slot: `available` (open for booking), `held` (temporary Redis lock during checkout), `booked` (confirmed payment), or `blocked` (reserved by operator for walk-in/maintenance).
_Avoid_: Slot availability, condition

**Hold**:
A temporary atomic reservation of one or more court slots for 10 minutes (TTL 600s) during checkout.
_Avoid_: Lock, cart reservation, temporary booking

## Booking & Payment

**Booking**:
A customer's reservation record containing one or more booking items, payment status, and verification credentials.
_Avoid_: Order, reservation, transaction

**Booking Item**:
A specific court slot claimed under a booking at a locked price.
_Avoid_: Order line, reservation item, slot item

**Dynamic QRIS**:
A single-use QR Code Indonesian Standard payment generated dynamically via Midtrans for the exact booking total.
_Avoid_: QR code, payment code, Midtrans charge

**Payment Status**:
The transactional lifecycle state of a booking: `pending`, `paid`, `expired`, or `rescheduled`.
_Avoid_: Order status, bill status

**Digital Ticket**:
A digital entry pass containing a unique booking reference and cryptographic QR verification token.
_Avoid_: Receipt, pass, voucher

## Operations & Policies

**Check-in**:
The physical arrival verification of a player at a venue by an operator via manual click or QR token camera scan.
_Avoid_: Attendance, redemption, admission

**Reschedule**:
A player self-service action moving a paid booking to an alternative available slot under strict 24-hour advance notice and price parity rules.
_Avoid_: Booking change, postponement, reschedule request
