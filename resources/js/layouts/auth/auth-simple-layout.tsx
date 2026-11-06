import { Link } from '@inertiajs/react';
import { CheckCircle2, ShieldCheck, Sparkles } from 'lucide-react';
import { Wordmark } from '@/components/public/wordmark';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div
            className="min-h-dvh bg-cream font-body text-ink selection:bg-cobalt selection:text-cream"
            style={{ colorScheme: 'light' }}
        >
            <a
                href="#auth-form"
                className="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:inline-flex focus:min-h-11 focus:items-center focus:bg-ink focus:px-4 focus:font-body focus:text-sm focus:text-cream focus:outline-2 focus:outline-offset-2 focus:outline-cobalt"
            >
                Langsung ke formulir
            </a>

            <div className="grid min-h-dvh lg:grid-cols-12">
                {/* Brand & Editorial Showcase (Desktop Left Column) */}
                <aside className="relative hidden border-r border-ink/15 bg-sand/25 p-8 lg:col-span-5 lg:flex lg:flex-col lg:justify-between xl:col-span-5 xl:p-12">
                    <div>
                        <Link
                            href={home()}
                            className="inline-flex items-center gap-2 transition-opacity hover:opacity-85 focus-visible:outline-2 focus-visible:outline-cobalt"
                        >
                            <Wordmark className="text-3xl text-ink" />
                        </Link>
                        <p className="mt-2 font-data text-xs tracking-[0.18em] text-stone uppercase">
                            Pemesanan Tempat Olahraga, Kerja, &amp; Ruang
                            Komunitas
                        </p>
                    </div>

                    <div className="my-auto space-y-6 py-8">
                        <div className="space-y-3">
                            <span className="inline-flex items-center gap-1.5 rounded-control border border-ink/20 bg-cream px-2.5 py-1 font-data text-xs font-semibold tracking-wider text-stone uppercase">
                                <Sparkles className="size-3.5 text-cobalt" />
                                Booking Tanpa Ribet
                            </span>
                            <h2 className="font-display text-3xl leading-tight font-extrabold tracking-tight text-ink xl:text-4xl">
                                Mau olahraga, kerja, atau bikin acara? Semua ada
                                tempatnya.
                            </h2>
                            <p className="font-body text-sm leading-relaxed text-stone">
                                Cek jadwal kosong secara real-time, bayar instan
                                pakai QRIS, dan simpan tiket digital di HP kamu
                                untuk langsung ditunjukkan di lokasi.
                            </p>
                        </div>

                        {/* Visual Preview Card */}
                        <div className="overflow-hidden rounded-control border border-ink/20 bg-cream p-3 shadow-xs">
                            <div className="relative aspect-16/9 overflow-hidden rounded-control bg-stone/20">
                                <img
                                    src="/images/venues/futsal.jpg"
                                    alt="Ilustrasi tempat dan venue AdaTempat"
                                    className="h-full w-full object-cover"
                                />
                                <div className="absolute inset-0 bg-gradient-to-t from-ink/70 via-transparent to-transparent" />
                                <div className="absolute start-3 end-3 bottom-2.5 flex items-center justify-between text-cream">
                                    <span className="font-body text-xs font-medium">
                                        Venue Terverifikasi
                                    </span>
                                    <span className="rounded-control bg-cobalt px-2 py-0.5 font-data text-[11px] font-semibold tracking-wider text-cream uppercase">
                                        Booking Instan
                                    </span>
                                </div>
                            </div>
                            <div className="mt-3 grid grid-cols-2 gap-2 border-t border-ink/10 pt-2.5 font-body text-xs text-stone">
                                <div className="flex items-center gap-1.5">
                                    <CheckCircle2 className="size-3.5 text-emerald-600" />
                                    <span>Bebas bentrok jadwal</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <ShieldCheck className="size-3.5 text-cobalt" />
                                    <span>Tiket aktif di HP</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="border-t border-ink/10 pt-4 font-data text-xs text-stone">
                        AdaTempat © 2026 · Cari &amp; Booking Tempat Olahraga,
                        Kerja, &amp; Acara
                    </div>
                </aside>

                {/* Form Area (Desktop Right Column & Full Mobile) */}
                <main
                    id="auth-form"
                    tabIndex={-1}
                    className="flex flex-col items-center justify-center p-6 sm:p-10 lg:col-span-7 lg:p-12 xl:col-span-7"
                >
                    <div className="w-full max-w-md space-y-6">
                        {/* Mobile Wordmark Header */}
                        <div className="flex flex-col items-center gap-2 lg:hidden">
                            <Link
                                href={home()}
                                className="inline-flex items-center gap-2 focus-visible:outline-2 focus-visible:outline-cobalt"
                            >
                                <Wordmark className="text-3xl text-ink" />
                            </Link>
                            <span className="font-data text-xs tracking-wider text-stone uppercase">
                                Portal Akses Akun
                            </span>
                        </div>

                        {/* Card Form */}
                        <div className="rounded-control border border-ink/20 bg-cream/90 p-6 shadow-xs sm:p-8">
                            <div className="mb-6 space-y-1.5 border-b border-ink/15 pb-5">
                                <h1 className="font-display text-2xl font-bold tracking-tight text-ink sm:text-3xl">
                                    {title}
                                </h1>
                                {description && (
                                    <p className="font-body text-sm text-stone">
                                        {description}
                                    </p>
                                )}
                            </div>

                            {children}
                        </div>

                        {/* Bottom security assurance */}
                        <div className="flex items-center justify-center gap-2 text-center font-data text-xs text-stone">
                            <ShieldCheck className="size-4 text-emerald-600" />
                            <span>
                                Koneksi aman terenkripsi & data Anda terlindungi
                            </span>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    );
}
