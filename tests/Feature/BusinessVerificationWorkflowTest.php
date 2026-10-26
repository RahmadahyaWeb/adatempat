<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessVerificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_submit_complete_business_with_a_draft_venue_for_verification(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER);
        $business = $this->createBusiness($owner);
        $this->createDraftVenue($business);

        Livewire::actingAs($owner)
            ->test('pages::owner.business-edit', ['business' => $business->public_id])
            ->set('name', 'Studio Demo Lengkap')
            ->set('address', 'Jalan Contoh Nomor 12')
            ->set('city', 'Banjarbaru')
            ->set('province', 'Kalimantan Selatan')
            ->set('latitude', '-3.4429')
            ->set('longitude', '114.8355')
            ->call('submitForVerification')
            ->assertHasNoErrors()
            ->assertSee('Usaha berhasil diajukan untuk verifikasi');

        $business->refresh();

        $this->assertSame(Business::STATUS_PENDING_VERIFICATION, $business->status);
        $this->assertSame('Studio Demo Lengkap', $business->name);
        $this->assertSame('Banjarbaru', $business->city);
        $this->assertSame('Kalimantan Selatan', $business->province);
        $this->assertNotNull($business->verification_submitted_at);
        $this->assertNull($business->published_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'business_verification.submitted',
            'auditable_type' => Business::class,
            'auditable_id' => $business->getKey(),
            'actor_user_id' => $owner->getKey(),
        ]);
    }

    public function test_owner_cannot_submit_business_without_a_draft_venue(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER);
        $business = $this->createBusiness($owner);

        Livewire::actingAs($owner)
            ->test('pages::owner.business-edit', ['business' => $business->public_id])
            ->set('name', $business->name)
            ->set('address', $business->address)
            ->set('city', 'Banjarbaru')
            ->set('province', 'Kalimantan Selatan')
            ->set('latitude', '-3.4429')
            ->set('longitude', '114.8355')
            ->call('submitForVerification')
            ->assertHasErrors(['verification']);

        $this->assertDatabaseHas('businesses', [
            'id' => $business->getKey(),
            'status' => Business::STATUS_DRAFT,
            'verification_submitted_at' => null,
        ]);
    }

    public function test_owner_cannot_submit_verification_with_invalid_coordinates(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER);
        $business = $this->createBusiness($owner);
        $this->createDraftVenue($business);

        Livewire::actingAs($owner)
            ->test('pages::owner.business-edit', ['business' => $business->public_id])
            ->set('city', 'Banjarbaru')
            ->set('province', 'Kalimantan Selatan')
            ->set('latitude', '-120')
            ->set('longitude', '114.8355')
            ->call('submitForVerification')
            ->assertHasErrors(['latitude']);

        $this->assertDatabaseHas('businesses', [
            'id' => $business->getKey(),
            'status' => Business::STATUS_DRAFT,
        ]);
    }

    public function test_super_admin_can_approve_verification_without_publishing_before_subscription_is_ready(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER);
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);
        $business = $this->createBusiness($owner, Business::STATUS_PENDING_VERIFICATION);
        $business->forceFill(['verification_submitted_at' => now()])->save();
        $this->createDraftVenue($business);

        Livewire::actingAs($admin)
            ->test('pages::admin.business-verifications')
            ->assertSee('Studio Uji Verifikasi')
            ->call('approve', $business->public_id)
            ->assertHasNoErrors()
            ->assertSee('Usaha berhasil diverifikasi');

        $business->refresh();

        $this->assertSame(Business::STATUS_ACTIVE, $business->status);
        $this->assertNotNull($business->verified_at);
        $this->assertSame($admin->getKey(), $business->verified_by);
        $this->assertNull($business->published_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'business_verification.approved',
            'auditable_type' => Business::class,
            'auditable_id' => $business->getKey(),
            'actor_user_id' => $admin->getKey(),
        ]);
    }

    public function test_super_admin_rejection_requires_a_reason_and_owner_can_see_it(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER);
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);
        $business = $this->createBusiness($owner, Business::STATUS_PENDING_VERIFICATION);
        $business->forceFill(['verification_submitted_at' => now()])->save();
        $this->createDraftVenue($business);

        Livewire::actingAs($admin)
            ->test('pages::admin.business-verifications')
            ->call('beginReject', $business->public_id)
            ->set('reviewReason', '')
            ->call('reject')
            ->assertHasErrors(['reviewReason']);

        Livewire::actingAs($admin)
            ->test('pages::admin.business-verifications')
            ->call('beginReject', $business->public_id)
            ->set('reviewReason', 'Titik lokasi tidak sesuai dengan alamat.')
            ->call('reject')
            ->assertHasNoErrors();

        $business->refresh();

        $this->assertSame(Business::STATUS_REJECTED, $business->status);
        $this->assertSame('Titik lokasi tidak sesuai dengan alamat.', $business->rejection_reason);
        $this->assertNull($business->published_at);

        Livewire::actingAs($owner)
            ->test('pages::owner.business-edit', ['business' => $business->public_id])
            ->assertSee('Usaha perlu diperbaiki')
            ->assertSee('Titik lokasi tidak sesuai dengan alamat.')
            ->assertSee('Ajukan verifikasi');
    }

    private function createUser(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->forceFill([
            'status' => 'active',
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    private function createBusiness(User $owner, string $status = Business::STATUS_DRAFT): Business
    {
        return $owner->businesses()->create([
            'name' => 'Studio Uji Verifikasi',
            'slug' => 'studio-uji-verifikasi-' . Str::lower(Str::random(8)),
            'address' => 'Jalan Contoh Nomor 12',
            'city' => 'Banjarbaru',
            'province' => 'Kalimantan Selatan',
            'latitude' => -3.4429,
            'longitude' => 114.8355,
            'status' => $status,
            'published_at' => null,
        ]);
    }

    private function createDraftVenue(Business $business): Venue
    {
        $category = Category::query()->create([
            'name' => 'Meeting',
            'slug' => 'meeting-' . Str::lower(Str::random(6)),
            'status' => Category::STATUS_ACTIVE,
        ]);

        return $business->venues()->create([
            'category_id' => $category->getKey(),
            'name' => 'Ruang Meeting Uji',
            'slug' => 'ruang-meeting-' . Str::lower(Str::random(6)),
            'description' => 'Venue draft untuk proses verifikasi usaha.',
            'status' => Venue::STATUS_DRAFT,
        ]);
    }
}
