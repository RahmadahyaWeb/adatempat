import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Clock,
    PackagePlus,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import { type FormEventHandler, useState } from 'react';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index as venueIndex } from '@/routes/owner/venues';
import {
    destroy as spaceDestroy,
    index as spaceIndex,
    update as spaceUpdate,
} from '@/routes/owner/venues/spaces';
import { index as addOnsIndex } from '@/routes/owner/venues/spaces/add-ons';
import { index as schedulesIndex } from '@/routes/owner/venues/spaces/schedules';

type Props = {
    venue: {
        id: number;
        name: string;
        slug: string;
        city: string;
        timezone: string;
    };
    space: {
        id: number;
        name: string;
        slug: string;
        category: string;
        capacity: number | null;
        base_price: number;
        instant_book: boolean;
        cancellation_policy: string;
        cancellation_cutoff_hours: number;
        refund_percentage: number;
        is_active: boolean;
    };
    categories: Array<{ value: string; label: string }>;
    cancellationPolicies: Array<{ value: string; label: string }>;
};

export default function OwnerSpacesEdit({
    venue,
    space,
    categories,
    cancellationPolicies,
}: Props) {
    const { data, setData, put, processing, errors } = useForm<{
        name: string;
        category: string;
        capacity: number | '';
        base_price: number | '';
        instant_book: boolean;
        cancellation_policy: string;
        cancellation_cutoff_hours: number;
        refund_percentage: number;
        is_active: boolean;
    }>({
        name: space.name,
        category: space.category,
        capacity: space.capacity ?? '',
        base_price: space.base_price,
        instant_book: space.instant_book,
        cancellation_policy: space.cancellation_policy,
        cancellation_cutoff_hours: space.cancellation_cutoff_hours,
        refund_percentage: space.refund_percentage,
        is_active: space.is_active,
    });

    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

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
        put(
            spaceUpdate({
                venue: venue.slug,
                space: space.slug,
            }).url,
        );
    };

    const handleDelete = () => {
        setIsDeleting(true);
        router.delete(
            spaceDestroy({
                venue: venue.slug,
                space: space.slug,
            }).url,
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsDeleting(false);
                    setIsDeleteDialogOpen(false);
                },
            },
        );
    };

    return (
        <>
            <Head title={`Ubah Space - ${space.name}`} />

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
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <span className="text-xs font-bold tracking-wider text-cobalt uppercase">
                            {venue.name} · Pengaturan Unit
                        </span>
                        <h1 className="mt-0.5 font-display text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">
                            Ubah Data Space: {space.name}
                        </h1>
                        <p className="mt-1 text-sm text-stone-600 dark:text-stone-400">
                            Perbarui detail informasi, tarif per slot, atau
                            status ketersediaan unit lapangan ini.
                        </p>
                    </div>

                    {/* Quick navigation to schedule or add-ons */}
                    <div className="flex items-center gap-2">
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="gap-1.5 border-stone-200 text-xs dark:border-neutral-700"
                        >
                            <Link
                                href={schedulesIndex({
                                    venue: venue.slug,
                                    space: space.slug,
                                })}
                            >
                                <Clock className="size-3.5 text-cobalt" />
                                <span>Jadwal Operasional</span>
                            </Link>
                        </Button>
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="gap-1.5 border-stone-200 text-xs dark:border-neutral-700"
                        >
                            <Link
                                href={addOnsIndex({
                                    venue: venue.slug,
                                    space: space.slug,
                                })}
                            >
                                <PackagePlus className="size-3.5 text-amber-600" />
                                <span>Add-On</span>
                            </Link>
                        </Button>
                    </div>
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
                                        <InputError
                                            message={errors.refund_percentage}
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Danger Zone */}
                        <Card className="border-rose-200 bg-rose-50/30 dark:border-rose-900/40 dark:bg-rose-950/20">
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 font-display text-base font-bold text-rose-900 dark:text-rose-200">
                                    <Trash2 className="size-4" />
                                    <span>Hapus Unit Space Ini</span>
                                </CardTitle>
                                <CardDescription className="text-xs text-rose-700/80 dark:text-rose-300/80">
                                    Menghapus space akan menghapus seluruh data
                                    jadwal operasional dan add-on terkait. Jika
                                    space sudah memiliki riwayat pemesanan,
                                    penghapusan akan ditolak otomatis.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => setIsDeleteDialogOpen(true)}
                                    className="gap-2"
                                >
                                    <Trash2 className="size-4" />
                                    <span>Hapus Space Ini</span>
                                </Button>
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
                                        : 'Simpan Perubahan'}
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

            {/* Delete Dialog */}
            <Dialog
                open={isDeleteDialogOpen}
                onOpenChange={setIsDeleteDialogOpen}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus Space {space.name}?</DialogTitle>
                        <DialogDescription className="text-xs text-stone-600 dark:text-stone-400">
                            Tindakan ini tidak dapat dibatalkan. Jika space ini
                            sudah memiliki riwayat pesanan aktif, sistem akan
                            menolak penghapusan untuk menjaga catatan transaksi
                            pelanggan.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setIsDeleteDialogOpen(false)}
                            disabled={isDeleting}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={handleDelete}
                            disabled={isDeleting}
                        >
                            {isDeleting ? 'Menghapus...' : 'Ya, Hapus Space'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

OwnerSpacesEdit.layout = {
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
