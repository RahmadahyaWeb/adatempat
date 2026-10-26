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
        $primaryNavigationItems = [];
        $roleMenuLabel = null;
        $roleMenuGroups = [];
        $accountLabel = 'Pelanggan';

        if (! $compactHeader) {
            if (! $currentUser) {
                $primaryNavigationItems = [
                    ['label' => 'Jelajahi tempat', 'href' => route('home') . '#hasil'],
                    ['label' => 'Kategori', 'href' => route('home') . '#kategori'],
                    ['label' => 'Untuk pemilik', 'href' => route('home') . '#untuk-pemilik'],
                ];
            } elseif ($currentUser->isSuperAdmin()) {
                $accountLabel = 'Super Admin';
                $primaryNavigationItems = [
                    ['label' => 'Revisi identitas', 'href' => route('business-revisions.index')],
                ];
                $roleMenuLabel = 'Administrasi';
                $roleMenuGroups = [
                    [
                        'label' => 'Verifikasi & moderasi',
                        'items' => [
                            ['label' => 'Verifikasi usaha', 'placeholder' => 'Segera hadir'],
                            ['label' => 'Sengketa booking', 'href' => route('disputes.index')],
                            ['label' => 'Moderasi konten', 'placeholder' => 'Segera hadir'],
                        ],
                    ],
                    [
                        'label' => 'Pengelolaan platform',
                        'items' => [
                            ['label' => 'Template venue', 'href' => route('venue-templates.index')],
                            ['label' => 'Payout & rekening', 'href' => route('payouts.index')],
                            ['label' => 'Refund & rekonsiliasi', 'href' => route('refunds.index')],
                            ['label' => 'Langganan Owner', 'href' => route('subscriptions.index')],
                            ['label' => 'Pengaturan platform', 'placeholder' => 'Segera hadir'],
                        ],
                    ],
                ];
            } elseif ($currentUser->isOwner()) {
                $accountLabel = 'Owner';

                if ($currentUser->hasVerifiedEmail()) {
                    $primaryNavigationItems = [
                        ['label' => 'Usaha & venue', 'href' => route('owner.businesses.index')],
                    ];
                    $roleMenuLabel = 'Aktivitas usaha';
                    $roleMenuGroups = [
                        [
                            'label' => 'Operasional',
                            'items' => [
                                ['label' => 'Booking masuk', 'href' => route('owner.bookings.index')],
                                ['label' => 'Kalender', 'placeholder' => 'Segera hadir'],
                            ],
                        ],
                        [
                            'label' => 'Keuangan',
                            'items' => [
                                ['label' => 'Langganan', 'placeholder' => 'Segera hadir'],
                                ['label' => 'Rekening payout', 'href' => route('owner.payout-account')],
                                ['label' => 'Pendapatan', 'placeholder' => 'Segera hadir'],
                            ],
                        ],
                    ];
                } else {
                    $primaryNavigationItems = [
                        ['label' => 'Verifikasi email', 'href' => route('verification.notice')],
                        ['label' => 'Jelajahi tempat', 'href' => route('home') . '#hasil'],
                    ];
                }
            } else {
                $accountLabel = 'Pelanggan';
                $primaryNavigationItems = [
                    ['label' => 'Jelajahi tempat', 'href' => route('home') . '#hasil'],
                    ['label' => 'Kategori', 'href' => route('home') . '#kategori'],
                ];
                $roleMenuLabel = 'Aktivitas';
                $roleMenuGroups = [
                    [
                        'label' => 'Pesanan & interaksi',
                        'items' => [
                            ['label' => 'Pesanan saya', 'href' => route('customer.bookings.index')],
                            ['label' => 'Favorit', 'placeholder' => 'Segera hadir'],
                            ['label' => 'Pesan pemilik', 'placeholder' => 'Segera hadir'],
                        ],
                    ],
                ];
            }
        }
    @endphp

    <a href="#konten-utama" class="sr-only z-50 rounded-lg bg-white px-4 py-3 font-bold text-[var(--ink)] shadow-lg focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
        Lewati ke konten utama
    </a>

    <header class="site-header">
        <div class="site-container flex min-h-[4.25rem] items-center justify-start gap-3 sm:min-h-[4.75rem]">
            <a href="{{ route('home') }}" class="brand-wordmark shrink-0 rounded-lg py-2" aria-label="Adatempat — Beranda">
                <span class="brand-symbol" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                        <path d="M5 20V4h10v16M15 9h4v11M9 8h2M9 12h2M9 16h2"/>
                    </svg>
                </span>
                <span>adatempat</span>
            </a>

            @if ($compactHeader)
                <a href="{{ route('home') }}" class="btn btn-secondary ml-auto min-h-10 px-3 py-2 sm:px-4">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m15 18-6-6 6-6"/></svg>
                    <span class="hidden sm:inline">Kembali ke beranda</span>
                    <span class="sm:hidden">Beranda</span>
                </a>
            @else
                <nav aria-label="Navigasi utama" class="hidden min-w-0 items-center gap-1 xl:ml-6 xl:mr-auto xl:flex">
                    @foreach ($primaryNavigationItems as $item)
                        <a href="{{ $item['href'] }}" class="site-nav-link whitespace-nowrap">{{ $item['label'] }}</a>
                    @endforeach

                    @if ($roleMenuLabel && count($roleMenuGroups))
                        <details class="mobile-menu role-menu">
                            <summary aria-label="{{ $roleMenuLabel }}">
                                <span>{{ $roleMenuLabel }}</span>
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m7 10 5 5 5-5"/></svg>
                            </summary>
                            <div class="mobile-menu-panel role-menu-panel">
                                @foreach ($roleMenuGroups as $group)
                                    <div class="role-menu-group">
                                        <p class="role-menu-group-label">{{ $group['label'] }}</p>
                                        @foreach ($group['items'] as $item)
                                            @if (isset($item['href']))
                                                <a href="{{ $item['href'] }}" class="role-menu-item">{{ $item['label'] }}</a>
                                            @else
                                                <span class="role-menu-item role-menu-item-disabled" aria-disabled="true" title="Fitur ini belum tersedia">
                                                    <span>{{ $item['label'] }}</span>
                                                    <span class="role-menu-status">{{ $item['placeholder'] }}</span>
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </nav>

                <div class="hidden shrink-0 items-center gap-3 xl:flex">
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

                <details class="mobile-menu ml-auto xl:hidden">
                    <summary aria-label="Buka menu navigasi">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="size-5"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </summary>
                    <nav aria-label="Navigasi seluler" class="mobile-menu-panel">
                        @foreach ($primaryNavigationItems as $item)
                            <a href="{{ $item['href'] }}" class="site-nav-link w-full">{{ $item['label'] }}</a>
                        @endforeach

                        @foreach ($roleMenuGroups as $group)
                            <div class="role-menu-group">
                                <p class="role-menu-group-label">{{ $group['label'] }}</p>
                                @foreach ($group['items'] as $item)
                                    @if (isset($item['href']))
                                        <a href="{{ $item['href'] }}" class="role-menu-item">{{ $item['label'] }}</a>
                                    @else
                                        <span class="role-menu-item role-menu-item-disabled" aria-disabled="true" title="Fitur ini belum tersedia">
                                            <span>{{ $item['label'] }}</span>
                                            <span class="role-menu-status">{{ $item['placeholder'] }}</span>
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @endforeach

                        @auth
                            <div class="my-1 border-t border-[var(--border)]"></div>
                            <div class="px-3 py-2">
                                <p class="truncate text-sm font-bold text-[var(--ink)]">{{ $currentUser->name }}</p>
                                <p class="mt-0.5 text-xs font-semibold text-[var(--muted)]">{{ $accountLabel }}</p>
                            </div>
                            <form method="POST" action="{{ route('logout') }}" class="p-1">
                                @csrf
                                <button type="submit" class="btn btn-secondary w-full">Keluar</button>
                            </form>
                        @else
                            <div class="my-1 border-t border-[var(--border)]"></div>
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
                        <a class="underline-offset-4 hover:underline" href="{{ route('venue-templates.index') }}">Template venue</a>
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