<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterCity;
use App\Services\MasterDataCurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCityController extends Controller
{
    public function __construct(
        protected MasterDataCurationService $masterDataService,
    ) {}

    /**
     * List all curated Indonesian cities/districts.
     */
    public function index(): JsonResponse
    {
        $cities = $this->masterDataService->getCities();

        return response()->json([
            'status' => 'success',
            'data' => $cities,
        ]);
    }

    /**
     * Add a new Indonesian city to the master data taxonomy.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:master_cities,name'],
            'province' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $city = $this->masterDataService->createCity($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'City added to master data successfully.',
            'data' => $city,
        ], 201);
    }

    /**
     * Update an Indonesian city record in master data.
     */
    public function update(Request $request, MasterCity $city): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('master_cities', 'name')->ignore($city->id)],
            'province' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $updatedCity = $this->masterDataService->updateCity($city, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'City updated successfully.',
            'data' => $updatedCity,
        ]);
    }

    /**
     * Delete an Indonesian city from master data.
     */
    public function destroy(MasterCity $city): JsonResponse
    {
        $this->masterDataService->deleteCity($city);

        return response()->json([
            'status' => 'success',
            'message' => 'City removed from master data successfully.',
        ]);
    }
}
