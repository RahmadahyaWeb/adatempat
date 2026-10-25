<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FBFCF8">
    <meta name="description" content="Temukan tempat untuk acara, pertemuan, produksi, dan berbagai rencana lainnya bersama Adatempat.">
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
        $compactHeader = request()->routeIs('login', 'register', 'password.request', 'password.reset', 'verification.notice', 'mfa.setup', 'mfa.challenge');
    @endphp

    <a href="#konten-utama" class="sr-only z-50 rounded-lg bg-white px-4 py-3 font-bold text-[var(--ink)] shadow-lg focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
        Lewati ke konten utama
    </a>

    <header class="site-header">
        <div class="site-container flex min-h-[4.25rem] items-center justify-between gap-4 sm:min-h-[4.75rem]">
            <a href="{{ route('home') }}" class="brand-wordmark rounded-lg py-2" aria-label="Adatempat — Beranda">
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
                <nav aria-label="Navigasi utama" class="hidden items-center gap-1 md:flex">
                    <a href="{{ route('home') }}#hasil" class="site-nav-link">Jelajahi tempat</a>
                    <a href="{{ route('home') }}#kategori" class="site-nav-link">Kategori</a>
                    <a href="{{ route('home') }}#untuk-pemilik" class="site-nav-link">Untuk pemilik</a>
                </nav>

                <div class="hidden items-center gap-2 md:flex">
                    @auth
                        <span class="mr-2 max-w-40 truncate text-sm font-semibold text-[var(--ink-soft)]">Hai, {{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-secondary">Keluar</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-secondary">Masuk</a>
                        <a href="{{ route('register') }}" class="btn btn-primary">Buat akun</a>
                    @endauth
                </div>

                <details class="mobile-menu md:hidden">
                    <summary aria-label="Buka menu navigasi">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="size-5"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </summary>
                    <nav aria-label="Navigasi seluler" class="mobile-menu-panel">
                        <a href="{{ route('home') }}#hasil" class="site-nav-link">Jelajahi tempat</a>
                        <a href="{{ route('home') }}#kategori" class="site-nav-link">Kategori</a>
                        <a href="{{ route('home') }}#untuk-pemilik" class="site-nav-link">Untuk pemilik</a>
                        <div class="my-1 border-t border-[var(--border)]"></div>
                        @auth
                            <p class="px-3 py-2 text-sm font-semibold text-[var(--ink-soft)]">Masuk sebagai {{ auth()->user()->name }}</p>
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
                    Mulai dari rencana, temukan tempat yang sesuai. Informasi venue ditampilkan berdasarkan data yang dipublikasikan pemiliknya.
                </p>
            </div>
            <nav aria-label="Navigasi footer" class="flex flex-wrap gap-x-5 gap-y-2 text-sm font-semibold text-[var(--ink-soft)]">
                <a class="underline-offset-4 hover:underline" href="{{ route('home') }}#kategori">Kategori</a>
                <a class="underline-offset-4 hover:underline" href="{{ route('home') }}#hasil">Jelajahi tempat</a>
                <a class="underline-offset-4 hover:underline" href="{{ route('register') }}">Daftar</a>
                <a class="underline-offset-4 hover:underline" href="{{ route('login') }}">Masuk</a>
            </nav>
            <p class="border-t border-[var(--border)] pt-4 text-xs text-[var(--muted)] sm:col-span-2">© {{ date('Y') }} Adatempat. Semua hak dilindungi.</p>
        </div>
    </footer>
    @livewireScripts
</body>
</html>