import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, ShieldCheck, Sparkles } from 'lucide-react';
import { type FormEventHandler } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index as venueIndex } from '@/routes/owner/venues';
import {
    index as spaceIndex,
    store as spaceStore,
} from '@/routes/owner/venues/spaces';

type Props = {
    venue: {
        id: number;
        name: string;
        slug: string;
        city: string;
        timezone: string;
    };
    categories: Array<{ value: string; label: string }>;
    cancellationPolicies: Array<{ value: string; label: string }>;
};

export default function OwnerSpacesCreate({
    venue,
    categories,
    cancellationPolicies,
}: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        category: categories[0]?.value ?? 'badminton',
        capacity: '' as number | '',
        base_price: '' as number | '',
        instant_book: true,
        cancellation_policy: 'free_cancellation',
        cancellation_cutoff_hours: 6,
        refund_percentage: 100,
        is_active: true,
    });

    const handlePolicyChange = (policy: string) => {
        let cutoff = data.cancellation_cutoff_hours;
        let refund = data.refund_percentage;

        if (policy === 'free_cancellation') {
            cutoff = 6;
            refund = 100;
        } else if (policy === 'partial_refund') {
            cutoff = 12;
            refund = 50;
        } else if (policy === 'non_refundable') {
            cutoff = 0;
            refund = 0;
        }

        setData((prev) => ({
            ...prev,
            cancellation_policy: policy,
            cancellation_cutoff_hours: cutoff,
            refund_percentage: refund,
        }));
    };

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        post(spaceStore({ venue: venue.slug }).url);
    };

    return (
        <>
            <Head title={`Tambah Space Baru - ${venue.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-8">
                {/* Back Link */}
                <div>
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="-ml-2 gap-1.5 text-xs text-stone-600 hover:text-foreground dark:text-stone-400"
                    >
                        <Link href={spaceIndex({ venue: venue.slug })}>
                            <ArrowLeft className="size-3.5" />
                            <span>Kembali ke Daftar Space</span>
                        </Link>
                    </Button>
                </div>

                {/* Header */}
                <div>
                    <span className="text-xs font-bold tracking-wider text-cobalt uppercase">
                        {venue.name} · Unit Baru
                    </span>
                    <h1 className="mt-0.5 font-display text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">
                        Tambah Unit Space / Lapangan
                    </h1>
                    <p className="mt-1 text-sm text-stone-600 dark:text-stone-400">
                        Isi spesifikasi lapangan, harga dasar sewa per slot,
                        serta aturan pembatalan untuk unit ini.
                    </p>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="grid gap-6 lg:grid-cols-3"
                >
                    {/* Main Form Fields */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* Detail Space Card */}
                        <Card className="border-stone-200/90 dark:border-neutral-800">
                            <CardHeader className="pb-4">
                                <CardTitle className="font-display text-base font-bold">
                                    Informasi Dasar Space
                                </CardTitle>
                                <CardDescription className="text-xs text-stone-500">
                                    Nama dan kategori menentukan bagaimana
                                    lapangan ini muncul di pencarian katalog.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-5">
                                {/* Nama Space */}
                                <div className="space-y-2">
                                    <Label
                                        htmlFor="name"
                                        className="text-xs font-semibold"
                                    >
                                        Nama Space / Lapangan{' '}
                                        <span className="text-rose-600 dark:text-rose-400">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        placeholder="Contoh: Lapangan Futsal 1 (Vinyl Pro)"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        className="h-10 border-stone-200 text-sm shadow-2xs focus-visible:border-cobalt focus-visible:ring-1 focus-visible:ring-cobalt dark:border-neutral-700"
                                        aria-invalid={
                                            errors.name ? 'true' : 'false'
                                        }
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                {/* Kategori & Kapasitas */}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="category"
                                            className="text-xs font-semibold"
                                        >
                                            Kategori Ruang{' '}
                                            <span className="text-rose-600 dark:text-rose-400">
                                                *
                                            </span>
                                        </Label>
                                        <select
                                            id="category"
                                            value={data.category}
                                            onChange={(e) =>
                                                setData(
                                                    'category',
                                                    e.target.value,
                                                )
                                            }
                                            className="flex h-10 w-full rounded-md border border-stone-200 bg-background px-3 py-2 text-sm shadow-2xs focus-visible:border-cobalt focus-visible:ring-1 focus-visible:ring-cobalt focus-visible:outline-none dark:border-neutral-700"
                                        >
                                            {categories.map((c) => (
                                                <option
                                                    key={c.value}
                                                    value={c.value}
                                                >
                                                    {c.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.category} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="capacity"
                                            className="text-xs font-semibold"
                                        >
                                            Kapasitas Maksimum (Orang)
                                        </Label>
                                        <Input
                                            id="capacity"
                                            type="number"
                                            min={1}
                                            placeholder="Opsional, contoh: 12"
                                            value={data.capacity}
                                            onChange={(e) =>
                                                setData(
                                                    'capacity',
                                                    e.target.value === ''
                                                        ? ''
                                                        : parseInt(
                                                              e.target.value,
                                                              10,
                                                          ),
                                                )
                                            }
                                            className="h-10 border-stone-200 text-sm shadow-2xs focus-visible:border-cobalt focus-visible:ring-1 focus-visible:ring-cobalt dark:border-neutral-700"
                                        />
                                        <InputError message={errors.capacity} />
                                    </div>
                                </div>

                                {/* Harga Dasar */}
                                <div className="space-y-2">
                                    <Label
                                        htmlFor="base_price"
                                        className="text-xs font-semibold"
                                    >
                                        Harga Dasar Sewa (Rp / Slot){' '}
                                        <span className="text-rose-600 dark:text-rose-400">
                                            *
                                        </span>
                                    </Label>
                                    <div className="relative">
                                        <span className="absolute top-2.5 left-3 text-xs font-semibold text-stone-500">
                                            Rp
                                        </span>
                                        <Input
                                            id="base_price"
                                            type="number"
                                            min={0}
                                            step={1000}
                                            placeholder="150000"
                                            value={data.base_price}
                                            onChange={(e) =>
                                                setData(
                                                    'base_price',
                                                    e.target.value === ''
                                                        ? ''
                                                        : parseFloat(
                                                              e.target.value,
                                                          ),
                                                )
                                            }
                                            className="h-10 border-stone-200 pl-9 text-sm font-semibold shadow-2xs focus-visible:border-cobalt focus-visible:ring-1 focus-visible:ring-cobalt dark:border-neutral-700"
                                            aria-invalid={
                                                errors.base_price
                                                    ? 'true'
                                                    : 'false'
                                            }
                                        />
                                    </div>
                                    <p className="text-[11px] text-stone-500">
                                        Tarif per durasi slot yang diatur pada
                                        jadwal operasional (default 60 menit).
                                    </p>
                                    <InputError message={errors.base_price} />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Cancellation Policy Card */}
                        <Card className="border-stone-200/90 dark:border-neutral-800">
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2 font-display text-base font-bold">
                                    <ShieldCheck className="size-4 text-cobalt" />
                                    <span>Kebijakan Pembatalan & Refund</span>
                                </CardTitle>
                                <CardDescription className="text-xs text-stone-500">
                                    Tentukan aturan pengembalian dana jika
                                    pelanggan membatalkan pesanan sebelum jadwal
                                    main.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-5">
                                <div className="space-y-2">
                                    <Label
                                        htmlFor="cancellation_policy"
                                        className="text-xs font-semibold"
                                    >
                                        Tipe Kebijakan Pembatalan{' '}
                                        <span className="text-rose-600 dark:text-rose-400">
                                            *
                                        </span>
                                    </Label>
                                    <select
                                        id="cancellation_policy"
                                        value={data.cancellation_policy}
                                        onChange={(e) =>
                                            handlePolicyChange(e.target.value)
                                        }
                                        className="flex h-10 w-full rounded-md border border-stone-200 bg-background px-3 py-2 text-sm shadow-2xs focus-visible:border-cobalt focus-visible:ring-1 focus-visible:ring-cobalt focus-visible:outline-none dark:border-neutral-700"
                                    >
                                        {cancellationPolicies.map((p) => (
                                            <option
                                                key={p.value}
                                                value={p.value}
                                            >
                                                {p.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.cancellation_policy}
                                    />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="cancellation_cutoff_hours"
                                            className="text-xs font-semibold"
                                        >
                                            Batas Waktu Pembatalan (Jam Sebelum
                                            Main){' '}
                                            <span className="text-rose-600 dark:text-rose-400">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="cancellation_cutoff_hours"
                                            type="number"
                                            min={0}
                                            value={
                                                data.cancellation_cutoff_hours
                                            }
                                            onChange={(e) =>
                                                setData(
                                                    'cancellation_cutoff_hours',
                                                    parseInt(
                                                        e.target.value,
                                                        10,
                                                    ) || 0,
                                                )
                                            }
                                            className="h-10 border-stone-200 text-sm shadow-2xs focus-visible:border-cobalt focus-visible:ring-1 focus-visible:ring-cobalt dark:border-neutral-700"
                                        />
                                        <p className="text-[11px] text-stone-500">
                                            Contoh: 6 jam sebelum jam slot
                                            dimulai.
                                        </p>
                                        <InputError
                                            message={
                                                errors.cancellation_cutoff_hours
                                            }
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="refund_percentage"
                                            className="text-xs font-semibold"
                                        >
                                            Persentase Pengembalian Dana (%){' '}
                                            <span className="text-rose-600 dark:text-rose-400">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="refund_percentage"
                                            type="number"
                                            min={0}
                                            max={100}
                                            value={data.refund_percentage}
                                            onChange={(e) =>
                                                setData(
                                                    'refund_percentage',
                                                    parseFloat(
                                                        e.target.value,
                                                    ) || 0,
                                                )
                                            }
                                            className="h-10 border-stone-200 text-sm shadow-2xs focus-visible:border-cobalt focus-visible:ring-1 focus-visible:ring-cobalt dark:border-neutral-700"
                                        />
                                        <p className="text-[11px] text-stone-500">
                                            Contoh: 100% untuk batal gratis, 50%
                                            untuk parsial.
                                        </p>
                                        <InputError
                                            message={errors.refund_percentage}
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar Settings & Submit */}
                    <div className="space-y-6">
                        <Card className="border-stone-200/90 dark:border-neutral-800">
                            <CardHeader className="pb-3">
                                <CardTitle className="font-display text-base font-bold">
                                    Pengaturan Pemesanan
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Instant Book Toggle */}
                                <div className="flex items-start gap-3 rounded-lg border border-stone-200 p-3.5 dark:border-neutral-700">
                                    <Checkbox
                                        id="instant_book"
                                        checked={data.instant_book}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'instant_book',
                                                Boolean(checked),
                                            )
                                        }
                                        className="mt-0.5"
                                    />
                                    <div className="space-y-0.5">
                                        <Label
                                            htmlFor="instant_book"
                                            className="cursor-pointer text-xs font-semibold"
                                        >
                                            Instant Booking
                                        </Label>
                                        <p className="text-[11px] text-stone-500">
                                            Pesanan langsung otomatis
                                            terkonfirmasi begitu pembayaran QRIS
                                            berhasil.
                                        </p>
                                    </div>
                                </div>

                                {/* Active Toggle */}
                                <div className="flex items-start gap-3 rounded-lg border border-stone-200 p-3.5 dark:border-neutral-700">
                                    <Checkbox
                                        id="is_active"
                                        checked={data.is_active}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'is_active',
                                                Boolean(checked),
                                            )
                                        }
                                        className="mt-0.5"
                                    />
                                    <div className="space-y-0.5">
                                        <Label
                                            htmlFor="is_active"
                                            className="cursor-pointer text-xs font-semibold"
                                        >
                                            Status Space Aktif
                                        </Label>
                                        <p className="text-[11px] text-stone-500">
                                            Space aktif dapat dilihat dan
                                            dipesan oleh pelanggan di katalog.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Onboarding Info */}
                        <Card className="border-stone-200/90 bg-stone-50/70 dark:border-neutral-800 dark:bg-neutral-900/40">
                            <CardContent className="p-4 text-xs text-stone-600 dark:text-stone-400">
                                <div className="flex items-start gap-2.5">
                                    <Sparkles className="mt-0.5 size-4 shrink-0 text-cobalt" />
                                    <div>
                                        <p className="font-semibold text-foreground">
                                            Jadwal Otomatis Siap Pakai
                                        </p>
                                        <p className="mt-1 leading-relaxed">
                                            Setelah space dibuat, sistem
                                            otomatis menyediakan jadwal awal
                                            (08:00–22:00, slot 60 menit) yang
                                            dapat kamu sesuaikan di tab Jadwal.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Actions */}
                        <div className="space-y-2">
                            <Button
                                type="submit"
                                disabled={processing}
                                className="h-10 w-full gap-2 bg-cobalt text-white hover:bg-cobalt-deep"
                            >
                                <Check className="size-4" />
                                <span>
                                    {processing
                                        ? 'Menyimpan...'
                                        : 'Simpan Space'}
                                </span>
                            </Button>
                            <Button
                                asChild
                                variant="outline"
                                type="button"
                                className="h-10 w-full border-stone-200 text-stone-700 dark:border-neutral-700 dark:text-stone-300"
                            >
                                <Link href={spaceIndex({ venue: venue.slug })}>
                                    Batal
                                </Link>
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}

OwnerSpacesCreate.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Kelola Venue',
            href: venueIndex(),
        },
    ],
};
