<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterSportType;
use App\Services\MasterDataCurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminSportTypeController extends Controller
{
    public function __construct(
        protected MasterDataCurationService $masterDataService,
    ) {}

    /**
     * List all supported sport types in the master taxonomy.
     */
    public function index(): JsonResponse
    {
        $sportTypes = $this->masterDataService->getSportTypes();

        return response()->json([
            'status' => 'success',
            'data' => $sportTypes,
        ]);
    }

    /**
     * Add a new supported sport type to the master taxonomy.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:master_sport_types,slug'],
            'icon' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $sportType = $this->masterDataService->createSportType($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Sport type added to master taxonomy successfully.',
            'data' => $sportType,
        ], 201);
    }

    /**
     * Update an approved sport type in the master taxonomy.
     */
    public function update(Request $request, MasterSportType $sportType): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('master_sport_types', 'slug')->ignore($sportType->id)],
            'icon' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $updatedSportType = $this->masterDataService->updateSportType($sportType, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Sport type updated successfully.',
            'data' => $updatedSportType,
        ]);
    }

    /**
     * Delete a sport type from the master taxonomy.
     */
    public function destroy(MasterSportType $sportType): JsonResponse
    {
        $this->masterDataService->deleteSportType($sportType);

        return response()->json([
            'status' => 'success',
            'message' => 'Sport type removed from master taxonomy successfully.',
        ]);
    }
}
