<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicVenueDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_exposes_advanced_discovery_controls(): void
    {
        Livewire::test('pages::home')
            ->assertSee('Filter lainnya')
            ->assertSee('Harga minimum')
            ->assertSee('Kapasitas minimum')
            ->assertSee('Fasilitas')
            ->assertSee('Tanggal')
            ->assertSee('Urutkan hasil');
    }

    public function test_discovery_filter_validation_rejects_incomplete_schedule_and_invalid_ranges(): void
    {
        Livewire::test('pages::home')
            ->set('minPrice', '500000')
            ->set('maxPrice', '100000')
            ->set('minCapacity', '0')
            ->set('availabilityDate', now('Asia/Jakarta')->addDay()->format('Y-m-d'))
            ->call('applyDiscoveryFilters')
            ->assertHasErrors(['maxPrice', 'minCapacity', 'availabilityDate']);
    }

    public function test_valid_facility_filter_and_sort_can_be_applied(): void
    {
        Livewire::test('pages::home')
            ->set('selectedFacilities', ['Wi-Fi'])
            ->set('sort', 'price_low')
            ->call('applyDiscoveryFilters')
            ->assertHasNoErrors()
            ->assertSet('hasSearched', true)
            ->assertSet('sort', 'price_low');
    }

    public function test_reset_search_clears_all_discovery_state(): void
    {
        Livewire::test('pages::home')
            ->set('keyword', 'meeting')
            ->set('location', 'Banjarbaru')
            ->set('minPrice', '100000')
            ->set('maxPrice', '500000')
            ->set('minCapacity', '20')
            ->set('selectedFacilities', ['AC', 'Parkir'])
            ->set('sort', 'capacity_high')
            ->call('resetSearch')
            ->assertSet('keyword', '')
            ->assertSet('location', '')
            ->assertSet('minPrice', '')
            ->assertSet('maxPrice', '')
            ->assertSet('minCapacity', '')
            ->assertSet('selectedFacilities', [])
            ->assertSet('sort', 'newest')
            ->assertSet('hasSearched', false);
    }
}
