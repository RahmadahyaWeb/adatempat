<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePromotion;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OwnerVenuePromotionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
    }

    public function test_owner_can_create_percentage_promotion_with_timezone_conversion_and_audit_log(): void
    {
        $owner = $this->createOwner();
        [$business, $venue] = $this->createVenue($owner, 'Asia/Makassar');
        $startsAt = CarbonImmutable::now('Asia/Makassar')->addDays(2)->startOfHour();
        $endsAt = $startsAt->addDays(7);

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-promotions', [
                'business' => $business->public_id,
                'venue' => $venue->public_id,
            ])
            ->set('name', 'Promo Pembukaan')
            ->set('code', 'hemat10')
            ->set('discountType', 'percentage')
            ->set('discountValue', '10')
            ->set('maxDiscountAmount', '100000')
            ->set('maxQuota', '25')
            ->set('startsAt', $startsAt->format('Y-m-d\TH:i'))
            ->set('endsAt', $endsAt->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Promosi berhasil dibuat.');

        $promotion = $venue->promotions()->firstOrFail();

        $this->assertSame('HEMAT10', $promotion->code);
        $this->assertSame('owner', $promotion->funded_by);
        $this->assertSame('percentage', $promotion->discount_type);
        $this->assertSame(10, $promotion->discount_value);
        $this->assertSame(100000, $promotion->max_discount_amount);
        $this->assertSame(25, $promotion->max_quota);
        $this->assertSame('active', $promotion->status);
        $this->assertSame($owner->getKey(), $promotion->created_by);
        $this->assertSame($startsAt->utc()->format('Y-m-d H:i:s'), $promotion->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame($endsAt->utc()->format('Y-m-d H:i:s'), $promotion->ends_at->utc()->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => VenuePromotion::class,
            'auditable_id' => $promotion->getKey(),
            'action' => 'venue_promotion.created',
            'actor_user_id' => $owner->getKey(),
        ]);
    }

    public function test_percentage_promotion_requires_a_positive_maximum_discount(): void
    {
        $owner = $this->createOwner();
        [$business, $venue] = $this->createVenue($owner);
        $startsAt = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->startOfHour();
        $endsAt = $startsAt->addDay();

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-promotions', [
                'business' => $business->public_id,
                'venue' => $venue->public_id,
            ])
            ->set('name', 'Promo Tanpa Batas')
            ->set('discountType', 'percentage')
            ->set('discountValue', '10')
            ->set('maxDiscountAmount', '')
            ->set('startsAt', $startsAt->format('Y-m-d\TH:i'))
            ->set('endsAt', $endsAt->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasErrors(['maxDiscountAmount']);

        $this->assertDatabaseCount('venue_promotions', 0);
    }

    public function test_owner_can_pause_and_resume_their_own_promotion(): void
    {
        $owner = $this->createOwner();
        [$business, $venue] = $this->createVenue($owner);
        $promotion = $venue->promotions()->create([
            'business_id' => null,
            'name' => 'Promo Uji Status',
            'funded_by' => VenuePromotion::FUNDED_BY_OWNER,
            'discount_type' => VenuePromotion::DISCOUNT_FIXED,
            'discount_value' => 50000,
            'max_discount_amount' => null,
            'is_combinable_with_owner_promo' => false,
            'max_quota' => 5,
            'used_count' => 0,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDays(3),
            'status' => VenuePromotion::STATUS_ACTIVE,
            'created_by' => $owner->getKey(),
        ]);

        $component = Livewire::actingAs($owner)->test('pages::owner.venue-promotions', [
            'business' => $business->public_id,
            'venue' => $venue->public_id,
        ]);

        $component->call('toggleStatus', $promotion->getKey())->assertHasNoErrors();
        $this->assertDatabaseHas('venue_promotions', ['id' => $promotion->getKey(), 'status' => 'paused']);

        $component->call('toggleStatus', $promotion->getKey())->assertHasNoErrors();
        $this->assertDatabaseHas('venue_promotions', ['id' => $promotion->getKey(), 'status' => 'active']);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => VenuePromotion::class,
            'auditable_id' => $promotion->getKey(),
            'action' => 'venue_promotion.status_changed',
        ]);
    }

    public function test_owner_cannot_manage_a_venue_owned_by_another_user(): void
    {
        $owner = $this->createOwner();
        $otherOwner = $this->createOwner();
        [$business, $venue] = $this->createVenue($otherOwner);

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-promotions', [
                'business' => $business->public_id,
                'venue' => $venue->public_id,
            ])
            ->assertStatus(404);
    }

    private function createOwner(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function createVenue(User $owner, string $timezone = 'Asia/Jakarta'): array
    {
        $business = $owner->businesses()->create([
            'name' => 'Usaha Promo Uji',
            'slug' => 'usaha-promo-' . strtolower((string) Str::ulid()),
            'address' => 'Jalan Contoh Nomor 10',
            'timezone' => $timezone,
            'status' => Business::STATUS_ACTIVE,
            'published_at' => now(),
        ]);
        $category = Category::query()->firstOrFail();
        $venue = $business->venues()->create([
            'category_id' => $category->getKey(),
            'name' => 'Venue Promo Uji',
            'slug' => 'venue-promo-' . strtolower((string) Str::ulid()),
            'status' => Venue::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        return [$business, $venue];
    }
}
