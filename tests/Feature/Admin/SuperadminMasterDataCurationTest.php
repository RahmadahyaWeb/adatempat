<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\MasterCity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->superadmin = User::create([
        'name' => 'Platform Superadmin',
        'email' => 'superadmin@adatempat.com',
        'role' => UserRole::Superadmin,
    ]);

    $this->operator = User::create([
        'name' => 'Regular Operator',
        'email' => 'operator@adatempat.com',
        'role' => UserRole::VenueOperator,
    ]);

    $this->player = User::create([
        'name' => 'Regular Player',
        'email' => 'player@adatempat.com',
        'role' => UserRole::Player,
    ]);
});

test('unauthenticated and non-superadmin users cannot access master data endpoints', function () {
    // Unauthenticated
    $this->getJson('/admin/sports')->assertStatus(401);
    $this->getJson('/admin/cities')->assertStatus(401);

    // Player forbidden
    $this->actingAs($this->player)->getJson('/admin/sports')->assertStatus(403);
    $this->actingAs($this->player)->getJson('/admin/cities')->assertStatus(403);

    // Operator forbidden
    $this->actingAs($this->operator)->getJson('/admin/sports')->assertStatus(403);
    $this->actingAs($this->operator)->getJson('/admin/cities')->assertStatus(403);
});

test('superadmin can list and curate approved sport types', function () {
    // 1. List initial seeded sports
    $listResponse = $this->actingAs($this->superadmin)->getJson('/admin/sports');
    $listResponse->assertStatus(200)
        ->assertJson([
            'status' => 'success',
        ]);

    $sports = $listResponse->json('data');
    expect(count($sports))->toBeGreaterThanOrEqual(5);

    // 2. Create new sport type (Squash)
    $createResponse = $this->actingAs($this->superadmin)->postJson('/admin/sports', [
        'name' => 'Squash',
        'slug' => 'squash',
        'icon' => 'squash-icon.svg',
    ]);

    $createResponse->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'message' => 'Sport type added to master taxonomy successfully.',
            'data' => [
                'name' => 'Squash',
                'slug' => 'squash',
                'icon' => 'squash-icon.svg',
                'is_active' => true,
            ],
        ]);

    $createdSportId = $createResponse->json('data.id');
    $this->assertDatabaseHas('master_sport_types', [
        'id' => $createdSportId,
        'slug' => 'squash',
    ]);

    // 3. Update the sport type
    $updateResponse = $this->actingAs($this->superadmin)->putJson("/admin/sports/{$createdSportId}", [
        'name' => 'Squash Pro',
        'is_active' => false,
    ]);

    $updateResponse->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'name' => 'Squash Pro',
                'is_active' => false,
            ],
        ]);

    // 4. Delete the sport type
    $deleteResponse = $this->actingAs($this->superadmin)->deleteJson("/admin/sports/{$createdSportId}");
    $deleteResponse->assertStatus(200);

    $this->assertDatabaseMissing('master_sport_types', [
        'id' => $createdSportId,
    ]);
});

test('sport type creation auto-generates slug when omitted and enforces unique slug', function () {
    $response = $this->actingAs($this->superadmin)->postJson('/admin/sports', [
        'name' => 'Table Tennis',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'name' => 'Table Tennis',
                'slug' => 'table_tennis',
            ],
        ]);

    // Duplicate slug validation
    $duplicateResponse = $this->actingAs($this->superadmin)->postJson('/admin/sports', [
        'name' => 'Table Tennis Duplicate',
        'slug' => 'table_tennis',
    ]);

    $duplicateResponse->assertStatus(422)
        ->assertJsonValidationErrors(['slug']);
});

test('superadmin can list and curate Indonesian cities and districts', function () {
    // 1. List initial seeded cities
    $listResponse = $this->actingAs($this->superadmin)->getJson('/admin/cities');
    $listResponse->assertStatus(200)
        ->assertJson([
            'status' => 'success',
        ]);

    $cities = $listResponse->json('data');
    expect(count($cities))->toBeGreaterThanOrEqual(10);

    // 2. Add new city (Malang)
    $createResponse = $this->actingAs($this->superadmin)->postJson('/admin/cities', [
        'name' => 'Malang',
        'province' => 'Jawa Timur',
    ]);

    $createResponse->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'message' => 'City added to master data successfully.',
            'data' => [
                'name' => 'Malang',
                'province' => 'Jawa Timur',
                'is_active' => true,
            ],
        ]);

    $createdCityId = $createResponse->json('data.id');
    $this->assertDatabaseHas('master_cities', [
        'id' => $createdCityId,
        'name' => 'Malang',
        'province' => 'Jawa Timur',
    ]);

    // 3. Update city
    $updateResponse = $this->actingAs($this->superadmin)->putJson("/admin/cities/{$createdCityId}", [
        'name' => 'Kota Malang',
        'is_active' => false,
    ]);

    $updateResponse->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'name' => 'Kota Malang',
                'is_active' => false,
            ],
        ]);

    // 4. Delete city
    $deleteResponse = $this->actingAs($this->superadmin)->deleteJson("/admin/cities/{$createdCityId}");
    $deleteResponse->assertStatus(200);

    $this->assertDatabaseMissing('master_cities', [
        'id' => $createdCityId,
    ]);
});

test('city creation enforces required name and unique constraint', function () {
    MasterCity::create([
        'name' => 'Bandung Raya',
        'province' => 'Jawa Barat',
    ]);

    $response = $this->actingAs($this->superadmin)->postJson('/admin/cities', [
        'name' => 'Bandung Raya',
        'province' => 'Jawa Barat',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});
