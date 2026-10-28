<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerBookingWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_booking_workspace_renders_status_filters_and_empty_state(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        Livewire::actingAs($customer)
            ->test('pages::customer.bookings')
            ->assertSee('Booking saya')
            ->assertSee('Aktif')
            ->assertSee('Selesai')
            ->assertSee('Dibatalkan')
            ->assertSee('Semua')
            ->assertSee('Belum ada booking di sini');
    }

    public function test_customer_can_switch_booking_workspace_filter(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        Livewire::actingAs($customer)
            ->test('pages::customer.bookings')
            ->assertSet('bookingFilter', 'active')
            ->call('setBookingFilter', 'completed')
            ->assertSet('bookingFilter', 'completed')
            ->call('setBookingFilter', 'cancelled')
            ->assertSet('bookingFilter', 'cancelled')
            ->call('setBookingFilter', 'all')
            ->assertSet('bookingFilter', 'all');
    }

    public function test_invalid_booking_filter_is_ignored(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        Livewire::actingAs($customer)
            ->test('pages::customer.bookings')
            ->call('setBookingFilter', 'invalid')
            ->assertSet('bookingFilter', 'active');
    }
}
