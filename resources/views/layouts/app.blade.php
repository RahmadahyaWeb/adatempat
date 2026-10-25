<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FAF8F5">
    <meta name="description" content="Temukan dan pesan venue yang sesuai dengan kebutuhanmu bersama Adatempat.">
    <meta name="color-scheme" content="light">
    <title>{{ isset($title) && $title ? $title . ' — ' : '' }}{{ config('app.name', 'Adatempat') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;500&family=Instrument+Serif:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col bg-[var(--bg-canvas)] font-sans text-[var(--text-primary)] antialiased">
    @php
        $compactHeader = request()->routeIs(
            'login',
            'register',
            'password.request',
            'password.reset',
            'verification.notice',
            'mfa.setup',
            'mfa.challenge',
        );
    @endphp

    <a href="#konten-utama" class="sr-only z-50 rounded-full bg-[var(--bg-surface)] px-5 py-3 font-semibold text-[var(--text-primary)] shadow-floating focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
        Lewati ke konten utama
    </a>

    <header class="sticky top-0 z-40 border-b border-[var(--border-subtle)] bg-[var(--bg-canvas)]/95 backdrop-blur-xl">
        <div class="site-container flex min-h-[4.5rem] items-center justify-between gap-3 sm:min-h-[5rem]">
            <a href="{{ route('home') }}" class="inline-flex min-w-0 shrink-0 items-center gap-2.5 rounded-xl" aria-label="{{ config('app.name', 'Adatempat') }} — Beranda">
                <span class="flex size-10 items-center justify-center rounded-xl bg-[var(--accent-primary)] font-editorial text-2xl leading-none text-white sm:size-11">a</span>
                <span class="font-sans text-lg font-bold tracking-[-0.045em] text-[var(--text-primary)] sm:text-xl">{{ config('app.name', 'Adatempat') }}</span>
            </a>

            @if ($compactHeader)
                <a href="{{ route('home') }}" class="inline-flex min-h-10 items-center gap-2 rounded-full px-3 text-sm font-medium text-[var(--text-secondary)] transition-colors hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)] sm:px-4">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="size-4">
                        <path d="m15 18-6-6 6-6"/>
                    </svg>
                    <span class="hidden sm:inline">Kembali ke beranda</span>
                    <span class="sm:hidden">Beranda</span>
                </a>
            @else
                <nav aria-label="Navigasi utama" class="hidden items-center gap-1 lg:flex">
                    <a href="{{ route('home') }}#jelajahi" class="rounded-full px-4 py-2.5 text-sm font-medium text-[var(--text-secondary)] transition-colors hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]">Jelajahi</a>
                    <a href="{{ route('home') }}#kategori" class="rounded-full px-4 py-2.5 text-sm font-medium text-[var(--text-secondary)] transition-colors hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]">Kategori</a>
                    <a href="{{ route('home') }}#cara-kerja" class="rounded-full px-4 py-2.5 text-sm font-medium text-[var(--text-secondary)] transition-colors hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]">Cara kerja</a>
                    <a href="{{ route('home') }}#untuk-owner" class="rounded-full px-4 py-2.5 text-sm font-medium text-[var(--text-secondary)] transition-colors hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]">Untuk pemilik usaha</a>
                </nav>

                <div class="hidden shrink-0 items-center gap-2 sm:flex">
                    @guest
                        <a href="{{ route('login') }}" class="ui-button ui-button-secondary min-h-10 border-transparent px-4">Masuk</a>
                        <a href="{{ route('register') }}" class="ui-button ui-button-primary min-h-10 px-5">Buat akun</a>
                    @endguest

                    @auth
                        <span class="hidden max-w-40 truncate text-sm text-[var(--text-secondary)] xl:inline">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="ui-button ui-button-secondary min-h-10">Keluar</button>
                        </form>
                    @endauth
                </div>

                <details class="relative sm:hidden">
                    <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-full border border-[var(--border-subtle)] bg-[var(--bg-surface)] text-[var(--text-primary)] [&::-webkit-details-marker]:hidden" aria-label="Buka menu navigasi">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" class="size-5">
                            <path d="M4 7h16M4 12h16M4 17h16"/>
                        </svg>
                    </summary>
                    <div class="absolute right-0 top-full mt-3 w-64 rounded-2xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] p-2 shadow-floating">
                        <a href="{{ route('home') }}#jelajahi" class="block rounded-xl px-4 py-3 text-sm font-medium hover:bg-[var(--bg-subtle)]">Jelajahi</a>
                        <a href="{{ route('home') }}#kategori" class="block rounded-xl px-4 py-3 text-sm font-medium hover:bg-[var(--bg-subtle)]">Kategori</a>
                        <a href="{{ route('home') }}#cara-kerja" class="block rounded-xl px-4 py-3 text-sm font-medium hover:bg-[var(--bg-subtle)]">Cara kerja</a>
                        <a href="{{ route('home') }}#untuk-owner" class="block rounded-xl px-4 py-3 text-sm font-medium hover:bg-[var(--bg-subtle)]">Untuk pemilik usaha</a>
                        <div class="my-2 border-t border-[var(--border-subtle)]"></div>
                        @guest
                            <a href="{{ route('login') }}" class="block rounded-xl px-4 py-3 text-sm font-medium hover:bg-[var(--bg-subtle)]">Masuk</a>
                            <a href="{{ route('register') }}" class="block rounded-xl bg-[var(--accent-primary)] px-4 py-3 text-sm font-semibold text-white hover:bg-[var(--accent-primary-hover)]">Buat akun</a>
                        @endguest
                        @auth
                            <p class="truncate px-4 py-2 text-xs text-[var(--text-muted)]">{{ auth()->user()->name }}</p>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full rounded-xl px-4 py-3 text-left text-sm font-medium hover:bg-[var(--bg-subtle)]">Keluar</button>
                            </form>
                        @endauth
                    </div>
                </details>
            @endif
        </div>
    </header>

    <main id="konten-utama" class="min-w-0 flex-1">
        {{ $slot }}
    </main>

    <footer class="mt-auto border-t border-[var(--border-subtle)] bg-[var(--bg-surface)]">
        <div class="site-container grid gap-8 py-8 sm:grid-cols-[1fr_auto] sm:items-end sm:py-10">
            <div class="max-w-md">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-[var(--accent-primary)] font-editorial text-xl leading-none text-white">a</span>
                    <span class="font-bold tracking-[-0.04em]">{{ config('app.name', 'Adatempat') }}</span>
                </a>
                <p class="mt-3 max-w-sm text-sm leading-6 text-[var(--text-secondary)]">Lebih mudah menemukan tempat untuk rencana yang berarti.</p>
            </div>

            <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-[var(--text-secondary)] sm:justify-end">
                <a href="{{ route('home') }}#jelajahi" class="transition-colors hover:text-[var(--accent-primary)]">Jelajahi</a>
                <a href="{{ route('home') }}#kategori" class="transition-colors hover:text-[var(--accent-primary)]">Kategori</a>
                <a href="{{ route('home') }}#untuk-owner" class="transition-colors hover:text-[var(--accent-primary)]">Untuk pemilik usaha</a>
            </div>

            <div class="border-t border-[var(--border-subtle)] pt-5 text-xs text-[var(--text-muted)] sm:col-span-2 sm:flex sm:items-center sm:justify-between">
                <p>© {{ date('Y') }} {{ config('app.name', 'Adatempat') }}.</p>
                <p class="mt-1 sm:mt-0">Dibuat untuk membantu rencana menemukan tempatnya.</p>
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>