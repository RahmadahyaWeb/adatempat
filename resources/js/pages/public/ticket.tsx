import { Head, Link } from '@inertiajs/react';
import {
    Check,
    CheckCircle2,
    Copy,
    Home,
    MapPin,
    Phone,
    Printer,
    QrCode,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import { Breadcrumb } from '@/components/public/breadcrumb';
import { Button } from '@/components/public/button';
import { Container } from '@/components/public/container';
import { Price } from '@/components/public/price';
import { formatFullIndonesianDate } from '@/lib/catalog';
import type { TicketPageProps } from '@/types/catalog';

const labelStyles = 'font-data text-xs tracking-[0.18em] text-stone uppercase';

export default function TicketPage({ booking }: TicketPageProps) {
    const [copiedToken, setCopiedToken] = useState(false);
    const [copiedCode, setCopiedCode] = useState(false);

    const isCheckedIn = booking.status === 'checked_in';

    const handleCopyToken = async () => {
        try {
            await navigator.clipboard.writeText(booking.qr_checkin_token);
            setCopiedToken(true);
            setTimeout(() => setCopiedToken(false), 2000);
        } catch {
            // fallback
        }
    };

    const handleCopyCode = async () => {
        try {
            await navigator.clipboard.writeText(booking.code);
            setCopiedCode(true);
            setTimeout(() => setCopiedCode(false), 2000);
        } catch {
            // fallback
        }
    };

    const handlePrint = () => {
        window.print();
    };

    return (
        <>
            <Head title={`Tiket Digital - ${booking.code}`}>
                <meta name="robots" content="noindex, nofollow" />
            </Head>

            {/* Breadcrumb (hidden on print) */}
            <div className="pt-5 md:pt-6 print:hidden">
                <Container>
                    <Breadcrumb
                        items={[
                            { label: 'Cari venue', href: '/spaces' },
                            {
                                label: booking.venue.name,
                                href: `/v/${booking.venue.slug}`,
                            },
                            {
                                label: booking.space.name,
                                href: `/spaces/${booking.space.slug}`,
                            },
                            { label: 'Tiket Digital' },
                        ]}
                    />
                </Container>
            </div>

            <main className="py-8 md:py-12">
                <Container>
                    {/* Page Header (hidden on print) */}
                    <div className="border-b border-ink/15 pb-6 print:hidden">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <span className={labelStyles}>
                                    Tiket Digital Kamu
                                </span>
                                <div className="mt-1 flex items-center gap-3">
                                    <h1 className="font-display text-2xl font-bold tracking-tight text-ink md:text-3xl">
                                        Tiket Masuk Venue
                                    </h1>
                                    {isCheckedIn ? (
                                        <span className="inline-flex items-center gap-1.5 rounded-none border border-cobalt/30 bg-cobalt/10 px-2.5 py-1 font-data text-xs font-semibold text-cobalt">
                                            <CheckCircle2 className="size-3.5" />
                                            Sudah Check-in
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1.5 rounded-none border border-emerald-600/30 bg-emerald-500/15 px-2.5 py-1 font-data text-xs font-semibold text-emerald-900">
                                            <CheckCircle2 className="size-3.5 text-emerald-700" />
                                            Lunas · Siap Pakai
                                        </span>
                                    )}
                                </div>
                            </div>

                            {/* Print & Action Buttons */}
                            <div className="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handlePrint}
                                    className="min-h-10 text-xs font-semibold"
                                >
                                    <Printer className="mr-1.5 size-3.5" />
                                    Cetak Tiket
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="min-h-10 text-xs font-semibold"
                                >
                                    <Link href="/">
                                        <Home className="mr-1.5 size-3.5" />
                                        Beranda
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* Main Ticket Pass Card */}
                    <div className="mx-auto mt-8 max-w-3xl">
                        <article
                            aria-labelledby="ticket-title"
                            className="border-2 border-ink bg-cream p-6 shadow-sm md:p-8"
                        >
                            {/* Ticket Brand & Status Header */}
                            <div className="flex flex-wrap items-center justify-between gap-4 border-b border-ink/20 pb-5">
                                <div>
                                    <span className="font-display text-lg font-extrabold tracking-tight text-ink uppercase">
                                        AdaTempat Pass
                                    </span>
                                    <p className="font-data text-xs text-stone">
                                        Tiket Masuk Digital Terverifikasi
                                    </p>
                                </div>
                                <div className="flex items-center gap-2 border border-ink/20 bg-sand/40 px-3 py-1.5">
                                    <span className="font-data text-xs text-stone">
                                        Kode:
                                    </span>
                                    <span className="font-data text-xs font-bold text-ink">
                                        {booking.code}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={handleCopyCode}
                                        className="ml-1 inline-flex size-6 items-center justify-center text-stone hover:text-ink focus-visible:outline-2 focus-visible:outline-cobalt"
                                        aria-label="Salin kode booking"
                                    >
                                        {copiedCode ? (
                                            <Check className="size-3.5 text-emerald-700" />
                                        ) : (
                                            <Copy className="size-3.5" />
                                        )}
                                    </button>
                                </div>
                            </div>

                            {/* Venue & Schedule Section */}
                            <div className="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div>
                                    <span className={labelStyles}>
                                        Venue &amp; Ruangan
                                    </span>
                                    <h2
                                        id="ticket-title"
                                        className="mt-1 font-display text-xl font-bold text-ink"
                                    >
                                        {booking.space.name}
                                    </h2>
                                    <p className="mt-0.5 font-body text-sm font-semibold text-ink">
                                        {booking.venue.name}
                                    </p>
                                    <p className="mt-1 flex items-start gap-1.5 font-body text-xs text-stone">
                                        <MapPin className="mt-0.5 size-3.5 shrink-0 text-stone" />
                                        <span>
                                            {booking.venue.address},{' '}
                                            {booking.venue.city}
                                        </span>
                                    </p>
                                    {booking.venue.contact_phone && (
                                        <p className="mt-1 flex items-center gap-1.5 font-body text-xs text-stone">
                                            <Phone className="size-3.5 shrink-0 text-stone" />
                                            <span>
                                                {booking.venue.contact_phone}
                                            </span>
                                        </p>
                                    )}
                                </div>

                                <div className="border-t border-ink/15 pt-4 md:border-t-0 md:border-l md:pt-0 md:pl-6">
                                    <span className={labelStyles}>
                                        Jadwal Reservasi
                                    </span>
                                    <div className="mt-2 space-y-2">
                                        <div>
                                            <span className="block font-body text-xs text-stone">
                                                Tanggal Sewa
                                            </span>
                                            <span className="font-body text-sm font-bold text-ink">
                                                {formatFullIndonesianDate(
                                                    booking.booking_date,
                                                )}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="block font-body text-xs text-stone">
                                                Waktu Sesi
                                            </span>
                                            <span className="font-data text-sm font-bold text-ink tabular-nums">
                                                {booking.start_time} –{' '}
                                                {booking.end_time}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="block font-body text-xs text-stone">
                                                Nama Pemesan
                                            </span>
                                            <span className="font-body text-sm font-semibold text-ink">
                                                {booking.customer_name} (
                                                {booking.customer_phone})
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Perforated Divider */}
                            <div className="my-8 flex items-center gap-2">
                                <div className="size-3 rounded-full border-r border-ink bg-sand/30" />
                                <div className="flex-1 border-b-2 border-dashed border-ink/25" />
                                <div className="size-3 rounded-full border-l border-ink bg-sand/30" />
                            </div>

                            {/* QR Code Check-in Pass */}
                            <div className="text-center">
                                <span className={labelStyles}>
                                    Pindai di Gate Masuk Venue
                                </span>
                                <div className="mx-auto my-4 flex size-56 flex-col items-center justify-center border-2 border-dashed border-ink/40 bg-white p-3 shadow-xs">
                                    <QrCode
                                        className="size-44 text-ink"
                                        strokeWidth={1.5}
                                    />
                                    <span className="mt-1 font-data text-[10px] font-bold tracking-widest text-emerald-800 uppercase">
                                        VALID PASS
                                    </span>
                                </div>

                                {/* Token Box */}
                                <div className="mx-auto max-w-md border border-ink/20 bg-sand/30 p-2.5">
                                    <span className="block font-data text-[10px] tracking-wider text-stone uppercase">
                                        Token Check-in (64 Karakter)
                                    </span>
                                    <div className="mt-1 flex items-center justify-between gap-2">
                                        <span className="truncate font-data text-xs font-bold text-ink">
                                            {booking.qr_checkin_token}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={handleCopyToken}
                                            className="inline-flex size-7 shrink-0 items-center justify-center text-stone hover:text-ink focus-visible:outline-2 focus-visible:outline-cobalt"
                                            aria-label="Salin token check-in"
                                        >
                                            {copiedToken ? (
                                                <Check className="size-4 text-emerald-700" />
                                            ) : (
                                                <Copy className="size-4" />
                                            )}
                                        </button>
                                    </div>
                                </div>

                                <p className="mx-auto mt-3 max-w-sm font-body text-xs text-stone">
                                    Tunjukkan kode QR ini ke staf venue saat
                                    tiba ya. Gerbang masuk dibuka mulai 30 menit
                                    sebelum jadwal kamu dimulai!
                                </p>
                            </div>

                            {/* Pricing & Add-ons Summary */}
                            <div className="mt-8 border-t border-ink/15 pt-5">
                                <div className="flex flex-wrap items-baseline justify-between gap-2">
                                    <div>
                                        <span className="font-body text-xs text-stone">
                                            Status Pembayaran:
                                        </span>
                                        <span className="ml-1.5 font-data text-xs font-bold text-emerald-800 uppercase">
                                            Lunas (QRIS)
                                        </span>
                                    </div>
                                    <div className="flex items-baseline gap-2">
                                        <span className="font-body text-xs text-stone">
                                            Total Pembayaran:
                                        </span>
                                        <Price
                                            amount={booking.total_amount}
                                            className="font-display text-lg font-bold text-ink"
                                        />
                                    </div>
                                </div>

                                {booking.addOns.length > 0 && (
                                    <div className="mt-3 border-t border-dashed border-ink/10 pt-2 text-xs">
                                        <span className="font-body text-stone">
                                            Layanan Tambahan:{' '}
                                        </span>
                                        <span className="font-body font-medium text-ink">
                                            {booking.addOns
                                                .map(
                                                    (a) =>
                                                        `${a.name} (${a.quantity}x)`,
                                                )
                                                .join(', ')}
                                        </span>
                                    </div>
                                )}
                            </div>

                            {/* Footer Notice */}
                            <div className="mt-6 flex items-center gap-2 border-t border-ink/15 pt-4 text-xs text-stone">
                                <ShieldCheck className="size-4 shrink-0 text-emerald-700" />
                                <span>
                                    Tiket resmi AdaTempat · Tunjukkan langsung
                                    dari HP kamu saat tiba di lokasi venue.
                                </span>
                            </div>
                        </article>
                    </div>
                </Container>
            </main>
        </>
    );
}
