<?php

namespace Tests\Feature\Slots;

use App\Exceptions\SlotConflictException;
use App\Services\Slots\SlotReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SlotReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserves_canonical_cells(): void
    {
        (new SlotReservationService)->reserve(1, 10, '2026-11-06', [
            ['start_time' => '09:00', 'end_time' => '10:00', 'price' => 100000],
            ['start_time' => '10:00', 'end_time' => '11:00', 'price' => 100000],
        ]);

        $this->assertDatabaseCount('booking_slots', 2);
        $this->assertDatabaseHas('booking_slots', [
            'booking_id' => 1,
            'space_id' => 10,
            'slot_date' => '2026-11-06',
            'start_time' => '09:00:00',
        ]);
    }

    public function test_conflict_rolls_back_the_whole_booking(): void
    {
        $service = new SlotReservationService;

        $service->reserve(1, 10, '2026-11-06', [
            ['start_time' => '09:00', 'end_time' => '10:00'],
        ]);

        try {
            $service->reserve(2, 10, '2026-11-06', [
                ['start_time' => '10:00', 'end_time' => '11:00'],
                ['start_time' => '09:00', 'end_time' => '10:00'],
            ]);

            $this->fail('Reservation should have conflicted.');
        } catch (SlotConflictException) {
            // Expected.
        }

        $this->assertDatabaseMissing('booking_slots', ['booking_id' => 2]);
        $this->assertDatabaseCount('booking_slots', 1);
    }

    public function test_same_time_on_another_date_does_not_conflict(): void
    {
        $service = new SlotReservationService;

        $service->reserve(1, 10, '2026-11-06', [
            ['start_time' => '09:00', 'end_time' => '10:00'],
        ]);

        $service->reserve(2, 10, '2026-11-07', [
            ['start_time' => '09:00', 'end_time' => '10:00'],
        ]);

        $this->assertDatabaseCount('booking_slots', 2);
    }

    public function test_rejects_invalid_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SlotReservationService)->reserve(1, 10, '2026-11-06', []);
    }
}
