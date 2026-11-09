# Settle Domain Entities, Status Enums, and Booking State Machine

Type: grilling
Status: resolved
Blocked by: 

## Question

What are the exact domain models (User, Venue, Court, PricingRule, CourtSlot, Booking, Payment, Ticket), their Eloquent relationship mappings, status enums, and transition rules required to support the CourtSlot (`available`, `held`, `booked`, `blocked`) and Booking (`pending`, `paid`, `rescheduled`, `cancelled`, `expired`, `completed`) lifecycles without data anomalies?

## Answer

All architectural questions regarding domain entities, Eloquent relationships, status enums, and state machines are resolved:

1. **User & Roles Strategy**:
   - Single unified `User` model (`users` table) using Laravel standard authenticatable.
   - Roles backed by `UserRole` enum: `player`, `venue_operator`, `superadmin`.
   - `venue_id` nullable foreign key associated specifically with `venue_operator` records.

2. **Entities & Separation of Concerns**:
   - `Venue`: Represents physical sports facilities (`hasMany(Court::class)`, `hasMany(PricingRule::class)`).
   - `Court`: Represents individual playing spaces categorized by sport type (`belongsTo(Venue::class)`, `hasMany(CourtSlot::class)`).
   - `CourtSlot`: Discrete 60-minute interval slots (`belongsTo(Court::class)`, `hasMany(BookingItem::class)`).
   - `Booking`: Unified reservation record containing digital ticket credentials (`booking_code`, cryptographic `qr_token`, `checked_in_at`, `checked_in_by`).
   - `BookingItem`: Relational pivot (`booking_items`) between `Booking` and `CourtSlot`, locking the historical transaction price (`price_locked`) to guarantee immutability against future price rule modifications.
   - `Payment`: Standalone entity (`payments` table, `belongsTo(Booking::class)`) for Midtrans gateway audit logs, holding `transaction_id`, `order_id`, `gross_amount`, `qr_string`, expiry timestamps, and raw webhook payloads.

3. **Status Enums & State Machine**:
   - **`SlotStatus` Backed Enum**:
     - Values: `available`, `held`, `booked`, `blocked`.
     - Transitions:
       - `available` -> `held`: Initiated upon checkout lock (10-minute hold window).
       - `held` -> `available`: Automated release on checkout timeout or explicit abandonment.
       - `held` -> `booked`: Committed atomically upon valid Midtrans settlement webhook.
       - `available` <-> `blocked`: Venue operator manual walk-in or maintenance hold/unblock.
       - `booked` -> `available`: Executed only via atomic slot swap within self-service reschedule.
   - **`BookingStatus` Backed Enum**:
     - Values: `pending`, `paid`, `rescheduled`, `expired`, `completed`, `cancelled`.
     - Transitions:
       - `pending` -> `paid`: Midtrans QRIS webhook settlement confirmed.
       - `pending` -> `expired`: 10-minute payment countdown elapsed without settlement.
       - `paid` -> `rescheduled`: Player executes slot exchange under H-1 cutoff and price parity.
       - `paid` -> `completed`: Player arrival verified at venue or slot duration elapsed.
       - `paid` -> `cancelled`: Emergency manual override by Superadmin/Operator.

