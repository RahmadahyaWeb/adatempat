<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FAF8F5">

    <title>{{ isset($title) && $title ? $title . ' — ' : '' }}{{ config('app.name', 'Adatempat') }}</title>

    <meta name="description" content="Temukan tempat yang sesuai untuk kebutuhanmu bersama Adatempat.">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="flex min-h-screen flex-col bg-surface text-ink antialiased">
    <a href="#konten-utama"
        class="sr-only z-50 rounded-md bg-surface px-4 py-3 text-ink focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
        Lewati ke konten utama
    </a>

    <header class="sticky top-0 z-40 border-b border-border bg-surface/95 backdrop-blur">
        <div class="mx-auto flex min-h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}"
                class="inline-flex shrink-0 items-center gap-3 rounded-control focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-primary"
                aria-label="{{ config('app.name', 'Adatempat') }} — Beranda">
                <span
                    class="flex size-10 items-center justify-center rounded-card bg-brand-primary font-serif text-xl text-white">
                    A
                </span>

                <span class="text-lg font-semibold tracking-tight text-ink sm:text-xl">
                    {{ config('app.name', 'Adatempat') }}
                </span>
            </a>

            <nav aria-label="Navigasi utama" class="flex items-center gap-1 sm:gap-2">
                <a href="{{ route('home') }}#jelajahi"
                    class="inline-flex min-h-10 items-center rounded-control px-3 text-sm font-medium text-text-secondary transition-colors hover:bg-surface-subtle hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary sm:px-4">
                    Jelajahi
                </a>

                @guest
                    <a href="{{ route('login') }}"
                        class="inline-flex min-h-10 items-center rounded-control px-3 text-sm font-medium text-text-secondary transition-colors hover:bg-surface-subtle hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary sm:px-4">
                        Masuk
                    </a>

                    <a href="{{ route('register') }}"
                        class="inline-flex min-h-10 items-center justify-center rounded-control bg-brand-primary px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary sm:px-4">
                        Daftar
                    </a>
                @endguest

                @auth
                    <span class="hidden max-w-40 truncate text-sm text-text-secondary sm:inline">
                        {{ auth()->user()->name }}
                    </span>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <button type="submit"
                            class="inline-flex min-h-10 items-center justify-center rounded-control border border-border px-3 text-sm font-medium text-text-secondary transition-colors hover:bg-surface-subtle hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary sm:px-4">
                            Keluar
                        </button>
                    </form>
                @endauth
            </nav>
        </div>
    </header>

    <main id="konten-utama" class="min-w-0 flex-1">
        {{ $slot }}
    </main>

    <footer class="mt-auto border-t border-border bg-surface">
        <div
            class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-6 text-sm text-muted sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <p>
                © {{ date('Y') }} {{ config('app.name', 'Adatempat') }}
            </p>

            <p>
                Temukan tempat yang sesuai untuk kebutuhanmu.
            </p>
        </div>
    </footer>

    @livewireScripts
</body>

</html>
