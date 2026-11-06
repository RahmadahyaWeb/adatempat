<?php

namespace App\Http\Controllers\Owner;

use App\Enums\UserRole;
use App\Enums\VenueApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StoreVenueRequest;
use App\Http\Requests\Owner\UpdateVenueRequest;
use App\Models\Business;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VenueController extends Controller
{
    /**
     * @var list<array{value: string, label: string}>
     */
    private const TIMEZONES = [
        ['value' => 'Asia/Jakarta', 'label' => 'WIB (Waktu Indonesia Barat)'],
        ['value' => 'Asia/Makassar', 'label' => 'WITA (Waktu Indonesia Tengah)'],
        ['value' => 'Asia/Jayapura', 'label' => 'WIT (Waktu Indonesia Timur)'],
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();

        if ($user->role === UserRole::Superadmin) {
            $venuesQuery = Venue::query()->with('business');
            $businesses = Business::query()->orderBy('name')->get(['id', 'name']);
        } else {
            $businessIds = $user->businesses()->pluck('id');
            $venuesQuery = Venue::query()->whereIn('business_id', $businessIds)->with('business');
            $businesses = $user->businesses()->orderBy('name')->get(['id', 'name']);
        }

        /** @var LengthAwarePaginator<int, Venue> $paginator */
        $paginator = $venuesQuery
            ->withCount('spaces')
            ->latest()
            ->paginate(15);

        $venues = $paginator->through(fn (Venue $venue): array => [
            'id' => $venue->id,
            'name' => $venue->name,
            'slug' => $venue->slug,
            'city' => $venue->city,
            'address' => $venue->address,
            'timezone' => $venue->timezone,
            'contact_phone' => $venue->contact_phone,
            'is_published' => $venue->is_published,
            'approval_status' => $venue->approval_status->value,
            'rejection_reason' => $venue->rejection_reason,
            'cover_url' => $venue->cover_url,
            'spaces_count' => (int) $venue->spaces_count,
            'business_name' => $venue->business->name,
        ]);

        return Inertia::render('owner/venues/index', [
            'venues' => $venues,
            'businesses' => $businesses,
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        Gate::authorize('create', Venue::class);

        $user = $request->user();

        if ($user->role !== UserRole::Superadmin && ! $user->businesses()->exists()) {
            return redirect()->route('register.business')->with(
                'info',
                'Daftarkan bisnis terlebih dahulu sebelum menambahkan venue.'
            );
        }

        $businesses = $user->role === UserRole::Superadmin
            ? Business::query()->orderBy('name')->get(['id', 'name'])
            : $user->businesses()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('owner/venues/create', [
            'businesses' => $businesses,
            'timezones' => self::TIMEZONES,
        ]);
    }

    public function store(StoreVenueRequest $request): RedirectResponse
    {
        Gate::authorize('create', Venue::class);

        $name = (string) $request->input('name');
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;
        while (Venue::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $coverPath = null;
        if ($request->hasFile('cover_photo')) {
            $stored = $request->file('cover_photo')?->store('venues/covers', 'local');
            $coverPath = is_string($stored) ? $stored : null;
        }

        Venue::query()->create([
            'business_id' => $request->integer('business_id'),
            'name' => $name,
            'slug' => $slug,
            'city' => (string) $request->input('city'),
            'address' => (string) $request->input('address'),
            'timezone' => (string) $request->input('timezone'),
            'contact_phone' => $request->input('contact_phone'),
            'cover_photo_path' => $coverPath,
            'approval_status' => VenueApprovalStatus::Pending,
            'is_published' => false,
        ]);

        return redirect()->route('owner.venues.index')->with(
            'success',
            'Venue berhasil didaftarkan dan sedang menunggu persetujuan superadmin.'
        );
    }

    public function edit(Venue $venue): Response
    {
        Gate::authorize('update', $venue);

        $user = request()->user();
        $businesses = $user->role === UserRole::Superadmin
            ? Business::query()->orderBy('name')->get(['id', 'name'])
            : $user->businesses()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('owner/venues/edit', [
            'venue' => [
                'id' => $venue->id,
                'business_id' => $venue->business_id,
                'business_name' => $venue->business->name,
                'name' => $venue->name,
                'slug' => $venue->slug,
                'city' => $venue->city,
                'address' => $venue->address,
                'timezone' => $venue->timezone,
                'contact_phone' => $venue->contact_phone,
                'is_published' => $venue->is_published,
                'approval_status' => $venue->approval_status->value,
                'rejection_reason' => $venue->rejection_reason,
                'cover_url' => $venue->cover_url,
            ],
            'businesses' => $businesses,
            'timezones' => self::TIMEZONES,
        ]);
    }

    public function update(UpdateVenueRequest $request, Venue $venue): RedirectResponse
    {
        Gate::authorize('update', $venue);

        $isApproved = $venue->approval_status === VenueApprovalStatus::Approved;
        $name = (string) $request->input('name');
        $address = (string) $request->input('address');

        $nameChanged = $venue->name !== $name;
        $addressChanged = $venue->address !== $address;
        $isMaterialChange = $isApproved && ($nameChanged || $addressChanged);

        if ($nameChanged) {
            $baseSlug = Str::slug($name);
            $slug = $baseSlug;
            $counter = 1;
            while (Venue::query()->where('slug', $slug)->where('id', '!=', $venue->id)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }
            $venue->slug = $slug;
        }

        if ($request->filled('business_id')) {
            $venue->business_id = $request->integer('business_id');
        }

        $venue->name = $name;
        $venue->city = (string) $request->input('city');
        $venue->address = $address;
        $venue->timezone = (string) $request->input('timezone');
        $venue->contact_phone = $request->input('contact_phone');

        if ($request->hasFile('cover_photo')) {
            if ($venue->cover_photo_path && Storage::disk('local')->exists($venue->cover_photo_path)) {
                Storage::disk('local')->delete($venue->cover_photo_path);
            }
            $stored = $request->file('cover_photo')?->store('venues/covers', 'local');
            $venue->cover_photo_path = is_string($stored) ? $stored : null;
        }

        if ($isMaterialChange) {
            $venue->approval_status = VenueApprovalStatus::Pending;
            $venue->is_published = false;
            $venue->approved_at = null;
            $venue->approved_by = null;
            $venue->save();

            return redirect()->route('owner.venues.index')->with(
                'info',
                'Perubahan nama atau alamat venue memerlukan persetujuan ulang oleh superadmin. Status venue kembali ke Menunggu Persetujuan dan tayangan dinonaktifkan sementara.'
            );
        }

        $venue->save();

        return redirect()->route('owner.venues.index')->with(
            'success',
            'Data venue berhasil diperbarui.'
        );
    }

    public function togglePublish(Venue $venue): RedirectResponse
    {
        Gate::authorize('update', $venue);

        if ($venue->approval_status !== VenueApprovalStatus::Approved) {
            return back()->with('error', 'Venue belum disetujui oleh superadmin dan tidak dapat dipublikasikan ke marketplace.');
        }

        $venue->is_published = ! $venue->is_published;
        $venue->save();

        $message = $venue->is_published
            ? "Venue '{$venue->name}' sekarang aktif dan tayang di marketplace."
            : "Venue '{$venue->name}' ditarik dari tayangan marketplace.";

        return back()->with('success', $message);
    }

    public function submitApproval(Venue $venue): RedirectResponse
    {
        Gate::authorize('update', $venue);

        if ($venue->approval_status === VenueApprovalStatus::Approved) {
            return back()->with('info', 'Venue ini sudah disetujui.');
        }

        $venue->approval_status = VenueApprovalStatus::Pending;
        $venue->rejection_reason = null;
        $venue->save();

        return back()->with('success', "Pengajuan verifikasi untuk venue '{$venue->name}' berhasil dikirimkan ke superadmin.");
    }

    public function destroy(Venue $venue): RedirectResponse
    {
        Gate::authorize('delete', $venue);

        if ($venue->bookings()->exists()) {
            return back()->with('error', 'Venue tidak dapat dihapus karena sudah memiliki riwayat pemesanan.');
        }

        if ($venue->cover_photo_path && Storage::disk('local')->exists($venue->cover_photo_path)) {
            Storage::disk('local')->delete($venue->cover_photo_path);
        }

        $name = $venue->name;
        $venue->delete();

        return redirect()->route('owner.venues.index')->with('success', "Venue '{$name}' berhasil dihapus.");
    }
}
