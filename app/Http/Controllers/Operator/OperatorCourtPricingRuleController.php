<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Enums\DayType;
use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\CourtPricingRule;
use App\Services\SlotPricingPropagationService;
use App\Support\TimeHelper;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OperatorCourtPricingRuleController extends Controller
{
    /**
     * List all pricing rules for a court.
     */
    public function index(Court $court): JsonResponse
    {
        Gate::authorize('view', $court);

        return response()->json([
            'status' => 'success',
            'data' => $court->pricingRules()
                ->orderBy('day_type')
                ->orderBy('start_time')
                ->get(),
        ]);
    }

    /**
     * Store a new pricing rule for a court and propagate prices to future available slots.
     */
    public function store(
        Request $request,
        Court $court,
        SlotPricingPropagationService $propagationService
    ): JsonResponse {
        Gate::authorize('update', $court);

        $validated = $request->validate([
            'day_type' => ['required', Rule::enum(DayType::class)],
            'start_time' => [
                'required',
                'date_format:H:i,H:i:s',
                function (string $attribute, mixed $value, Closure $fail) use ($court) {
                    $val = TimeHelper::normalize((string) $value);
                    $opening = TimeHelper::normalize((string) $court->opening_time);
                    $closing = TimeHelper::normalize((string) $court->closing_time);

                    if ($val < $opening || $val >= $closing) {
                        $fail("The {$attribute} must be within court operational hours ({$court->opening_time} - {$court->closing_time}).");
                    }
                },
            ],
            'end_time' => [
                'required',
                'date_format:H:i,H:i:s',
                function (string $attribute, mixed $value, Closure $fail) use ($court, $request) {
                    $val = TimeHelper::normalize((string) $value);
                    $start = $request->input('start_time') ? TimeHelper::normalize((string) $request->input('start_time')) : null;
                    $closing = TimeHelper::normalize((string) $court->closing_time);

                    if ($start !== null && $val <= $start) {
                        $fail("The {$attribute} must be after start time.");
                    }

                    if ($val > $closing) {
                        $fail("The {$attribute} must not exceed court closing time ({$court->closing_time}).");
                    }
                },
            ],
            'price_per_hour' => ['required', 'integer', 'min:0'],
            'tier_name' => ['nullable', 'string', 'max:100'],
        ]);

        $validated['start_time'] = TimeHelper::normalize($validated['start_time']);
        $validated['end_time'] = TimeHelper::normalize($validated['end_time']);

        $pricingRule = $court->pricingRules()->create($validated);

        $propagationService->propagatePricingRules($court);

        return response()->json([
            'status' => 'success',
            'data' => $pricingRule,
        ], 201);
    }

    /**
     * Update an existing pricing rule and propagate changes to future available slots.
     */
    public function update(
        Request $request,
        Court $court,
        CourtPricingRule $pricingRule,
        SlotPricingPropagationService $propagationService
    ): JsonResponse {
        Gate::authorize('update', $court);

        if ($pricingRule->court_id !== $court->id) {
            abort(404, 'Pricing rule not found for this court.');
        }

        $validated = $request->validate([
            'day_type' => ['sometimes', Rule::enum(DayType::class)],
            'start_time' => [
                'sometimes',
                'date_format:H:i,H:i:s',
                function (string $attribute, mixed $value, Closure $fail) use ($court) {
                    $val = TimeHelper::normalize((string) $value);
                    $opening = TimeHelper::normalize((string) $court->opening_time);
                    $closing = TimeHelper::normalize((string) $court->closing_time);

                    if ($val < $opening || $val >= $closing) {
                        $fail("The {$attribute} must be within court operational hours ({$court->opening_time} - {$court->closing_time}).");
                    }
                },
            ],
            'end_time' => [
                'sometimes',
                'date_format:H:i,H:i:s',
                function (string $attribute, mixed $value, Closure $fail) use ($court, $request, $pricingRule) {
                    $val = TimeHelper::normalize((string) $value);
                    $startInput = $request->input('start_time', (string) $pricingRule->start_time);
                    $start = TimeHelper::normalize((string) $startInput);
                    $closing = TimeHelper::normalize((string) $court->closing_time);

                    if ($val <= $start) {
                        $fail("The {$attribute} must be after start time.");
                    }

                    if ($val > $closing) {
                        $fail("The {$attribute} must not exceed court closing time ({$court->closing_time}).");
                    }
                },
            ],
            'price_per_hour' => ['sometimes', 'integer', 'min:0'],
            'tier_name' => ['nullable', 'string', 'max:100'],
        ]);

        if (isset($validated['start_time'])) {
            $validated['start_time'] = TimeHelper::normalize($validated['start_time']);
        }
        if (isset($validated['end_time'])) {
            $validated['end_time'] = TimeHelper::normalize($validated['end_time']);
        }

        $pricingRule->update($validated);

        $propagationService->propagatePricingRules($court);

        return response()->json([
            'status' => 'success',
            'data' => $pricingRule,
        ]);
    }

    /**
     * Delete a pricing rule and propagate changes to future available slots.
     */
    public function destroy(
        Court $court,
        CourtPricingRule $pricingRule,
        SlotPricingPropagationService $propagationService
    ): Response {
        Gate::authorize('update', $court);

        if ($pricingRule->court_id !== $court->id) {
            abort(404, 'Pricing rule not found for this court.');
        }

        $pricingRule->delete();

        $propagationService->propagatePricingRules($court);

        return response()->noContent();
    }
}
