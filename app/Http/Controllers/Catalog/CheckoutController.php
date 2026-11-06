<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\VenueApprovalStatus;
use App\Exceptions\SlotConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bookings\StoreCheckoutRequest;
use App\Models\Space;
use App\Services\Bookings\BookingService;
use App\Services\Slots\SlotGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CheckoutController extends Controller
{
    public function __construct(
        protected SlotGeneratorService $slotGenerator,
        protected BookingService $bookingService,
    ) {}

    /**
     * Show the checkout page for a selected space, date, and slots.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $spaceSlug = (string) $request->query('space', '');
        $date = (string) $request->query('date', '');
        $slotsInput = $request->query('slots');

        if ($spaceSlug === '' || $date === '' || empty($slotsInput)) {
            return redirect()->route('spaces.index');
        }

        /** @var Space|null $space */
        $space = Space::query()
            ->with([
                'venue',
                'addOns' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
            ])
            ->where('slug', $spaceSlug)
            ->where('is_active', true)
            ->first();

        if (! $space || ! $space->venue || $space->venue->approval_status !== VenueApprovalStatus::Approved) {
            abort(404, 'Lapangan atau venue tidak ditemukan.');
        }

        // Parse slots list
        $slotTimes = is_array($slotsInput)
            ? array_filter($slotsInput, fn ($s) => is_string($s) && $s !== '')
            : array_filter(explode(',', (string) $slotsInput), fn ($s) => $s !== '');

        if ($slotTimes === []) {
            return redirect()->route('spaces.show', ['space' => $space->slug]);
        }

        // Generate and validate slots
        try {
            $generated = $this->slotGenerator->generate($space, $date);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('spaces.show', ['space' => $space->slug])
                ->with('error', $e->getMessage());
        }

        // Build lookup
        $slotsByTime = [];
        foreach ($generated['slots'] as $slot) {
            $timeKey = substr($slot['start_time'], 0, 5);
            $slotsByTime[$timeKey] = $slot;
        }

        $chosenSlots = [];
        foreach ($slotTimes as $time) {
            $key = substr(trim($time), 0, 5);
            if (! isset($slotsByTime[$key]) || $slotsByTime[$key]['status'] !== 'available') {
                return redirect()
                    ->route('spaces.show', ['space' => $space->slug, 'date' => $date])
                    ->with('error', "Slot jam {$key} sudah tidak tersedia. Silakan pilih slot lain.");
            }

            $chosenSlots[] = [
                'start_time' => $slotsByTime[$key]['start_time'],
                'end_time' => $slotsByTime[$key]['end_time'],
                'price' => (int) $slotsByTime[$key]['price'],
            ];
        }

        // Initial pricing calculation
        $pricing = $this->bookingService->calculatePricing(
            space: $space,
            slotTimes: $slotTimes,
            addOnSelections: [],
            availableSlots: $generated['slots'],
        );

        $user = $request->user();

        return Inertia::render('public/checkout', [
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'slug' => $space->slug,
                'category' => $space->category->value,
                'category_label' => $space->category->label(),
                'base_price' => (float) $space->base_price,
            ],
            'venue' => [
                'id' => $space->venue->id,
                'name' => $space->venue->name,
                'slug' => $space->venue->slug,
                'city' => $space->venue->city,
                'address' => $space->venue->address,
                'timezone' => $space->venue->timezone,
                'contact_phone' => $space->venue->contact_phone,
            ],
            'date' => $date,
            'slots' => $chosenSlots,
            'addOns' => $space->addOns->map(fn ($addOn) => [
                'id' => $addOn->id,
                'name' => $addOn->name,
                'price' => (float) $addOn->price,
                'pricing_type' => $addOn->pricing_type->value,
                'pricing_type_label' => $addOn->pricing_type->label(),
                'stock_quantity' => $addOn->stock_quantity,
            ])->values(),
            'customer' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => '',
            ],
            'cancellationPolicy' => [
                'policy' => $space->cancellation_policy->value,
                'policy_label' => $space->cancellation_policy->label(),
                'cutoff_hours' => $space->cancellation_cutoff_hours,
                'refund_percentage' => (float) $space->refund_percentage,
            ],
            'initialPricing' => $pricing,
        ]);
    }

    /**
     * Store a newly created booking with pending_payment status.
     */
    public function store(StoreCheckoutRequest $request): RedirectResponse
    {
        /** @var Space $space */
        $space = Space::query()
            ->with('venue')
            ->where('id', $request->validated('space_id'))
            ->where('is_active', true)
            ->firstOrFail();

        $user = $request->user();
        $date = (string) $request->validated('date');
        $slotTimes = (array) $request->validated('slots');
        $addOnSelections = (array) $request->validated('add_ons', []);
        $customerData = [
            'customer_name' => (string) $request->validated('customer_name'),
            'customer_phone' => (string) $request->validated('customer_phone'),
            'customer_email' => (string) $request->validated('customer_email'),
            'notes' => $request->validated('notes'),
        ];
        // Expire past-due holds on-the-fly before attempting reservation
        $this->bookingService->expirePendingBookings(now(), $space->id, $date);

        try {
            $booking = $this->bookingService->createBooking(
                user: $user,
                space: $space,
                date: $date,
                slotTimes: $slotTimes,
                addOnSelections: $addOnSelections,
                customerData: $customerData,
            );
        } catch (SlotConflictException $e) {
            return back()
                ->withErrors([
                    'slots' => 'Slot waktu yang dipilih baru saja dipesan oleh pengguna lain. Silakan kembali dan pilih slot lain.',
                ])
                ->withInput();
        } catch (InvalidArgumentException $e) {
            return back()
                ->withErrors([
                    'error' => $e->getMessage(),
                ])
                ->withInput();
        }

        return redirect()->route('payment.show', ['code' => $booking->code]);
    }
}
