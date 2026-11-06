import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Edit2,
    PackagePlus,
    Plus,
    Trash2,
} from 'lucide-react';
import { type FormEventHandler, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
import { index as spaceIndex } from '@/routes/owner/venues/spaces';
import {
    destroy as addOnDestroy,
    store as addOnStore,
    update as addOnUpdate,
} from '@/routes/owner/venues/spaces/add-ons';

type AddOnItem = {
    id: number;
    name: string;
    price: number;
    pricing_type: string;
    pricing_type_label: string;
    stock_quantity: number;
    is_active: boolean;
};

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
        category_label: string;
        base_price: number;
    };
    addOns: AddOnItem[];
    pricingTypes: Array<{ value: string; label: string }>;
};

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
}

export default function OwnerSpacesAddOns({
    venue,
    space,
    addOns,
    pricingTypes,
}: Props) {
    const { flash } = usePage<{
        flash?: { success?: string; error?: string };
    }>().props;

    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingAddOn, setEditingAddOn] = useState<AddOnItem | null>(null);
    const [deletingAddOn, setDeletingAddOn] = useState<AddOnItem | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    // Form for Create
    const createForm = useForm({
        name: '',
        price: '' as number | '',
        pricing_type: pricingTypes[0]?.value ?? 'per_hour',
        stock_quantity: 1,
        is_active: true,
    });

    // Form for Edit
    const editForm = useForm({
        name: '',
        price: 0 as number | '',
        pricing_type: 'per_hour',
        stock_quantity: 1,
        is_active: true,
    });

    const handleOpenEdit = (item: AddOnItem) => {
        setEditingAddOn(item);
        editForm.setData({
            name: item.name,
            price: item.price,
            pricing_type: item.pricing_type,
            stock_quantity: item.stock_quantity,
            is_active: item.is_active,
        });
    };

    const handleCreateSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        createForm.post(
            addOnStore({
                venue: venue.slug,
                space: space.slug,
            }).url,
            {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    createForm.reset();
                },
            },
        );
    };

    const handleEditSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!editingAddOn) return;

        editForm.put(
            addOnUpdate({
                venue: venue.slug,
                space: space.slug,
                addOn: editingAddOn.id,
            }).url,
            {
                onSuccess: () => {
                    setEditingAddOn(null);
                },
            },
        );
    };

    const handleDelete = () => {
        if (!deletingAddOn) return;

        setIsDeleting(true);
        router.delete(
            addOnDestroy({
                venue: venue.slug,
                space: space.slug,
                addOn: deletingAddOn.id,
            }).url,
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsDeleting(false);
                    setDeletingAddOn(null);
                },
            },
        );
    };

    return (
        <>
            <Head title={`Kelola Add-On - ${space.name}`} />

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
                            {venue.name} · Layanan Tambahan
                        </span>
                        <h1 className="mt-0.5 font-display text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">
                            Kelola Add-On: {space.name}
                        </h1>
                        <p className="mt-1 text-sm text-stone-600 dark:text-stone-400">
                            Fasilitas atau perlengkapan sewa tambahan yang dapat
                            dipilih pelanggan saat melakukan pemesanan (contoh:
                            rompi, raket, wasit).
                        </p>
                    </div>

                    <Button
                        type="button"
                        onClick={() => setIsCreateOpen(true)}
                        className="gap-2 bg-cobalt text-white hover:bg-cobalt-deep"
                    >
                        <Plus className="size-4" />
                        <span>Tambah Add-On</span>
                    </Button>
                </div>

                {/* Flash Messages */}
                {flash?.success && (
                    <div className="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50/80 p-4 text-xs font-medium text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">
                        <span>{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50/80 p-4 text-xs font-medium text-rose-900 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200">
                        <AlertCircle className="size-4 shrink-0 text-rose-600 dark:text-rose-400" />
                        <span>{flash.error}</span>
                    </div>
                )}

                {/* Add-Ons List */}
                {addOns.length === 0 ? (
                    <Card className="border-dashed border-stone-300 p-8 text-center dark:border-neutral-800">
                        <CardContent className="flex flex-col items-center justify-center p-6">
                            <div className="mb-4 flex size-12 items-center justify-center rounded-full bg-stone-100 text-stone-500 dark:bg-neutral-800">
                                <PackagePlus className="size-6" />
                            </div>
                            <h3 className="font-display text-base font-bold text-foreground">
                                Belum Ada Add-On Ditambahkan
                            </h3>
                            <p className="mt-1 max-w-sm text-xs text-stone-500">
                                Sediakan perlengkapan tambahan seperti sewa
                                rompi tim, bola, raket cadangan, atau wasit
                                untuk menambah nilai layanan lapangan kamu.
                            </p>
                            <Button
                                type="button"
                                onClick={() => setIsCreateOpen(true)}
                                className="mt-5 gap-2 bg-cobalt text-white hover:bg-cobalt-deep"
                            >
                                <Plus className="size-4" />
                                <span>Tambah Add-On Pertama</span>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {addOns.map((item) => (
                            <Card
                                key={item.id}
                                className={`flex flex-col justify-between border-stone-200/90 transition-shadow hover:shadow-xs dark:border-neutral-800 ${
                                    !item.is_active
                                        ? 'opacity-70 grayscale-20'
                                        : ''
                                }`}
                            >
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <CardTitle className="font-display text-base font-bold">
                                            {item.name}
                                        </CardTitle>
                                        <Badge
                                            variant={
                                                item.is_active
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            className={
                                                item.is_active
                                                    ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                                                    : 'border-stone-300 text-stone-500 dark:border-neutral-700'
                                            }
                                        >
                                            {item.is_active
                                                ? 'Aktif'
                                                : 'Nonaktif'}
                                        </Badge>
                                    </div>
                                    <CardDescription className="pt-1 text-sm font-semibold text-foreground">
                                        {formatCurrency(item.price)}{' '}
                                        <span className="text-xs font-normal text-stone-500">
                                            ({item.pricing_type_label})
                                        </span>
                                    </CardDescription>
                                </CardHeader>

                                <CardContent className="space-y-2 text-xs text-stone-600 dark:text-stone-400">
                                    <div className="flex items-center justify-between rounded-md border border-stone-100 bg-stone-50/70 px-3 py-2 dark:border-neutral-800 dark:bg-neutral-900/40">
                                        <span>Stok Unit Tersedia:</span>
                                        <strong className="font-semibold text-foreground">
                                            {item.stock_quantity} item
                                        </strong>
                                    </div>
                                </CardContent>

                                <div className="flex items-center justify-end gap-2 border-t border-stone-100 p-3 dark:border-neutral-800">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => handleOpenEdit(item)}
                                        className="h-8 gap-1.5 px-2 text-xs text-stone-600 hover:text-foreground dark:text-stone-400"
                                    >
                                        <Edit2 className="size-3" />
                                        <span>Ubah</span>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setDeletingAddOn(item)}
                                        className="h-8 gap-1.5 px-2 text-xs text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-rose-950/40"
                                    >
                                        <Trash2 className="size-3" />
                                        <span>Hapus</span>
                                    </Button>
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {/* Create Add-On Modal */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent>
                    <form onSubmit={handleCreateSubmit} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>Tambah Layanan Add-On</DialogTitle>
                            <DialogDescription className="text-xs text-stone-500">
                                Tentukan nama perlengkapan, harga sewa, dan tipe
                                perhitungan tarif.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4">
                            <div className="space-y-1.5">
                                <Label
                                    htmlFor="create_name"
                                    className="text-xs font-semibold"
                                >
                                    Nama Add-On{' '}
                                    <span className="text-rose-600 dark:text-rose-400">
                                        *
                                    </span>
                                </Label>
                                <Input
                                    id="create_name"
                                    placeholder="Contoh: Sewa Rompi Tim (12 Pcs)"
                                    value={createForm.data.name}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'name',
                                            e.target.value,
                                        )
                                    }
                                    className="h-9 text-xs"
                                    aria-invalid={
                                        createForm.errors.name
                                            ? 'true'
                                            : 'false'
                                    }
                                />
                                <InputError message={createForm.errors.name} />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label
                                        htmlFor="create_price"
                                        className="text-xs font-semibold"
                                    >
                                        Harga Sewa (Rp){' '}
                                        <span className="text-rose-600 dark:text-rose-400">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="create_price"
                                        type="number"
                                        min={0}
                                        step={1000}
                                        placeholder="20000"
                                        value={createForm.data.price}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'price',
                                                e.target.value === ''
                                                    ? ''
                                                    : parseFloat(
                                                          e.target.value,
                                                      ),
                                            )
                                        }
                                        className="h-9 text-xs"
                                        aria-invalid={
                                            createForm.errors.price
                                                ? 'true'
                                                : 'false'
                                        }
                                    />
                                    <InputError
                                        message={createForm.errors.price}
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <Label
                                        htmlFor="create_stock"
                                        className="text-xs font-semibold"
                                    >
                                        Stok Unit{' '}
                                        <span className="text-rose-600 dark:text-rose-400">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="create_stock"
                                        type="number"
                                        min={1}
                                        value={createForm.data.stock_quantity}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'stock_quantity',
                                                parseInt(e.target.value, 10) ||
                                                    1,
                                            )
                                        }
                                        className="h-9 text-xs"
                                    />
                                    <InputError
                                        message={
                                            createForm.errors.stock_quantity
                                        }
                                    />
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <Label
                                    htmlFor="create_pricing_type"
                                    className="text-xs font-semibold"
                                >
                                    Tipe Perhitungan Tarif{' '}
                                    <span className="text-rose-600 dark:text-rose-400">
                                        *
                                    </span>
                                </Label>
                                <select
                                    id="create_pricing_type"
                                    value={createForm.data.pricing_type}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'pricing_type',
                                            e.target.value,
                                        )
                                    }
                                    className="flex h-9 w-full rounded-md border border-stone-200 bg-background px-3 py-1 text-xs shadow-2xs focus-visible:border-cobalt focus-visible:ring-1 focus-visible:ring-cobalt focus-visible:outline-none dark:border-neutral-700"
                                >
                                    {pricingTypes.map((t) => (
                                        <option key={t.value} value={t.value}>
                                            {t.label}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-[11px] text-stone-500">
                                    Per jam (dikalikan durasi booking) / Per
                                    item (sekali sewa per kuantitas) / Per
                                    booking (flat).
                                </p>
                            </div>

                            <div className="flex items-center gap-2 pt-1">
                                <Checkbox
                                    id="create_active"
                                    checked={createForm.data.is_active}
                                    onCheckedChange={(checked) =>
                                        createForm.setData(
                                            'is_active',
                                            Boolean(checked),
                                        )
                                    }
                                />
                                <Label
                                    htmlFor="create_active"
                                    className="cursor-pointer text-xs font-semibold"
                                >
                                    Aktifkan Add-On Ini
                                </Label>
                            </div>
                        </div>

                        <DialogFooter className="gap-2 sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setIsCreateOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                size="sm"
                                disabled={createForm.processing}
                                className="bg-cobalt text-white hover:bg-cobalt-deep"
                            >
                                {createForm.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan Add-On'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Add-On Modal */}
            <Dialog
                open={editingAddOn !== null}
                onOpenChange={(open) => {
                    if (!open) setEditingAddOn(null);
                }}
            >
                <DialogContent>
                    <form onSubmit={handleEditSubmit} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>Ubah Layanan Add-On</DialogTitle>
                            <DialogDescription className="text-xs text-stone-500">
                                Perbarui informasi harga, stok, atau status
                                ketersediaan.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4">
                            <div className="space-y-1.5">
                                <Label
                                    htmlFor="edit_name"
                                    className="text-xs font-semibold"
                                >
                                    Nama Add-On{' '}
                                    <span className="text-rose-600 dark:text-rose-400">
                                        *
                                    </span>
                                </Label>
                                <Input
                                    id="edit_name"
                                    value={editForm.data.name}
                                    onChange={(e) =>
                                        editForm.setData('name', e.target.value)
                                    }
                                    className="h-9 text-xs"
                                    aria-invalid={
                                        editForm.errors.name ? 'true' : 'false'
                                    }
                                />
                                <InputError message={editForm.errors.name} />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label
                                        htmlFor="edit_price"
                                        className="text-xs font-semibold"
                                    >
                                        Harga Sewa (Rp){' '}
                                        <span className="text-rose-600 dark:text-rose-400">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="edit_price"
                                        type="number"
                                        min={0}
                                        step={1000}
                                        value={editForm.data.price}
                                        onChange={(e) =>
                                            editForm.setData(
                                                'price',
                                                e.target.value === ''
                                                    ? ''
                                                    : parseFloat(
                                                          e.target.value,
                                                      ),
                                            )
                                        }
                                        className="h-9 text-xs"
                                        aria-invalid={
                                            editForm.errors.price
                                                ? 'true'
                                                : 'false'
                                        }
                                    />
                                    <InputError
                                        message={editForm.errors.price}
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <Label
                                        htmlFor="edit_stock"
                                        className="text-xs font-semibold"
                                    >
                                        Stok Unit{' '}
                                        <span className="text-rose-600 dark:text-rose-400">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="edit_stock"
                                        type="number"
                                        min={1}
                                        value={editForm.data.stock_quantity}
                                        onChange={(e) =>
                                            editForm.setData(
                                                'stock_quantity',
                                                parseInt(e.target.value, 10) ||
                                                    1,
                                            )
                                        }
                                        className="h-9 text-xs"
                                    />
                                    <InputError
                                        message={editForm.errors.stock_quantity}
                                    />
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <Label
                                    htmlFor="edit_pricing_type"
                                    className="text-xs font-semibold"
                                >
                                    Tipe Perhitungan Tarif{' '}
                                    <span className="text-rose-600 dark:text-rose-400">
                                        *
                                    </span>
                                </Label>
                                <select
                                    id="edit_pricing_type"
                                    value={editForm.data.pricing_type}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'pricing_type',
                                            e.target.value,
                                        )
                                    }
                                    className="flex h-9 w-full rounded-md border border-stone-200 bg-background px-3 py-1 text-xs shadow-2xs focus-visible:border-cobalt focus-visible:ring-1 focus-visible:ring-cobalt focus-visible:outline-none dark:border-neutral-700"
                                >
                                    {pricingTypes.map((t) => (
                                        <option key={t.value} value={t.value}>
                                            {t.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex items-center gap-2 pt-1">
                                <Checkbox
                                    id="edit_active"
                                    checked={editForm.data.is_active}
                                    onCheckedChange={(checked) =>
                                        editForm.setData(
                                            'is_active',
                                            Boolean(checked),
                                        )
                                    }
                                />
                                <Label
                                    htmlFor="edit_active"
                                    className="cursor-pointer text-xs font-semibold"
                                >
                                    Status Add-On Aktif
                                </Label>
                            </div>
                        </div>

                        <DialogFooter className="gap-2 sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setEditingAddOn(null)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                size="sm"
                                disabled={editForm.processing}
                                className="bg-cobalt text-white hover:bg-cobalt-deep"
                            >
                                {editForm.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan Perubahan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Modal */}
            <Dialog
                open={deletingAddOn !== null}
                onOpenChange={(open) => {
                    if (!open) setDeletingAddOn(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Hapus Add-On {deletingAddOn?.name}?
                        </DialogTitle>
                        <DialogDescription className="text-xs text-stone-600 dark:text-stone-400">
                            Jika add-on ini pernah dipesan oleh pelanggan dalam
                            riwayat booking, sistem akan menolak penghapusan
                            untuk menjaga rekam transaksi. Jika tidak ingin
                            menawarkan lagi, cukup nonaktifkan status add-on.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setDeletingAddOn(null)}
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
                            {isDeleting ? 'Menghapus...' : 'Ya, Hapus Add-On'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

OwnerSpacesAddOns.layout = {
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
