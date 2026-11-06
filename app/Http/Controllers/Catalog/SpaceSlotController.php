<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\VenueApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\Space;
use App\Services\Slots\SlotGeneratorService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class SpaceSlotController extends Controller
{
    public function __construct(
        private readonly SlotGeneratorService $slotGenerator,
    ) {}

    public function index(Space $space, Request $request): JsonResponse
    {
        $space->loadMissing('venue');

        abort_unless(
            $space->is_active
            && $space->venue->is_published
            && $space->venue->approval_status === VenueApprovalStatus::Approved,
            404,
        );

        $timezone = $space->venue->timezone ?? config('app.timezone', 'Asia/Jakarta');
        $defaultDate = CarbonImmutable::now($timezone)->toDateString();

        $date = (string) $request->query('date', $defaultDate);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json([
                'message' => 'Format tanggal harus YYYY-MM-DD.',
            ], 422);
        }

        try {
            $cacheKey = "slots:space:{$space->id}:date:{$date}";

            $result = Cache::remember($cacheKey, 60, function () use ($space, $date): array {
                return $this->slotGenerator->generate($space, $date);
            });

            return response()->json($result);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
