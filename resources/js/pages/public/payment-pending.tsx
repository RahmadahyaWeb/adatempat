import { Head, Link, router } from '@inertiajs/react';
import {
    Check,
    CheckCircle2,
    Clock,
    Copy,
    QrCode,
    ShieldCheck,
    Zap,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Breadcrumb } from '@/components/public/breadcrumb';
import { Button } from '@/components/public/button';
import { Container } from '@/components/public/container';
import { Price } from '@/components/public/price';
import { formatFullIndonesianDate, formatRupiah } from '@/lib/catalog';
import type { PaymentPendingPageProps } from '@/types/catalog';

const labelStyles = 'font-data text-xs tracking-[0.18em] text-stone uppercase';

export default function PaymentPendingPage({
    booking,
    is_demo_mode = false,
    is_confirmed = false,
}: PaymentPendingPageProps) {
    const [status, setStatus] = useState<string>(() =>
        is_confirmed || booking.status === 'confirmed'
            ? 'confirmed'
            : booking.status,
    );
    const [copied, setCopied] = useState(false);
    const [copiedToken, setCopiedToken] = useState(false);
    const [isSimulating, setIsSimulating] = useState(false);
    const [simulationError, setSimulationError] = useState<string | null>(null);

    const [timeLeftSeconds, setTimeLeftSeconds] = useState<number>(() => {
        if (!booking.expires_at) return 600;
        const diff = Math.floor(
            (new Date(booking.expires_at).getTime() - Date.now()) / 1000,
        );
        return Math.max(0, diff);
    });

    const isConfirmed = status === 'confirmed';
    const isExpired =
        status === 'expired' || (!isConfirmed && timeLeftSeconds <= 0);

    // Countdown interval for pending hold window
    useEffect(() => {
        if (isConfirmed || timeLeftSeconds <= 0) return;
        const interval = setInterval(() => {
            setTimeLeftSeconds((prev) => Math.max(0, prev - 1));
        }, 1000);
        return () => clearInterval(interval);
    }, [isConfirmed, timeLeftSeconds]);

    // Client-side polling every 3 seconds while pending
    useEffect(() => {
        if (isConfirmed || isExpired) return;

        const interval = setInterval(async () => {
            try {
                const res = await fetch(`/payment/${booking.code}/status`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!res.ok) return;

                const data = (await res.json()) as {
                    status: string;
                    is_confirmed?: boolean;
                    is_expired?: boolean;
                };

                if (data.status === 'confirmed' || data.is_confirmed) {
                    setStatus('confirmed');
                } else if (data.status === 'expired' || data.is_expired) {
                    setStatus('expired');
                }
            } catch {
                // Silently ignore network polling failures
            }
        }, 3000);

        return () => clearInterval(interval);
    }, [booking.code, isConfirmed, isExpired]);

    const formattedCountdown = useMemo(() => {
        const minutes = Math.floor(timeLeftSeconds / 60);
        const seconds = timeLeftSeconds % 60;
        return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }, [timeLeftSeconds]);

    const handleCopyCode = async () => {
        try {
            await navigator.clipboard.writeText(booking.code);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // fallback
        }
    };

    const handleCopyToken = async () => {
        try {
            await navigator.clipboard.writeText(booking.qr_checkin_token);
            setCopiedToken(true);
            setTimeout(() => setCopiedToken(false), 2000);
        } catch {
            // fallback
        }
    };

    const handleSimulatePayment = () => {
        setIsSimulating(true);
        setSimulationError(null);

        router.post(
            `/payment/${booking.code}/simulate`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setStatus('confirmed');
                },
                onError: (errors) => {
                    const message =
                        typeof errors === 'object' && errors !== null
                            ? Object.values(errors)[0]
                            : 'Gagal memproses simulasi pembayaran.';
                    setSimulationError(String(message));
                },
                onFinish: () => {
                    setIsSimulating(false);
                },
            },
        );
    };

    const pageTitle = isConfirmed
        ? 'Pembayaran Berhasil'
        : isExpired
          ? 'Pembayaran Kedaluwarsa'
          : 'Menunggu Pembayaran';

    return (
        <>
            <Head title={`${pageTitle} - ${booking.code}`} />

            <div className="pt-5 md:pt-6">
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
                            { label: 'Pembayaran' },
                        ]}
                    />
                </Container>
            </div>

            <main className="py-8 md:py-12">
                <Container>
                    {/* Header */}
                    <div className="border-b border-ink/15 pb-6">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <span className={labelStyles}>
                                    Status Pesanan
                                </span>
                                <div className="mt-1 flex items-center gap-3">
                                    <h1 className="font-display text-2xl font-bold tracking-tight text-ink md:text-3xl">
                                        {pageTitle}
                                    </h1>
                                    {isConfirmed ? (
                                        <span className="inline-flex items-center gap-1.5 rounded-none border border-emerald-600/30 bg-emerald-500/15 px-2.5 py-1 font-data text-xs font-semibold text-emerald-900">
                                            <CheckCircle2 className="size-3.5 text-emerald-700" />
                                            Terkonfirmasi
                                        </span>
                                    ) : isExpired ? (
                                        <span className="inline-flex items-center gap-1.5 rounded-none border border-stone/30 bg-sand/40 px-2.5 py-1 font-data text-xs font-semibold text-stone">
                                            <Clock className="size-3.5" />
                                            Kedaluwarsa
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1.5 rounded-none border border-amber-600/30 bg-amber-500/15 px-2.5 py-1 font-data text-xs font-semibold text-amber-900">
                                            <Clock className="size-3.5" />
                                            Pending
                                        </span>
                                    )}
                                </div>
                            </div>

                            {/* Booking Code */}
                            <div className="flex items-center gap-2 border border-ink/20 bg-cream p-2 sm:p-2.5">
                                <span className="font-data text-xs text-stone">
                                    Kode Booking:
                                </span>
                                <span className="font-data text-sm font-bold tracking-wide text-ink">
                                    {booking.code}
                                </span>
                                <button
                                    type="button"
                                    onClick={handleCopyCode}
                                    className="ml-1 inline-flex size-7 items-center justify-center text-stone hover:text-ink focus-visible:outline-2 focus-visible:outline-cobalt"
                                    aria-label="Salin kode booking"
                                >
                                    {copied ? (
                                        <Check className="size-4 text-emerald-700" />
                                    ) : (
                                        <Copy className="size-4" />
                                    )}
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Status Alert Banner */}
                    <div
                        role="status"
                        className={
                            isConfirmed
                                ? 'mt-6 flex flex-wrap items-center justify-between gap-4 border border-emerald-600/40 bg-emerald-500/10 p-4 text-ink'
                                : isExpired
                                  ? 'mt-6 flex flex-wrap items-center justify-between gap-4 border border-stone/30 bg-sand/40 p-4 text-ink'
                                  : 'mt-6 flex flex-wrap items-center justify-between gap-4 border border-amber-600/40 bg-amber-500/10 p-4 text-ink'
                        }
                    >
                        <div className="flex items-center gap-3">
                            {isConfirmed ? (
                                <CheckCircle2 className="size-5 shrink-0 text-emerald-700" />
                            ) : (
                                <Clock
                                    className={
                                        isExpired
                                            ? 'size-5 shrink-0 text-stone'
                                            : 'size-5 shrink-0 text-amber-700'
                                    }
                                />
                            )}
                            <div>
                                <p className="font-body text-sm font-semibold text-ink">
                                    {isConfirmed
                                        ? 'Hore, pembayaran kamu sudah berhasil!'
                                        : isExpired
                                          ? 'Waktu pembayaran sudah habis nih'
                                          : 'Yuk selesaikan pembayaran sebelum waktu habis!'}
                                </p>
                                <p className="font-body text-xs text-stone">
                                    {isConfirmed
                                        ? 'Booking tempat kamu sudah resmi terkonfirmasi. Tinggal tunjukkan tiket QR saat tiba di lokasi nanti.'
                                        : isExpired
                                          ? 'Batas waktu 10 menit sudah lewat. Jangan khawatir, kamu bisa langsung pilih lagi slot yang masih kosong.'
                                          : 'Slot tempat kamu lagi diamankan selama timer berjalan. Lewat batas ini, slot otomatis dilepas buat pemesan lain.'}
                                </p>
                            </div>
                        </div>

                        {isConfirmed && (
                            <div className="flex items-center gap-2 border border-emerald-700/20 bg-cream px-3 py-1.5">
                                <span className="font-data text-xs font-bold tracking-wide text-emerald-800 uppercase">
                                    Lunas (QRIS)
                                </span>
                            </div>
                        )}

                        {!isConfirmed && !isExpired && (
                            <div className="flex items-center gap-2 border border-ink/20 bg-cream px-3 py-1.5">
                                <span className="font-data text-xs text-stone">
                                    Sisa Waktu:
                                </span>
                                <span className="font-data text-lg font-bold text-amber-800 tabular-nums">
                                    {formattedCountdown}
                                </span>
                            </div>
                        )}
                    </div>

                    {/* Main Content Grid */}
                    <div className="mt-8 grid grid-cols-1 items-start gap-8 lg:grid-cols-12 lg:gap-12">
                        {/* LEFT: QRIS Card / Confirmed Ticket / Expired Screen (7/12) */}
                        <div className="space-y-6 lg:col-span-7">
                            {isConfirmed ? (
                                <section
                                    aria-labelledby="confirmed-heading"
                                    className="border border-ink/20 bg-cream p-6 md:p-8"
                                >
                                    <div className="border-b border-ink/15 pb-5 text-center">
                                        <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-700">
                                            <CheckCircle2 className="size-8" />
                                        </div>
                                        <h2
                                            id="confirmed-heading"
                                            className="mt-4 font-display text-2xl font-bold text-ink"
                                        >
                                            Tiket Reservasi Terkonfirmasi
                                        </h2>
                                        <p className="mt-1 font-body text-sm text-stone">
                                            Pembayaran lunas via QRIS. Slot
                                            tempat telah diamankan sepenuhnya
                                            untuk Anda.
                                        </p>
                                    </div>

                                    {/* QR Check-in Token Box */}
                                    <div className="my-6 border border-ink/15 bg-sand/20 p-5 text-center">
                                        <span className={labelStyles}>
                                            Kode QR Check-in Venue
                                        </span>
                                        <div className="mx-auto my-4 flex size-52 flex-col items-center justify-center border-2 border-emerald-700/40 bg-white p-3 shadow-xs">
                                            <QrCode
                                                className="size-40 text-ink"
                                                strokeWidth={1.5}
                                            />
                                            <span className="mt-1 font-data text-[10px] font-bold tracking-widest text-emerald-800 uppercase">
                                                VERIFIED TICKET
                                            </span>
                                        </div>

                                        <div className="mx-auto max-w-sm">
                                            <div className="flex items-center justify-between gap-2 border border-ink/20 bg-cream p-2">
                                                <div className="min-w-0 text-left">
                                                    <span className="block font-data text-[10px] tracking-wider text-stone uppercase">
                                                        Check-in Token
                                                    </span>
                                                    <span className="block truncate font-data text-xs font-bold text-ink">
                                                        {
                                                            booking.qr_checkin_token
                                                        }
                                                    </span>
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={handleCopyToken}
                                                    className="inline-flex size-7 shrink-0 items-center justify-center text-stone hover:text-ink focus-visible:outline-2 focus-visible:outline-cobalt"
                                                    aria-label="Salin check-in token"
                                                >
                                                    {copiedToken ? (
                                                        <Check className="size-4 text-emerald-700" />
                                                    ) : (
                                                        <Copy className="size-4" />
                                                    )}
                                                </button>
                                            </div>
                                        </div>
                                        <p className="mt-3 font-body text-xs text-stone">
                                            Tunjukkan kode QR ini kepada
                                            resepsionis atau staf venue saat
                                            tiba di lokasi.
                                        </p>
                                    </div>

                                    {/* Action Navigation */}
                                    <div className="flex flex-col gap-3 pt-2 sm:flex-row">
                                        <Button
                                            asChild
                                            className="min-h-11 flex-1 text-sm font-semibold"
                                        >
                                            <Link
                                                href={`/tickets/${booking.code}`}
                                            >
                                                Buka Tiket Masuk
                                            </Link>
                                        </Button>
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="min-h-11 flex-1 text-sm font-semibold"
                                        >
                                            <Link href="/">
                                                Kembali ke Beranda
                                            </Link>
                                        </Button>
                                    </div>
                                </section>
                            ) : isExpired ? (
                                <section
                                    aria-labelledby="expired-heading"
                                    className="border border-ink/20 bg-cream p-8 text-center"
                                >
                                    <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-sand/60 text-stone">
                                        <Clock className="size-7" />
                                    </div>
                                    <h2
                                        id="expired-heading"
                                        className="mt-4 font-display text-2xl font-bold text-ink"
                                    >
                                        Waktu Pembayaran Habis
                                    </h2>
                                    <p className="mx-auto mt-2 max-w-md font-body text-sm text-stone">
                                        Batas waktu pembayaran 10 menit telah
                                        habis. Slot yang Anda pilih telah
                                        otomatis dilepas agar dapat dipesan
                                        kembali.
                                    </p>
                                    <div className="mt-6 flex justify-center">
                                        <Button
                                            asChild
                                            className="min-h-11 px-8 text-sm font-semibold"
                                        >
                                            <Link
                                                href={`/spaces/${booking.space.slug}`}
                                            >
                                                Pesan Ulang Tempat
                                            </Link>
                                        </Button>
                                    </div>
                                </section>
                            ) : (
                                <>
                                    <section
                                        aria-labelledby="qris-payment-heading"
                                        className="border border-ink/20 bg-cream p-6 text-center"
                                    >
                                        <div className="border-b border-ink/15 pb-4">
                                            <div className="mx-auto flex w-fit items-center gap-2 rounded-xs bg-ink px-3 py-1 text-cream">
                                                <QrCode className="size-4" />
                                                <span className="font-display text-xs font-bold tracking-wider uppercase">
                                                    QRIS Pembayaran Resmi
                                                </span>
                                            </div>
                                            <h2
                                                id="qris-payment-heading"
                                                className="mt-3 font-display text-xl font-bold text-ink"
                                            >
                                                Pindai QRIS untuk Membayar
                                            </h2>
                                            <p className="mt-1 font-body text-xs text-stone">
                                                Scan dengan BCA, Mandiri, BRI,
                                                BNI, GoPay, OVO, Dana, atau
                                                LinkAja
                                            </p>
                                        </div>

                                        {/* QR Code Container */}
                                        <div className="mx-auto my-6 flex size-56 flex-col items-center justify-center border-2 border-dashed border-ink/30 bg-sand/30 p-4">
                                            <div className="relative flex size-44 items-center justify-center border border-ink/40 bg-white p-2 shadow-xs">
                                                <QrCode
                                                    className="size-36 text-ink"
                                                    strokeWidth={1.5}
                                                />
                                                <div className="absolute flex size-10 items-center justify-center rounded-none bg-cobalt text-[9px] font-bold text-white shadow-xs">
                                                    ADT
                                                </div>
                                            </div>
                                        </div>

                                        {/* Total Amount */}
                                        <div className="border-t border-ink/15 pt-4">
                                            <span className="font-data text-xs tracking-wider text-stone uppercase">
                                                Total Tagihan
                                            </span>
                                            <p className="mt-1 font-display text-3xl font-bold text-ink">
                                                {formatRupiah(
                                                    booking.total_amount,
                                                )}
                                            </p>
                                            <p className="mt-0.5 font-data text-xs text-stone">
                                                Biaya layanan platform: Rp 0
                                                (Gratis)
                                            </p>
                                        </div>
                                    </section>

                                    {/* Local Demo Simulator Widget */}
                                    {is_demo_mode && (
                                        <section
                                            aria-labelledby="demo-simulator-heading"
                                            className="border border-amber-600/40 bg-amber-500/10 p-5 md:p-6"
                                        >
                                            <div className="flex items-start gap-3">
                                                <Zap className="mt-0.5 size-5 shrink-0 text-amber-800" />
                                                <div className="flex-1">
                                                    <h3
                                                        id="demo-simulator-heading"
                                                        className="font-display text-base font-bold text-ink"
                                                    >
                                                        Mode Pengujian Lokal
                                                    </h3>
                                                    <p className="mt-1 font-body text-xs text-stone">
                                                        Simulasikan verifikasi
                                                        pembayaran sukses
                                                        seketika tanpa perlu
                                                        memindai QRIS asli
                                                        dengan aplikasi
                                                        perbankan.
                                                    </p>

                                                    {simulationError && (
                                                        <p className="mt-2 font-body text-xs font-medium text-rose-700">
                                                            {simulationError}
                                                        </p>
                                                    )}

                                                    <div className="mt-4">
                                                        <Button
                                                            type="button"
                                                            onClick={
                                                                handleSimulatePayment
                                                            }
                                                            disabled={
                                                                isSimulating
                                                            }
                                                            className="min-h-10 text-xs font-semibold"
                                                        >
                                                            {isSimulating ? (
                                                                'Memproses Verifikasi...'
                                                            ) : (
                                                                <>
                                                                    <Zap className="mr-1.5 size-3.5" />
                                                                    Simulasikan
                                                                    Pembayaran
                                                                    Berhasil
                                                                </>
                                                            )}
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>
                                    )}

                                    {/* Payment Step-by-Step Instructions */}
                                    <section
                                        aria-labelledby="instructions-heading"
                                        className="border border-ink/20 bg-cream p-5 md:p-6"
                                    >
                                        <h3
                                            id="instructions-heading"
                                            className="font-display text-base font-bold text-ink"
                                        >
                                            Cara Pembayaran:
                                        </h3>
                                        <ol className="mt-4 space-y-3 font-body text-sm text-ink">
                                            <li className="flex items-start gap-3">
                                                <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-ink text-xs font-bold text-cream">
                                                    1
                                                </span>
                                                <span>
                                                    Buka aplikasi perbankan
                                                    (BCA, Mandiri, BRI, BNI)
                                                    atau dompet digital (GoPay,
                                                    OVO, Dana, ShopeePay) di
                                                    ponsel Anda.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-3">
                                                <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-ink text-xs font-bold text-cream">
                                                    2
                                                </span>
                                                <span>
                                                    Pilih menu{' '}
                                                    <strong>
                                                        Scan / Bayar via QRIS
                                                    </strong>{' '}
                                                    pada layar utama aplikasi.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-3">
                                                <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-ink text-xs font-bold text-cream">
                                                    3
                                                </span>
                                                <span>
                                                    Arahkan kamera ke kode QR di
                                                    atas dan periksa nominal
                                                    tagihan sebesar{' '}
                                                    <strong>
                                                        {formatRupiah(
                                                            booking.total_amount,
                                                        )}
                                                    </strong>
                                                    .
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-3">
                                                <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-ink text-xs font-bold text-cream">
                                                    4
                                                </span>
                                                <span>
                                                    Masukkan PIN Anda untuk
                                                    menyelesaikan pembayaran.
                                                    Status pesanan akan
                                                    terverifikasi secara
                                                    otomatis.
                                                </span>
                                            </li>
                                        </ol>
                                    </section>
                                </>
                            )}
                        </div>

                        {/* RIGHT: Order Details Summary (5/12) */}
                        <div className="space-y-6 lg:col-span-5">
                            <aside
                                aria-labelledby="booking-summary-heading"
                                className="border border-ink/20 bg-sand/30 p-5 md:p-6"
                            >
                                <div className="border-b border-ink/15 pb-4">
                                    <span className={labelStyles}>
                                        Detail Booking
                                    </span>
                                    <h2
                                        id="booking-summary-heading"
                                        className="mt-1 font-display text-xl font-bold text-ink"
                                    >
                                        {booking.space.name}
                                    </h2>
                                    <p className="font-body text-xs text-stone">
                                        {booking.venue.name} ·{' '}
                                        {booking.venue.city}
                                    </p>
                                </div>

                                {/* Schedule */}
                                <div className="mt-4 space-y-2 border-b border-ink/15 pb-4 text-sm">
                                    <div className="flex items-baseline justify-between">
                                        <span className="font-body text-xs text-stone">
                                            Tanggal
                                        </span>
                                        <span className="font-body text-xs font-semibold text-ink">
                                            {formatFullIndonesianDate(
                                                booking.booking_date,
                                            )}
                                        </span>
                                    </div>
                                    <div className="flex items-baseline justify-between">
                                        <span className="font-body text-xs text-stone">
                                            Waktu
                                        </span>
                                        <span className="font-body text-xs font-semibold text-ink tabular-nums">
                                            {booking.start_time} –{' '}
                                            {booking.end_time}
                                        </span>
                                    </div>
                                    <div className="flex items-baseline justify-between">
                                        <span className="font-body text-xs text-stone">
                                            Pemesan
                                        </span>
                                        <span className="font-body text-xs text-ink">
                                            {booking.customer_name} (
                                            {booking.customer_phone})
                                        </span>
                                    </div>
                                </div>

                                {/* Pricing Breakdown */}
                                <div className="mt-4 space-y-2.5 text-sm">
                                    <div className="flex items-baseline justify-between">
                                        <span className="font-body text-stone">
                                            Biaya Sewa Unit
                                        </span>
                                        <Price
                                            amount={booking.subtotal_amount}
                                            className="font-medium text-ink"
                                        />
                                    </div>

                                    {booking.addOns.map((addon, idx) => (
                                        <div
                                            key={idx}
                                            className="flex items-baseline justify-between text-xs"
                                        >
                                            <span className="font-body text-stone">
                                                {addon.name} (x{addon.quantity})
                                            </span>
                                            <Price
                                                amount={addon.total_price}
                                                className="font-medium text-ink"
                                            />
                                        </div>
                                    ))}

                                    <div className="flex items-baseline justify-between text-xs">
                                        <span className="font-body text-stone">
                                            Biaya Layanan Platform
                                        </span>
                                        <span className="font-data font-semibold text-emerald-700">
                                            Rp 0 (Gratis)
                                        </span>
                                    </div>

                                    <div className="flex items-baseline justify-between border-t border-ink/15 pt-3">
                                        <span className="font-display text-base font-bold text-ink">
                                            Total Tagihan
                                        </span>
                                        <span className="font-display text-xl font-bold text-ink tabular-nums">
                                            {formatRupiah(booking.total_amount)}
                                        </span>
                                    </div>
                                </div>

                                <div className="mt-6 border-t border-ink/15 pt-5">
                                    <div className="flex items-center gap-2 rounded-none border border-emerald-600/30 bg-emerald-500/10 p-3 text-emerald-900">
                                        <ShieldCheck className="size-4 shrink-0 text-emerald-700" />
                                        <p className="font-body text-xs">
                                            {isConfirmed
                                                ? 'Jadwal sudah aman terkunci buat kamu. Siap-siap berkegiatan di venue!'
                                                : 'Tiket QR bakal langsung aktif dan tersimpan di akunmu begitu pembayaran beres.'}
                                        </p>
                                    </div>

                                    <div className="mt-4">
                                        <Button
                                            asChild
                                            variant={
                                                isExpired
                                                    ? 'primary'
                                                    : 'outline'
                                            }
                                            className="min-h-11 w-full text-xs font-semibold"
                                        >
                                            <Link
                                                href={
                                                    isConfirmed
                                                        ? '/'
                                                        : isExpired
                                                          ? `/spaces/${booking.space.slug}`
                                                          : `/spaces/${booking.space.slug}`
                                                }
                                            >
                                                {isConfirmed
                                                    ? 'Kembali ke Beranda'
                                                    : isExpired
                                                      ? 'Pilih Ulang Tempat'
                                                      : 'Kembali ke Detail Tempat'}
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            </aside>
                        </div>
                    </div>
                </Container>
            </main>
        </>
    );
}
