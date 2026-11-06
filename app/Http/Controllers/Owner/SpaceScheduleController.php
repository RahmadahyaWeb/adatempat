<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StoreScheduleRequest;
use App\Models\Schedule;
use App\Models\Space;
use App\Models\SpaceScheduleVersion;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SpaceScheduleController extends Controller
{
    public function index(Request $request, Venue $venue, Space $space): Response
    {
        abort_if($space->venue_id !== $venue->id, 404);
        Gate::authorize('manageSchedules', $space);

        /** @var SpaceScheduleVersion|null $activeVersion */
        $activeVersion = $space->scheduleVersions()
            ->where('effective_from', '<=', today())
            ->where(fn (Builder $query) => $query
                ->whereNull('effective_to')
                ->orWhere('effective_to', '>=', today()))
            ->orderByDesc('version')
            ->with(['schedules' => fn ($query) => $query->orderBy('day_of_week')->orderBy('open_time')])
            ->first();

        if ($activeVersion === null) {
            /** @var SpaceScheduleVersion|null $activeVersion */
            $activeVersion = $space->scheduleVersions()
                ->orderByDesc('version')
                ->with(['schedules' => fn ($query) => $query->orderBy('day_of_week')->orderBy('open_time')])
                ->first();
        }

        $schedules = $activeVersion !== null
            ? $activeVersion->schedules->map(fn (Schedule $schedule): array => [
                'id' => $schedule->id,
                'day_of_week' => $schedule->day_of_week,
                'open_time' => substr((string) $schedule->open_time, 0, 5),
                'close_time' => substr((string) $schedule->close_time, 0, 5),
                'slot_duration_minutes' => (int) $schedule->slot_duration_minutes,
                'is_active' => (bool) $schedule->is_active,
            ])->values()->all()
            : [];

        $hasFutureBookings = $space->bookings()
            ->where('booking_date', '>=', today())
            ->whereNotIn('status', ['cancelled'])
            ->exists();

        return Inertia::render('owner/spaces/schedules', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
                'city' => $venue->city,
                'timezone' => $venue->timezone,
            ],
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'slug' => $space->slug,
                'category' => $space->category->value,
                'category_label' => $space->category->label(),
                'base_price' => (float) $space->base_price,
            ],
            'version' => $activeVersion !== null ? [
                'id' => $activeVersion->id,
                'version' => $activeVersion->version,
                'effective_from' => $activeVersion->effective_from->toDateString(),
                'effective_to' => $activeVersion->effective_to?->toDateString(),
            ] : null,
            'schedules' => $schedules,
            'hasFutureBookings' => $hasFutureBookings,
        ]);
    }

    public function update(StoreScheduleRequest $request, Venue $venue, Space $space): RedirectResponse
    {
        abort_if($space->venue_id !== $venue->id, 404);
        Gate::authorize('manageSchedules', $space);

        /** @var SpaceScheduleVersion|null $activeVersion */
        $activeVersion = $space->scheduleVersions()
            ->where('effective_from', '<=', today())
            ->where(fn (Builder $query) => $query
                ->whereNull('effective_to')
                ->orWhere('effective_to', '>=', today()))
            ->orderByDesc('version')
            ->first();

        $hasFutureBookings = $space->bookings()
            ->where('booking_date', '>=', today())
            ->whereNotIn('status', ['cancelled'])
            ->exists();

        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        DB::transaction(function () use ($space, $request, $activeVersion, $hasFutureBookings, $user): void {
            if ($hasFutureBookings && $activeVersion !== null) {
                $activeVersion->update(['effective_to' => today()]);

                /** @var SpaceScheduleVersion $targetVersion */
                $targetVersion = $space->scheduleVersions()->create([
                    'version' => $activeVersion->version + 1,
                    'effective_from' => today(),
                    'created_by' => $user->id,
                ]);
            } elseif ($activeVersion !== null) {
                $targetVersion = $activeVersion;
                $targetVersion->schedules()->delete();
            } else {
                /** @var SpaceScheduleVersion $targetVersion */
                $targetVersion = $space->scheduleVersions()->create([
                    'version' => 1,
                    'effective_from' => today(),
                    'created_by' => $user->id,
                ]);
            }

            /** @var list<array{day_of_week: int, open_time: string, close_time: string, slot_duration_minutes: int, is_active: bool}> $schedulesData */
            $schedulesData = $request->validated('schedules');

            foreach ($schedulesData as $scheduleData) {
                $targetVersion->schedules()->create([
                    'space_id' => $space->id,
                    'day_of_week' => (int) $scheduleData['day_of_week'],
                    'open_time' => $scheduleData['open_time'],
                    'close_time' => $scheduleData['close_time'],
                    'slot_duration_minutes' => (int) $scheduleData['slot_duration_minutes'],
                    'is_active' => (bool) $scheduleData['is_active'],
                ]);
            }
        });

        return redirect()
            ->route('owner.venues.spaces.schedules.index', [$venue, $space])
            ->with('success', 'Jadwal operasional space berhasil disimpan dan disinkronkan.');
    }
}
