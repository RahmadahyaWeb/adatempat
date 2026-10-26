<?php

use App\Models\Business;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profil usaha')] class extends Component {
    public string $businessSlug = '';

    public function mount(string $businessSlug): void
    {
        $this->businessSlug = $businessSlug;
    }

    #[Computed]
    public function business(): Business
    {
        return Business::query()
            ->where('slug', $this->businessSlug)
            ->where('status', Business::STATUS_ACTIVE)
            ->whereNotNull('published_at')
            ->with([
                'venues' => function ($query): void {
                    $query->published()
                        ->with('category')
                        ->orderBy('name');
                },
            ])
            ->firstOrFail();
    }
};
?>

<div class="min-h-[70vh]">
    @php($business = $this->business)

    <section class="bg-[var(--brand-forest)] text-white">
        <div class="site-container py-9 sm:py-12 lg:py-16">
            <a href="{{ route('home') }}" class="inline-flex min-h-9 items-center gap-2 text-sm font-semibold text-white/80 underline-offset-4 hover:text-white hover:underline">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m15 18-6-6 6-6"/></svg>
                Jelajahi Adatempat
            </a>

            <div class="mt-7 max-w-4xl">
                <p class="inline-flex items-center gap-2 text-xs font-extrabold uppercase tracking-[0.08em] text-[var(--brand-lime)]">
                    <span class="size-2 rounded-full bg-[var(--brand-lime)]"></span>
                    Profil usaha
                </p>
                <h1 class="mt-3 break-words text-3xl font-extrabold leading-tight tracking-[-0.055em] text-white sm:text-4xl lg:text-5xl">{{ $business->name }}</h1>
                @if ($business->address)
                    <p class="mt-4 flex items-start gap-2 text-sm leading-6 text-white/80 sm:text-base">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-5 shrink-0 text-[var(--brand-lime)]"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                        <span>{{ $business->address }}</span>
                    </p>
                @endif
                @if ($business->city || $business->province)
                    <p class="mt-2 text-sm leading-6 text-white/70">{{ collect([$business->city, $business->province])->filter()->implode(', ') }}</p>
                @endif
            </div>
        </div>
    </section>

    <section aria-labelledby="venue-list-heading" class="site-container py-9 sm:py-12 lg:py-14">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow"><span class="eyebrow-dot"></span> Katalog usaha</p>
                <h2 id="venue-list-heading" class="mt-3 text-2xl font-extrabold leading-tight tracking-[-0.05em] sm:text-3xl">Venue yang tersedia</h2>
                <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Informasi di halaman ini berasal dari venue yang sudah dipublikasikan.</p>
            </div>
            <span class="text-sm font-semibold text-[var(--muted)]">{{ $business->venues->count() }} venue</span>
        </div>

        @if ($business->venues->isEmpty())
            <div class="mt-6 rounded-2xl bg-[var(--surface-soft)] px-5 py-9 text-center sm:px-8 sm:py-12">
                <span class="mx-auto flex size-12 items-center justify-center rounded-xl bg-[var(--brand-lime)] text-[var(--ink)]">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M10 21v-3h4v3"/></svg>
                </span>
                <h3 class="mt-4 text-lg font-extrabold tracking-[-0.035em]">Belum ada venue yang dipublikasikan.</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[var(--muted)]">Silakan kembali lagi nanti untuk melihat pilihan venue dari usaha ini.</p>
            </div>
        @else
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($business->venues as $venue)
                    <article wire:key="public-venue-{{ $venue->id }}" class="min-w-0 overflow-hidden rounded-2xl bg-white">
                        <div class="flex aspect-[4/3] flex-col items-center justify-center gap-3 bg-[var(--surface-soft)] px-5 text-center">
                            <span class="flex size-11 items-center justify-center rounded-xl bg-white text-[var(--brand-forest)]">
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="size-6"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 15-5-5L5 20"/></svg>
                            </span>
                            <p class="text-xs font-semibold text-[var(--muted)]">Foto venue belum tersedia</p>
                        </div>
                        <div class="p-4 sm:p-5">
                            <span class="inline-flex max-w-full items-center rounded-md bg-[var(--surface-lime)] px-2.5 py-1 text-xs font-bold text-[var(--brand-forest-deep)]">{{ $venue->category?->name ?? 'Kategori belum tersedia' }}</span>
                            <h3 class="mt-3 break-words text-lg font-extrabold leading-tight tracking-[-0.04em]">{{ $venue->name }}</h3>
                            @if ($venue->description)
                                <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">{{ \Illuminate\Support\Str::limit($venue->description, 180) }}</p>
                            @else
                                <p class="mt-2 text-sm leading-6 text-[var(--muted)]">Deskripsi venue belum ditambahkan oleh pemilik usaha.</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>