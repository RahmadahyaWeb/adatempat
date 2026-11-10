<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MasterCity;
use App\Models\MasterSportType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class MasterDataCurationService
{
    /**
     * List supported sport types, optionally filtered by active status.
     *
     * @return Collection<int, MasterSportType>
     */
    public function getSportTypes(?bool $onlyActive = null): Collection
    {
        return MasterSportType::query()
            ->when($onlyActive !== null, fn ($query) => $query->where('is_active', $onlyActive))
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a new approved sport type in the master taxonomy.
     *
     * @param  array<string, mixed>  $data
     */
    public function createSportType(array $data): MasterSportType
    {
        if (empty($data['slug']) && ! empty($data['name'])) {
            $data['slug'] = $this->generateSportSlug((string) $data['name']);
        }

        return MasterSportType::create($data);
    }

    /**
     * Update an existing sport type's taxonomy record.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSportType(MasterSportType $sportType, array $data): MasterSportType
    {
        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = $this->generateSportSlug((string) $data['name']);
        }

        $sportType->update($data);

        return $sportType->fresh();
    }

    /**
     * Remove or deactivate a sport type from the master taxonomy.
     */
    public function deleteSportType(MasterSportType $sportType): bool
    {
        return (bool) $sportType->delete();
    }

    /**
     * List curated Indonesian cities/districts.
     *
     * @return Collection<int, MasterCity>
     */
    public function getCities(?bool $onlyActive = null): Collection
    {
        return MasterCity::query()
            ->when($onlyActive !== null, fn ($query) => $query->where('is_active', $onlyActive))
            ->orderBy('province')
            ->orderBy('name')
            ->get();
    }

    /**
     * Add a new Indonesian city/district to master data.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCity(array $data): MasterCity
    {
        return MasterCity::create($data);
    }

    /**
     * Update an existing Indonesian city master record.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCity(MasterCity $city, array $data): MasterCity
    {
        $city->update($data);

        return $city->fresh();
    }

    /**
     * Delete an Indonesian city from master data.
     */
    public function deleteCity(MasterCity $city): bool
    {
        return (bool) $city->delete();
    }

    /**
     * Generate snake_case slug identifier for sport type.
     */
    private function generateSportSlug(string $name): string
    {
        return Str::slug($name, '_');
    }
}
