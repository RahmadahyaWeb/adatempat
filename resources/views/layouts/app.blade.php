<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FBFCF8">
    <meta name="description" content="Temukan tempat yang tepat untuk acara, pertemuan, produksi, dan kebutuhan lainnya bersama Adatempat.">
    <meta name="color-scheme" content="light">
    <title>{{ isset($title) && $title ? $title . ' — ' : '' }}{{ config('app.name', 'Adatempat') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col bg-[var(--canvas)] font-sans text-[var(--ink)] antialiased">
    @php
        $currentUser = auth()->user();
        $compactHeader = request()->routeIs('login', 'register', 'password.request', 'password.reset', 'verification.notice', 'mfa.setup', 'mfa.challenge');
        $navigationItems = [];
        $accountLabel = 'Pelanggan';

        if (! $compactHeader) {
            if (! $currentUser) {
                $navigationItems = [
                    ['label' => 'Jelajahi tempat', 'href' => route('home') . '#hasil'],
                    ['label' => 'Kategori', 'href' => route('home') . '#kategori'],
                    ['label' => 'Untuk pemilik', 'href' => route('home') . '#untuk-pemilik'],
                ];
            } elseif ($currentUser->isSuperAdmin()) {
                $accountLabel = 'Super Admin';
                $navigationItems = [
                    ['label' => 'Revisi identitas', 'href' => route('business-revisions.index')],
                    ['label' => 'Verifikasi usaha', 'placeholder' => 'Segera hadir'],
                    ['label' => 'Paket langganan', 'placeholder' => 'Segera hadir'],
                    ['label' => 'Moderasi', 'placeholder' => 'Segera hadir'],
                    ['label' => 'Pengaturan', 'placeholder' => 'Segera hadir'],
                ];
            } elseif ($currentUser->isOwner()) {
                $accountLabel = 'Owner';

                if ($currentUser->hasVerifiedEmail()) {
                    $navigationItems = [
                        ['label' => 'Usaha & venue', 'href' => route('owner.businesses.index')],
                        ['label' => 'Pesanan', 'placeholder' => 'Segera hadir'],
                        ['label' => 'Kalender', 'placeholder' => 'Segera hadir'],
                        ['label' => 'Langganan', 'placeholder' => 'Segera hadir'],
                        ['label' => 'Pendapatan', 'placeholder' => 'Segera hadir'],
                    ];
                } else {
                    $navigationItems = [
                        ['label' => 'Verifikasi email', 'href' => route('verification.notice')],
                        ['label' => 'Jelajahi tempat', 'href' => route('home') . '#hasil'],
                        ['label' => 'Kategori', 'href' => route('home') . '#kategori'],
                    ];
                }
            } else {
                $accountLabel = 'Pelanggan';
                $navigationItems = [
                    ['label' => 'Jelajahi tempat', 'href' => route('home') . '#hasil'],
                    ['label' => 'Kategori', 'href' => route('home') . '#kategori'],
                    ['label' => 'Pesanan saya', 'placeholder' => 'Segera hadir'],
                    ['label' => 'Favorit', 'placeholder' => 'Segera hadir'],
                    ['label' => 'Pesan pemilik', 'placeholder' => 'Segera hadir'],
                ];
            }
        }
    @endphp

    <a href="#konten-utama" class="sr-only z-50 rounded-lg bg-white px-4 py-3 font-bold text-[var(--ink)] shadow-lg focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
        Lewati ke konten utama
    </a>

    <header class="site-header">
        <div class="site-container flex min-h-[4.25rem] items-center justify-between gap-3 sm:min-h-[4.75rem]">
            <a href="{{ route('home') }}" class="brand-wordmark shrink-0 rounded-lg py-2" aria-label="Adatempat — Beranda">
                <span class="brand-symbol" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                        <path d="M5 20V4h10v16M15 9h4v11M9 8h2M9 12h2M9 16h2"/>
                    </svg>
                </span>
                <span>adatempat</span>
            </a>

            @if ($compactHeader)
                <a href="{{ route('home') }}" class="btn btn-secondary min-h-10 px-3 py-2 sm:px-4">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m15 18-6-6 6-6"/></svg>
                    <span class="hidden sm:inline">Kembali ke beranda</span>
                    <span class="sm:hidden">Beranda</span>
                </a>
            @else
                <nav aria-label="Navigasi utama" class="hidden min-w-0 items-center gap-0.5 lg:flex">
                    @foreach ($navigationItems as $item)
                        @if (isset($item['href']))
                            <a href="{{ $item['href'] }}" class="site-nav-link whitespace-nowrap">{{ $item['label'] }}</a>
                        @else
                            <span class="site-nav-link cursor-not-allowed gap-1.5 whitespace-nowrap opacity-65" aria-disabled="true" title="Fitur ini belum tersedia">
                                <span>{{ $item['label'] }}</span>
                                <span class="rounded-md bg-[var(--surface-soft)] px-1.5 py-0.5 text-[0.625rem] font-bold leading-none text-[var(--muted)]">{{ $item['placeholder'] }}</span>
                            </span>
                        @endif
                    @endforeach
                </nav>

                <div class="hidden shrink-0 items-center gap-3 lg:flex">
                    @auth
                        <div class="max-w-44 text-right">
                            <span class="block max-w-44 truncate text-sm font-bold text-[var(--ink)]">{{ $currentUser->name }}</span>
                            <span class="block text-xs font-semibold text-[var(--muted)]">{{ $accountLabel }}</span>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-secondary">Keluar</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-secondary">Masuk</a>
                        <a href="{{ route('register') }}" class="btn btn-primary">Buat akun</a>
                    @endauth
                </div>

                <details class="mobile-menu lg:hidden">
                    <summary aria-label="Buka menu navigasi" aria-controls="menu-navigasi-seluler">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="size-5"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </summary>
                    <nav id="menu-navigasi-seluler" aria-label="Navigasi seluler" class="mobile-menu-panel">
                        @foreach ($navigationItems as $item)
                            @if (isset($item['href']))
                                <a href="{{ $item['href'] }}" class="site-nav-link w-full justify-between">{{ $item['label'] }}</a>
                            @else
                                <span class="site-nav-link w-full cursor-not-allowed justify-between gap-3 opacity-70" aria-disabled="true" title="Fitur ini belum tersedia">
                                    <span>{{ $item['label'] }}</span>
                                    <span class="shrink-0 rounded-md bg-[var(--surface-soft)] px-1.5 py-1 text-[0.625rem] font-bold leading-none text-[var(--muted)]">{{ $item['placeholder'] }}</span>
                                </span>
                            @endif
                        @endforeach

                        <div class="my-1 border-t border-[var(--border)]"></div>
                        @auth
                            <div class="px-3 py-2">
                                <p class="truncate text-sm font-bold text-[var(--ink)]">{{ $currentUser->name }}</p>
                                <p class="mt-0.5 text-xs font-semibold text-[var(--muted)]">{{ $accountLabel }}</p>
                            </div>
                            <form method="POST" action="{{ route('logout') }}" class="p-1">
                                @csrf
                                <button type="submit" class="btn btn-secondary w-full">Keluar</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-secondary w-full">Masuk</a>
                            <a href="{{ route('register') }}" class="btn btn-primary w-full">Buat akun</a>
                        @endauth
                    </nav>
                </details>
            @endif
        </div>
    </header>

    <main id="konten-utama" class="flex-1">
        {{ $slot }}
    </main>

    <footer class="site-footer">
        <div class="site-container grid gap-6 py-7 sm:grid-cols-[1fr_auto] sm:items-end sm:gap-8 sm:py-9">
            <div>
                <a href="{{ route('home') }}" class="brand-wordmark text-lg">
                    <span class="brand-symbol !h-7 !w-7 !rounded-md" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M5 20V4h10v16M15 9h4v11M9 8h2M9 12h2M9 16h2"/></svg>
                    </span>
                    <span>adatempat</span>
                </a>
                <p class="mt-3 max-w-md text-sm leading-6 text-[var(--muted)]">
                    Temukan tempat yang tepat untuk kebutuhanmu. Bandingkan informasi yang tersedia sebelum memilih tempat yang sesuai.
                </p>
            </div>
            <nav aria-label="Navigasi footer" class="flex flex-wrap gap-x-5 gap-y-2 text-sm font-semibold text-[var(--ink-soft)]">
                <a class="underline-offset-4 hover:underline" href="{{ route('home') }}#kategori">Kategori</a>
                <a class="underline-offset-4 hover:underline" href="{{ route('home') }}#hasil">Jelajahi tempat</a>
                @if ($currentUser)
                    @if ($currentUser->isSuperAdmin())
                        <a class="underline-offset-4 hover:underline" href="{{ route('business-revisions.index') }}">Peninjauan revisi</a>
                    @elseif ($currentUser->isOwner())
                        @if ($currentUser->hasVerifiedEmail())
                            <a class="underline-offset-4 hover:underline" href="{{ route('owner.businesses.index') }}">Kelola usaha</a>
                        @else
                            <a class="underline-offset-4 hover:underline" href="{{ route('verification.notice') }}">Verifikasi email</a>
                        @endif
                    @endif
                @else
                    <a class="underline-offset-4 hover:underline" href="{{ route('home') }}#untuk-pemilik">Untuk pemilik</a>
                    <a class="underline-offset-4 hover:underline" href="{{ route('register') }}">Buat akun</a>
                    <a class="underline-offset-4 hover:underline" href="{{ route('login') }}">Masuk</a>
                @endif
            </nav>
            <p class="border-t border-[var(--border)] pt-4 text-xs text-[var(--muted)] sm:col-span-2">© {{ date('Y') }} Adatempat. Semua hak dilindungi.</p>
        </div>
    </footer>
    @livewireScripts
</body>
</html>
