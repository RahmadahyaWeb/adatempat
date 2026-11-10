<?php

declare(strict_types=1);

use App\Models\MasterCity;
use App\Models\MasterSportType;
use App\Services\MasterDataCurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new MasterDataCurationService;
});

test('getSportTypes filters by active flag and orders by name', function () {
    MasterSportType::query()->delete();

    MasterSportType::create(['name' => 'Tennis', 'slug' => 'tennis', 'is_active' => true]);
    MasterSportType::create(['name' => 'Badminton', 'slug' => 'badminton', 'is_active' => true]);
    MasterSportType::create(['name' => 'Archery', 'slug' => 'archery', 'is_active' => false]);

    $all = $this->service->getSportTypes();
    expect($all->count())->toBe(3)
        ->and($all->first()->name)->toBe('Archery');

    $activeOnly = $this->service->getSportTypes(onlyActive: true);
    expect($activeOnly->count())->toBe(2)
        ->and($activeOnly->pluck('slug')->all())->toBe(['badminton', 'tennis']);
});

test('createSportType auto-generates slug from name when omitted', function () {
    $sportType = $this->service->createSportType([
        'name' => 'Pickle Ball',
    ]);

    expect($sportType->slug)->toBe('pickle_ball')
        ->and($sportType->is_active)->toBeTrue();
});

test('updateSportType and deleteSportType perform expected model mutations', function () {
    $sportType = MasterSportType::create([
        'name' => 'Cricket',
        'slug' => 'cricket',
        'is_active' => true,
    ]);

    $updated = $this->service->updateSportType($sportType, ['is_active' => false]);
    expect($updated->is_active)->toBeFalse();

    $deleted = $this->service->deleteSportType($sportType);
    expect($deleted)->toBeTrue();
    expect(MasterSportType::where('slug', 'cricket')->exists())->toBeFalse();
});

test('getCities filters by active flag and orders by province and name', function () {
    MasterCity::query()->delete();

    MasterCity::create(['name' => 'Surabaya', 'province' => 'Jawa Timur', 'is_active' => true]);
    MasterCity::create(['name' => 'Bandung', 'province' => 'Jawa Barat', 'is_active' => true]);
    MasterCity::create(['name' => 'Cimahi', 'province' => 'Jawa Barat', 'is_active' => false]);

    $activeOnly = $this->service->getCities(onlyActive: true);
    expect($activeOnly->count())->toBe(2)
        ->and($activeOnly->first()->name)->toBe('Bandung');
});

test('createCity, updateCity, and deleteCity perform expected mutations', function () {
    $city = $this->service->createCity([
        'name' => 'Bogor',
        'province' => 'Jawa Barat',
    ]);

    expect($city->name)->toBe('Bogor');

    $updated = $this->service->updateCity($city, ['name' => 'Kota Bogor']);
    expect($updated->name)->toBe('Kota Bogor');

    $deleted = $this->service->deleteCity($city);
    expect($deleted)->toBeTrue();
    expect(MasterCity::where('name', 'Kota Bogor')->exists())->toBeFalse();
});
