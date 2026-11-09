# Specify Court Slot Generation and Tiered Pricing Matrix

Type: grilling
Status: resolved
Blocked by: 01, 02

## Question

What deterministic calculation algorithm, operational scheduling models, and pricing resolution rules (weekday vs. weekend, regular vs. peak hours) will generate discrete 60-minute court slots and dynamically resolve hourly prices without manual schedule regeneration?

## Answer

All architectural questions regarding court slot generation, operational scheduling boundaries, and the tiered pricing resolution matrix are resolved:

1. **Hybrid Slot Generation Architecture (Rolling Window AOT + Idempotent JIT Fallback)**:
   - **Rolling Window Ahead-of-Time (AOT)**: A daily scheduled command (`php artisan court-slots:generate-window --days=30`) iterates across all active courts and generates discrete 60-minute slots for the upcoming 30-day window based on each court's operational hours and pricing rules.
   - **Idempotent Just-in-Time (JIT) Fallback**: When a calendar view is requested for a valid date within the booking horizon (e.g. newly onboarded or activated courts), the system dynamically invokes `SlotGenerationService::generateForDate($court, $date)` using `insertOrIgnore` or `firstOrCreate` with composite key `(court_id, date, start_time)`. This guarantees instant availability without duplicates or database deadlocks.

2. **Operational Boundaries & Discrete 60-Minute Intervals**:
   - Each court defines `opening_time` and `closing_time` (format `TIME`, e.g. `06:00:00` to `23:00:00`) on the `courts` table.
   - Slot generation operates in discrete 60-minute increments on the hour (`00:00`). Fractional offsets (e.g., 30-minute intervals or starting at `07:30`) are strictly prohibited and rejected at the domain validation boundary.

3. **Deterministic Pricing Hierarchy & Baseline Fallback**:
   - For every generated slot on date $D$ at time window $[T_{\text{start}}, T_{\text{end}}]$:
     - Classifies `day_type`: `weekday` (Monday-Friday) vs. `weekend` (Saturday-Sunday) via Carbon.
     - Queries `court_pricing_rules` matching `court_id = $court->id`, `day_type = $dayType`, and `start_time <= $slotStartTime AND end_time >= $slotEndTime`.
     - **Baseline Fallback Rate**: If no custom pricing rule covers the slot interval, the system applies `courts.default_hourly_rate` (`unsignedBigInteger`). This ensures every slot has a deterministic non-zero IDR price and eliminates unhandled null pricing states.

4. **Dynamic Price Propagation to Future Unbooked Slots**:
   - When an operator publishes or updates rules in `court_pricing_rules`, the system dispatches `CourtSlotPricingService::propagatePricingUpdate($court)`.
   - The service updates `price` exclusively for unbooked future slots where `status = 'available'` and `date > CURRENT_DATE OR (date = CURRENT_DATE AND start_time > CURRENT_TIME)`.
   - Slots with status `held` (active checkout), `booked` (completed transaction with `booking_items.price_locked`), or `blocked` (walk-in/maintenance) remain completely immutable, preventing checkout price disputes and financial audit discrepancies.

5. **Booking Horizon Policy**:
   - The `venues` table maintains a `booking_horizon_days` column (integer, default 30).
   - Any query, checkout attempt, or slot generation request beyond `now()->addDays($venue->booking_horizon_days)` is rejected at the domain policy layer, shielding operators from long-term operational uncertainty.

