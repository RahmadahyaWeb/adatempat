<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OwnerBusinessRevisionStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_older_rejection_is_not_shown_after_a_newer_revision_is_approved(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $business = $this->createActiveBusiness($owner);

        $this->createRevision($business, $owner, [
            'proposed_name' => 'Studio Demo Adatempat',
            'status' => BusinessRevision::STATUS_REJECTED,
            'review_reason' => 'gak mau',
            'reviewed_by' => $owner->getKey(),
            'reviewed_at' => now()->subMinute(),
        ]);

        $this->createRevision($business, $owner, [
            'proposed_name' => 'Studio Demo Adatempat tes',
            'proposed_address' => 'Banjarbaru, Kalimantan Selatan',
            'status' => BusinessRevision::STATUS_APPROVED,
            'review_reason' => null,
            'reviewed_by' => $owner->getKey(),
            'reviewed_at' => now(),
        ]);

        Livewire::actingAs($owner)
            ->test('pages::owner.business-edit', ['business' => $business->public_id])
            ->assertSee('Revisi identitas terakhir disetujui')
            ->assertSee('Studio Demo Adatempat tes')
            ->assertDontSee('Revisi identitas terakhir ditolak')
            ->assertDontSee('gak mau');
    }

    public function test_latest_rejection_reason_is_shown_when_the_latest_revision_was_rejected(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $business = $this->createActiveBusiness($owner);

        $this->createRevision($business, $owner, [
            'proposed_name' => 'Nama usulan yang ditolak',
            'status' => BusinessRevision::STATUS_REJECTED,
            'review_reason' => 'Mohon perbaiki nama usaha',
            'reviewed_by' => $owner->getKey(),
            'reviewed_at' => now(),
        ]);

        Livewire::actingAs($owner)
            ->test('pages::owner.business-edit', ['business' => $business->public_id])
            ->assertSee('Revisi identitas terakhir ditolak')
            ->assertSee('Mohon perbaiki nama usaha')
            ->assertDontSee('Revisi identitas terakhir disetujui');
    }

    public function test_pending_revision_takes_priority_over_an_older_rejection(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $business = $this->createActiveBusiness($owner);

        $this->createRevision($business, $owner, [
            'proposed_name' => 'Nama lama yang ditolak',
            'status' => BusinessRevision::STATUS_REJECTED,
            'review_reason' => 'Alamat perlu diperbaiki',
            'reviewed_by' => $owner->getKey(),
            'reviewed_at' => now()->subMinute(),
        ]);

        $this->createRevision($business, $owner, [
            'proposed_name' => 'Nama terbaru yang menunggu',
            'proposed_address' => 'Alamat terbaru',
            'status' => BusinessRevision::STATUS_PENDING,
        ]);

        Livewire::actingAs($owner)
            ->test('pages::owner.business-edit', ['business' => $business->public_id])
            ->assertSee('Revisi identitas menunggu peninjauan')
            ->assertSee('Nama terbaru yang menunggu')
            ->assertDontSee('Revisi identitas terakhir ditolak')
            ->assertDontSee('Alamat perlu diperbaiki');
    }

    private function createActiveBusiness(User $owner): Business
    {
        return Business::query()->create([
            'owner_id' => $owner->getKey(),
            'name' => 'Studio Demo Adatempat tes',
            'slug' => 'studio-demo-adatempat-tes',
            'address' => 'Banjarbaru, Kalimantan Selatan',
            'status' => Business::STATUS_ACTIVE,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createRevision(Business $business, User $owner, array $attributes): BusinessRevision
    {
        return $business->revisions()->create($attributes + [
            'submitted_by' => $owner->getKey(),
            'proposed_name' => 'Usulan nama',
            'proposed_address' => 'Alamat usulan',
        ]);
    }
}
