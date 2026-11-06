import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    Check,
    Clock,
    Info,
    Minus,
    Plus,
    QrCode,
    ShieldCheck,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { Breadcrumb } from '@/components/public/breadcrumb';
import { Button } from '@/components/public/button';
import { Container } from '@/components/public/container';
import { Input } from '@/components/public/input';
import { Price } from '@/components/public/price';
import {
    formatFullIndonesianDate,
    formatRupiah,
    timezoneLabel,
} from '@/lib/catalog';
import { cn } from '@/lib/utils';
import type { CheckoutAddOn, CheckoutPageProps } from '@/types/catalog';

const labelStyles = 'font-data text-xs tracking-[0.18em] text-stone uppercase';

export default function CheckoutPage({
    space,
    venue,
    date,
    slots,
    addOns,
    customer,
    cancellationPolicy,
    initialPricing,
}: CheckoutPageProps) {
    // Quantities mapped by add_on id
    const [selectedAddOns, setSelectedAddOns] = useState<
        Record<number, number>
    >({});

    const form = useForm({
        space_id: space.id,
        date: date,
        slots: slots.map((s) => s.start_time),
        add_ons: [] as Array<{ id: number; quantity: number }>,
        customer_name: customer.name || '',
        customer_phone: customer.phone || '',
        customer_email: customer.email || '',
        notes: '',
    });

    // Time calculations
    const slotTimesLabel = useMemo(() => {
        if (slots.length === 0) return '-';
        const start = slots[0].start_time.slice(0, 5);
        const end = slots[slots.length - 1].end_time.slice(0, 5);
        return `${start} – ${end} (${initialPricing.duration_hours} jam)`;
    }, [slots, initialPricing.duration_hours]);

    const slotSubtotal = initialPricing.slot_subtotal;

    // Recalculate add-ons pricing dynamically on client
    const { addOnsSubtotal, addOnBreakdown } = useMemo(() => {
        let total = 0;
        const breakdown: Array<{
            addOn: CheckoutAddOn;
            quantity: number;
            lineTotal: number;
        }> = [];

        for (const addOn of addOns) {
            const qty = selectedAddOns[addOn.id] || 0;
            if (qty > 0) {
                let lineTotal = 0;
                if (addOn.pricing_type === 'per_hour') {
                    lineTotal =
                        addOn.price * initialPricing.duration_hours * qty;
                } else if (addOn.pricing_type === 'per_item') {
                    lineTotal = addOn.price * qty;
                } else {
                    // per_booking
                    lineTotal = addOn.price;
                }
                total += lineTotal;
                breakdown.push({
                    addOn,
                    quantity: addOn.pricing_type === 'per_booking' ? 1 : qty,
                    lineTotal,
                });
            }
        }

        return { addOnsSubtotal: total, addOnBreakdown: breakdown };
    }, [addOns, selectedAddOns, initialPricing.duration_hours]);

    const totalPayment = slotSubtotal + addOnsSubtotal;

    const handleAddOnQuantityChange = (addOnId: number, nextQty: number) => {
        const updated = {
            ...selectedAddOns,
            [addOnId]: Math.max(0, nextQty),
        };
        setSelectedAddOns(updated);

        // Update form add_ons payload
        const payload = Object.entries(updated)
            .filter(([, qty]) => qty > 0)
            .map(([id, quantity]) => ({
                id: Number(id),
                quantity,
            }));
        form.setData('add_ons', payload);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/checkout');
    };

    return (
        <>
            <Head title={`Checkout Pemesanan - ${space.name}`} />

            <div className="pt-5 md:pt-6">
                <Container>
                    <Breadcrumb
                        items={[
                            { label: 'Cari venue', href: '/spaces' },
                            { label: venue.name, href: `/v/${venue.slug}` },
                            {
                                label: space.name,
                                href: `/spaces/${space.slug}?date=${date}`,
                            },
                            { label: 'Checkout' },
                        ]}
                    />
                </Container>
            </div>

            <main className="py-8 md:py-12">
                <Container>
                    {/* Page Title */}
                    <div className="border-b border-ink/15 pb-6">
                        <span className={labelStyles}>Satu Langkah Lagi</span>
                        <h1 className="mt-2 font-display text-3xl font-bold tracking-tight text-ink md:text-4xl">
                            Selesaikan Booking Kamu
                        </h1>
                        <p className="mt-1 font-body text-base text-stone">
                            Cek lagi rincian jadwal dan isi data kontak kamu
                            buat tiket digital.
                        </p>
                    </div>

                    {/* Global Error Banner (e.g. slot conflict) */}
                    {(form.errors.slots ||
                        (form.errors as Record<string, string | undefined>)
                            .error) && (
                        <div
                            role="alert"
                            className="mt-6 flex flex-col gap-4 border border-amber-600/40 bg-amber-500/10 p-4 text-ink sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div className="flex items-start gap-3">
                                <AlertCircle className="mt-0.5 size-5 shrink-0 text-amber-700" />
                                <div>
                                    <p className="font-body text-sm font-semibold text-amber-900">
                                        Pemesanan Tidak Dapat Dilanjutkan
                                    </p>
                                    <p className="mt-0.5 font-body text-sm text-ink">
                                        {form.errors.slots ||
                                            (
                                                form.errors as Record<
                                                    string,
                                                    string | undefined
                                                >
                                            ).error}
                                    </p>
                                </div>
                            </div>
                            <Button
                                asChild
                                variant="outline"
                                className="shrink-0 border-amber-700/40 text-amber-950 hover:bg-amber-500/20"
                            >
                                <Link
                                    href={`/spaces/${space.slug}?date=${date}`}
                                >
                                    Pilih Slot Waktu Lain
                                </Link>
                            </Button>
                        </div>
                    )}

                    <form
                        onSubmit={handleSubmit}
                        className="mt-8 grid grid-cols-1 items-start gap-8 lg:grid-cols-12 lg:gap-12"
                    >
                        {/* LEFT COLUMN: Data Form (7/12) */}
                        <div className="space-y-8 lg:col-span-7">
                            {/* Section 1: Customer Info */}
                            <section
                                aria-labelledby="customer-info-heading"
                                className="border border-ink/20 bg-cream p-5 md:p-6"
                            >
                                <div className="flex items-center gap-2 border-b border-ink/15 pb-3">
                                    <h2
                                        id="customer-info-heading"
                                        className="font-display text-lg font-bold text-ink"
                                    >
                                        1. Data Pemesan
                                    </h2>
                                </div>
                                <p className="mt-2 font-body text-xs text-stone">
                                    Data kontak ini dipakai buat tiket digital
                                    kamu dan koordinasi saat tiba di lokasi.
                                </p>

                                <div className="mt-5 space-y-4">
                                    <div>
                                        <label
                                            htmlFor="customer_name"
                                            className="block font-body text-sm font-medium text-ink"
                                        >
                                            Nama Lengkap{' '}
                                            <span className="text-red-600">
                                                *
                                            </span>
                                        </label>
                                        <Input
                                            id="customer_name"
                                            type="text"
                                            required
                                            value={form.data.customer_name}
                                            onChange={(e) =>
                                                form.setData(
                                                    'customer_name',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Nama sesuai identitas"
                                            className={cn(
                                                'mt-1.5',
                                                form.errors.customer_name &&
                                                    'border-red-600 focus-visible:outline-red-600',
                                            )}
                                        />
                                        {form.errors.customer_name && (
                                            <p className="mt-1 font-body text-xs text-red-600">
                                                {form.errors.customer_name}
                                            </p>
                                        )}
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="customer_email"
                                            className="block font-body text-sm font-medium text-ink"
                                        >
                                            Alamat Email{' '}
                                            <span className="text-red-600">
                                                *
                                            </span>
                                        </label>
                                        <Input
                                            id="customer_email"
                                            type="email"
                                            required
                                            value={form.data.customer_email}
                                            onChange={(e) =>
                                                form.setData(
                                                    'customer_email',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="contoh@email.com"
                                            className={cn(
                                                'mt-1.5',
                                                form.errors.customer_email &&
                                                    'border-red-600 focus-visible:outline-red-600',
                                            )}
                                        />
                                        {form.errors.customer_email && (
                                            <p className="mt-1 font-body text-xs text-red-600">
                                                {form.errors.customer_email}
                                            </p>
                                        )}
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="customer_phone"
                                            className="block font-body text-sm font-medium text-ink"
                                        >
                                            Nomor WhatsApp / Telepon{' '}
                                            <span className="text-red-600">
                                                *
                                            </span>
                                        </label>
                                        <Input
                                            id="customer_phone"
                                            type="tel"
                                            required
                                            value={form.data.customer_phone}
                                            onChange={(e) =>
                                                form.setData(
                                                    'customer_phone',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="08123456789"
                                            className={cn(
                                                'mt-1.5',
                                                form.errors.customer_phone &&
                                                    'border-red-600 focus-visible:outline-red-600',
                                            )}
                                        />
                                        <p className="mt-1 font-body text-xs text-stone">
                                            Format nomor telepon Indonesia aktif
                                            (contoh: 08123456789).
                                        </p>
                                        {form.errors.customer_phone && (
                                            <p className="mt-1 font-body text-xs text-red-600">
                                                {form.errors.customer_phone}
                                            </p>
                                        )}
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="notes"
                                            className="block font-body text-sm font-medium text-ink"
                                        >
                                            Catatan Khusus{' '}
                                            <span className="font-normal text-stone">
                                                (Opsional)
                                            </span>
                                        </label>
                                        <textarea
                                            id="notes"
                                            rows={3}
                                            maxLength={500}
                                            value={form.data.notes}
                                            onChange={(e) =>
                                                form.setData(
                                                    'notes',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Permintaan penataan khusus, informasi peserta, atau kebutuhan lain."
                                            className={cn(
                                                'mt-1.5 w-full rounded-control border border-ink bg-cream p-3 font-body text-base text-ink placeholder:text-stone focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt',
                                                form.errors.notes &&
                                                    'border-red-600 focus-visible:outline-red-600',
                                            )}
                                        />
                                        {form.errors.notes && (
                                            <p className="mt-1 font-body text-xs text-red-600">
                                                {form.errors.notes}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </section>

                            {/* Section 2: Add-Ons */}
                            <section
                                aria-labelledby="addons-heading"
                                className="border border-ink/20 bg-cream p-5 md:p-6"
                            >
                                <div className="flex items-center justify-between border-b border-ink/15 pb-3">
                                    <h2
                                        id="addons-heading"
                                        className="font-display text-lg font-bold text-ink"
                                    >
                                        2. Layanan Tambahan (Add-ons)
                                    </h2>
                                    <span className={labelStyles}>
                                        Pilihan Opsional
                                    </span>
                                </div>

                                {addOns.length === 0 ? (
                                    <div className="mt-4 rounded-none border border-ink/10 bg-sand/20 p-4 text-center">
                                        <p className="font-body text-sm text-stone">
                                            Tidak ada layanan tambahan yang
                                            tersedia untuk unit ini.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="mt-4 space-y-3">
                                        {addOns.map((addOn) => {
                                            const qty =
                                                selectedAddOns[addOn.id] || 0;
                                            const isSelected = qty > 0;

                                            return (
                                                <div
                                                    key={addOn.id}
                                                    className={cn(
                                                        'flex flex-col gap-3 border p-4 transition-colors sm:flex-row sm:items-center sm:justify-between',
                                                        isSelected
                                                            ? 'border-ink bg-sand/30'
                                                            : 'border-ink/15 bg-cream hover:border-ink/40',
                                                    )}
                                                >
                                                    <div className="min-w-0">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-body font-semibold text-ink">
                                                                {addOn.name}
                                                            </span>
                                                            <span className="rounded-xs bg-ink/10 px-1.5 py-0.5 font-data text-[10px] tracking-wider text-stone uppercase">
                                                                {
                                                                    addOn.pricing_type_label
                                                                }
                                                            </span>
                                                        </div>
                                                        <p className="mt-1 font-data text-xs text-stone">
                                                            {formatRupiah(
                                                                addOn.price,
                                                            )}
                                                            {addOn.pricing_type ===
                                                                'per_hour' && (
                                                                <span>
                                                                    {' '}
                                                                    / jam
                                                                </span>
                                                            )}
                                                            {addOn.pricing_type ===
                                                                'per_item' && (
                                                                <span>
                                                                    {' '}
                                                                    / item
                                                                </span>
                                                            )}
                                                            {addOn.stock_quantity >
                                                                0 && (
                                                                <span className="ml-2">
                                                                    (Sisa stok:{' '}
                                                                    {
                                                                        addOn.stock_quantity
                                                                    }
                                                                    )
                                                                </span>
                                                            )}
                                                        </p>
                                                    </div>

                                                    {/* Control based on pricing type */}
                                                    <div className="flex items-center gap-3 self-end sm:self-center">
                                                        {addOn.pricing_type ===
                                                            'per_booking' ||
                                                        addOn.pricing_type ===
                                                            'per_hour' ? (
                                                            <label className="flex cursor-pointer items-center gap-2">
                                                                <input
                                                                    type="checkbox"
                                                                    checked={
                                                                        isSelected
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        handleAddOnQuantityChange(
                                                                            addOn.id,
                                                                            e
                                                                                .target
                                                                                .checked
                                                                                ? 1
                                                                                : 0,
                                                                        )
                                                                    }
                                                                    className="size-5 rounded-none border-ink text-cobalt focus:ring-cobalt"
                                                                />
                                                                <span className="font-body text-xs font-medium text-ink">
                                                                    {isSelected
                                                                        ? 'Dipilih'
                                                                        : 'Tambah'}
                                                                </span>
                                                            </label>
                                                        ) : (
                                                            /* per_item counter stepper */
                                                            <div className="flex items-center border border-ink/20 bg-cream">
                                                                <button
                                                                    type="button"
                                                                    disabled={
                                                                        qty <= 0
                                                                    }
                                                                    onClick={() =>
                                                                        handleAddOnQuantityChange(
                                                                            addOn.id,
                                                                            qty -
                                                                                1,
                                                                        )
                                                                    }
                                                                    className="flex size-8 items-center justify-center text-ink hover:bg-sand/40 disabled:opacity-30"
                                                                    aria-label={`Kurangi ${addOn.name}`}
                                                                >
                                                                    <Minus className="size-3.5" />
                                                                </button>
                                                                <span className="w-8 text-center font-data text-xs font-bold text-ink">
                                                                    {qty}
                                                                </span>
                                                                <button
                                                                    type="button"
                                                                    disabled={
                                                                        addOn.stock_quantity >
                                                                            0 &&
                                                                        qty >=
                                                                            addOn.stock_quantity
                                                                    }
                                                                    onClick={() =>
                                                                        handleAddOnQuantityChange(
                                                                            addOn.id,
                                                                            qty +
                                                                                1,
                                                                        )
                                                                    }
                                                                    className="flex size-8 items-center justify-center text-ink hover:bg-sand/40 disabled:opacity-30"
                                                                    aria-label={`Tambah ${addOn.name}`}
                                                                >
                                                                    <Plus className="size-3.5" />
                                                                </button>
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </section>

                            {/* Section 3: Payment Method */}
                            <section
                                aria-labelledby="payment-heading"
                                className="border border-ink/20 bg-cream p-5 md:p-6"
                            >
                                <div className="flex items-center gap-2 border-b border-ink/15 pb-3">
                                    <h2
                                        id="payment-heading"
                                        className="font-display text-lg font-bold text-ink"
                                    >
                                        3. Metode Pembayaran
                                    </h2>
                                </div>

                                <div className="mt-4 border border-ink bg-sand/30 p-4">
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center gap-2.5">
                                            <div className="flex size-8 items-center justify-center rounded-none bg-ink text-cream">
                                                <QrCode className="size-4" />
                                            </div>
                                            <div>
                                                <p className="font-display text-sm font-bold text-ink">
                                                    QRIS (Quick Response Code
                                                    Indonesian Standard)
                                                </p>
                                                <p className="font-body text-xs text-stone">
                                                    Standar Nasional Bank
                                                    Indonesia
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex size-5 items-center justify-center rounded-full bg-cobalt text-white">
                                            <Check className="size-3 stroke-[3]" />
                                        </div>
                                    </div>

                                    <div className="mt-3 border-t border-ink/10 pt-3">
                                        <p className="font-body text-xs leading-relaxed text-stone">
                                            Bisa dibayar pakai aplikasi bank apa
                                            pun (BCA, Mandiri, BRI, BNI) atau
                                            e-wallet (GoPay, OVO, Dana,
                                            ShopeePay). Kode QRIS bakal langsung
                                            muncul setelah kamu klik tombol di
                                            bawah.
                                        </p>
                                    </div>
                                </div>
                            </section>

                            {/* Section 4: Cancellation Policy */}
                            <section
                                aria-labelledby="policy-heading"
                                className="border border-ink/20 bg-cream p-5 md:p-6"
                            >
                                <div className="flex items-center gap-2 border-b border-ink/15 pb-3">
                                    <h2
                                        id="policy-heading"
                                        className="font-display text-lg font-bold text-ink"
                                    >
                                        4. Kebijakan Pembatalan
                                    </h2>
                                </div>

                                <div className="mt-4 flex items-start gap-3 bg-sand/20 p-4 text-ink">
                                    <Info className="mt-0.5 size-4 shrink-0 text-stone" />
                                    <div className="text-xs">
                                        <p className="font-semibold text-ink">
                                            {cancellationPolicy.policy_label}
                                        </p>
                                        <p className="mt-1 leading-relaxed text-stone">
                                            {cancellationPolicy.refund_percentage >
                                            0
                                                ? `Pengembalian dana ${cancellationPolicy.refund_percentage}% bila dibatalkan ${
                                                      cancellationPolicy.cutoff_hours >
                                                      0
                                                          ? `paling lambat ${cancellationPolicy.cutoff_hours} jam sebelum jadwal.`
                                                          : 'sebelum jadwal dimulai.'
                                                  }`
                                                : 'Pemesanan ini tidak dapat dibatalkan atau direfund setelah pembayaran berhasil.'}
                                        </p>
                                    </div>
                                </div>
                            </section>
                        </div>

                        {/* RIGHT COLUMN: Sticky Order Summary (5/12) */}
                        <div className="lg:col-span-5">
                            <aside
                                aria-labelledby="order-summary-heading"
                                className="sticky top-24 border border-ink/20 bg-sand/30 p-5 md:p-6"
                            >
                                <div className="border-b border-ink/15 pb-4">
                                    <span className={labelStyles}>
                                        Ringkasan Booking
                                    </span>
                                    <h2
                                        id="order-summary-heading"
                                        className="mt-1 font-display text-xl font-bold text-ink"
                                    >
                                        {space.name}
                                    </h2>
                                    <p className="font-body text-xs text-stone">
                                        {venue.name} · {venue.city}
                                    </p>
                                </div>

                                {/* Schedule Details */}
                                <div className="mt-4 space-y-2 border-b border-ink/15 pb-4 text-sm">
                                    <div className="flex items-baseline justify-between">
                                        <span className="font-body text-xs text-stone">
                                            Tanggal
                                        </span>
                                        <span className="font-body text-xs font-semibold text-ink">
                                            {formatFullIndonesianDate(date)}
                                        </span>
                                    </div>
                                    <div className="flex items-baseline justify-between">
                                        <span className="font-body text-xs text-stone">
                                            Jadwal (
                                            {timezoneLabel(venue.timezone)})
                                        </span>
                                        <span className="font-body text-xs font-semibold text-ink tabular-nums">
                                            {slotTimesLabel}
                                        </span>
                                    </div>
                                    <div className="flex items-baseline justify-between">
                                        <span className="font-body text-xs text-stone">
                                            Jumlah Slot
                                        </span>
                                        <span className="font-body text-xs text-ink tabular-nums">
                                            {slots.length} slot
                                        </span>
                                    </div>
                                </div>

                                {/* Cost Breakdown */}
                                <div className="mt-4 space-y-2.5 text-sm">
                                    <div className="flex items-baseline justify-between">
                                        <span className="font-body text-stone">
                                            Biaya Sewa Unit
                                        </span>
                                        <Price
                                            amount={slotSubtotal}
                                            className="font-medium text-ink"
                                        />
                                    </div>

                                    {addOnBreakdown.map(
                                        ({ addOn, quantity, lineTotal }) => (
                                            <div
                                                key={addOn.id}
                                                className="flex items-baseline justify-between text-xs"
                                            >
                                                <span className="font-body text-stone">
                                                    {addOn.name} (x{quantity})
                                                </span>
                                                <Price
                                                    amount={lineTotal}
                                                    className="font-medium text-ink"
                                                />
                                            </div>
                                        ),
                                    )}

                                    <div className="flex items-baseline justify-between text-xs">
                                        <span className="font-body text-stone">
                                            Biaya Layanan Platform
                                        </span>
                                        <span className="font-data font-semibold text-emerald-700">
                                            Rp 0 (Gratis)
                                        </span>
                                    </div>

                                    <div className="flex items-baseline justify-between border-t border-ink/15 pt-4">
                                        <span className="font-display text-base font-bold text-ink">
                                            Total Pembayaran
                                        </span>
                                        <span className="font-display text-2xl font-bold text-ink tabular-nums">
                                            {formatRupiah(totalPayment)}
                                        </span>
                                    </div>
                                </div>

                                {/* CTA Button */}
                                <div className="mt-6 border-t border-ink/15 pt-5">
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                        className="min-h-12 w-full bg-cobalt font-bold text-white hover:bg-cobalt/90 disabled:opacity-75"
                                    >
                                        {form.processing
                                            ? 'Memproses Booking Kamu...'
                                            : 'Lanjut ke Pembayaran QRIS'}
                                    </Button>

                                    <div className="mt-4 flex items-center justify-center gap-1.5 text-center">
                                        <Clock className="size-3.5 text-stone" />
                                        <p className="font-data text-[11px] text-stone">
                                            Slot ini diamankan 10 menit buat
                                            kamu bayar
                                        </p>
                                    </div>

                                    <div className="mt-3 flex items-center justify-center gap-1.5 border-t border-ink/10 pt-3 text-center">
                                        <ShieldCheck className="size-3.5 text-emerald-700" />
                                        <p className="font-body text-[11px] text-stone">
                                            Booking resmi dan langsung terhubung
                                            dengan pengelola venue
                                        </p>
                                    </div>
                                </div>
                            </aside>
                        </div>
                    </form>
                </Container>
            </main>
        </>
    );
}
