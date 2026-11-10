<?php

declare(strict_types=1);

use App\Enums\CheckInStatus;
use App\Enums\PaymentStatus;
use App\Enums\SlotStatus;
use App\Enums\SportType;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPlayerTicketFixture(): array
{
    $venue = Venue::create([
        'name' => 'Senayan Gelora',
        'slug' => 'senayan-gelora',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Gelora Bung Karno No. 10',
        'subscription_status' => 'active',
        'phone' => '08123456789',
    ]);

    $court = Court::create([
        'venue_id' => $venue->id,
        'name' => 'Badminton Court 1',
        'sport_type' => SportType::Badminton,
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);

    $slot = CourtSlot::create([
        'court_id' => $court->id,
        'date' => now()->toDateString(),
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'price' => 100000,
        'status' => SlotStatus::Booked,
    ]);

    $player = User::factory()->create([
        'name' => 'Budi Sudarsono',
        'email' => 'budi@example.com',
        'role' => UserRole::Player,
    ]);

    $booking = Booking::create([
        'booking_code' => 'ADT-20261110-TEST',
        'user_id' => $player->id,
        'venue_id' => $venue->id,
        'total_amount' => 100000,
        'payment_status' => PaymentStatus::Paid,
        'check_in_status' => CheckInStatus::Unclaimed,
        'qr_token' => Booking::generateQrToken(),
        'paid_at' => now(),
    ]);

    BookingItem::create([
        'booking_id' => $booking->id,
        'court_slot_id' => $slot->id,
        'price_locked' => 100000,
    ]);

    return compact('venue', 'court', 'slot', 'player', 'booking');
}

test('unauthenticated user cannot access player tickets or bookings', function () {
    $response = $this->getJson('/player/bookings');
    $response->assertStatus(401);

    $responseTicket = $this->getJson('/player/tickets/ADT-20261110-TEST');
    $responseTicket->assertStatus(401);
});

test('authenticated player can view their booking history', function () {
    $data = createPlayerTicketFixture();

    $response = $this->actingAs($data['player'])
        ->getJson('/player/bookings');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data' => [
                'data' => [
                    '*' => [
                        'booking_code',
                        'venue' => ['id', 'name', 'city'],
                        'total_amount',
                        'payment_status',
                        'check_in_status',
                        'created_at',
                        'items',
                    ],
                ],
                'current_page',
                'total',
            ],
        ])
        ->assertJsonFragment([
            'booking_code' => 'ADT-20261110-TEST',
            'total_amount' => 100000,
            'payment_status' => 'paid',
        ]);
});

test('authenticated player can retrieve their digital ticket with qr token', function () {
    $data = createPlayerTicketFixture();
    $booking = $data['booking'];

    $response = $this->actingAs($data['player'])
        ->getJson("/player/tickets/{$booking->booking_code}");

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'booking_code' => $booking->booking_code,
                'qr_token' => $booking->qr_token,
                'payment_status' => 'paid',
                'check_in_status' => 'unclaimed',
                'total_amount' => 100000,
                'venue' => [
                    'id' => $data['venue']->id,
                    'name' => 'Senayan Gelora',
                    'city' => 'Jakarta Pusat',
                    'address' => 'Jl. Gelora Bung Karno No. 10',
                ],
            ],
        ])
        ->assertJsonStructure([
            'data' => [
                'items' => [
                    '*' => [
                        'court_id',
                        'court_name',
                        'sport_type',
                        'date',
                        'start_time',
                        'end_time',
                        'price_locked',
                    ],
                ],
            ],
        ]);

    // Also verify profile alias endpoint
    $this->actingAs($data['player'])
        ->getJson("/profile/tickets/{$booking->booking_code}")
        ->assertStatus(200);
});

test('player cannot view digital ticket belonging to another player (HTTP 403 Forbidden)', function () {
    $data = createPlayerTicketFixture();
    $otherPlayer = User::factory()->create([
        'name' => 'Other Player',
        'email' => 'other@example.com',
        'role' => UserRole::Player,
    ]);

    $response = $this->actingAs($otherPlayer)
        ->getJson("/player/tickets/{$data['booking']->booking_code}");

    $response->assertStatus(403);
});

test('digital ticket conceals qr token when booking is still pending payment', function () {
    $data = createPlayerTicketFixture();
    $data['booking']->update([
        'payment_status' => PaymentStatus::Pending,
        'paid_at' => null,
    ]);

    $response = $this->actingAs($data['player'])
        ->getJson("/player/tickets/{$data['booking']->booking_code}");

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'booking_code' => $data['booking']->booking_code,
                'qr_token' => null,
                'payment_status' => 'pending',
            ],
        ]);
});
