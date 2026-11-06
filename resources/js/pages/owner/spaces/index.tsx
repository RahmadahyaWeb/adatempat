import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Calendar,
    Clock,
    Edit2,
    Layers,
    MapPin,
    PackagePlus,
    Plus,
    Trash2,
    Users,
    Zap,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { dashboard } from '@/routes';
import { index as venueIndex } from '@/routes/owner/venues';
import {
    create as spaceCreate,
    destroy as spaceDestroy,
    edit as spaceEdit,
} from '@/routes/owner/venues/spaces';
import { index as addOnsIndex } from '@/routes/owner/venues/spaces/add-ons';
import { index as schedulesIndex } from '@/routes/owner/venues/spaces/schedules';

type VenueSummary = {
    id: number;
    name: string;
    slug: string;
    city: string;
    timezone: string;
    is_published: boolean;
};

type SpaceListItem = {
    id: number;
    name: string;
    slug: string;
    category: string;
    category_label: string;
    capacity: number | null;
    base_price: number;
    instant_book: boolean;
    cancellation_policy: string;
    cancellation_policy_label: string;
    cancellation_cutoff_hours: number;
    refund_percentage: number;
    is_active: boolean;
    add_ons_count: number;
    bookings_count: number;
};

type PaginatedSpaces = {
    data: SpaceListItem[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    venue: VenueSummary;
    spaces: PaginatedSpaces;
};

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
}

export default function OwnerSpacesIndex({ venue, spaces }: Props) {
    const { flash } = usePage<{
        flash?: { success?: string; error?: string };
    }>().props;

    const [deleteTarget, setDeleteTarget] = useState<SpaceListItem | null>(
        null,
    );
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDelete = () => {
        if (!deleteTarget) return;

        setIsDeleting(true);
        router.delete(
            spaceDestroy({
                venue: venue.slug,
                space: deleteTarget.slug,
            }).url,
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsDeleting(false);
                    setDeleteTarget(null);
                },
            },
        );
    };

    return (
        <>
            <Head title={`Kelola Space - ${venue.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-8">
                {/* Back Link & Venue Context */}
                <div className="flex flex-col gap-2">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="-ml-2 w-fit gap-1.5 text-xs text-stone-600 hover:text-foreground dark:text-stone-400"
                    >
                        <Link href={venueIndex()}>
                            <ArrowLeft className="size-3.5" />
                            <span>Kembali ke Daftar Venue</span>
                        </Link>
                    </Button>

                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="text-xs font-bold tracking-wider text-cobalt uppercase">
                                    Unit Lapangan & Ruang
                                </span>
                                <Badge
                                    variant="outline"
                                    className="border-stone-200 text-[11px] font-normal dark:border-neutral-700"
                                >
                                    <MapPin className="mr-1 size-3 text-stone-400" />
                                    {venue.city}
                                </Badge>
                            </div>
                            <h1 className="mt-1 font-display text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">
                                {venue.name}
                            </h1>
                            <p className="mt-1 text-sm text-stone-600 dark:text-stone-400">
                                Kelola daftar space/lapangan, jadwal jam
                                operasional, dan add-on perlengkapan sewa.
                            </p>
                        </div>

                        <Button
                            asChild
                            className="gap-2 bg-cobalt text-white hover:bg-cobalt-deep"
                        >
                            <Link href={spaceCreate({ venue: venue.slug })}>
                                <Plus className="size-4" />
                                <span>Tambah Space Baru</span>
                            </Link>
                        </Button>
                    </div>
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

                {/* Spaces List */}
                {spaces.total === 0 ? (
                    <Card className="border-dashed border-stone-300 p-8 text-center dark:border-neutral-800">
                        <CardContent className="flex flex-col items-center justify-center p-6">
                            <div className="mb-4 flex size-12 items-center justify-center rounded-full bg-stone-100 text-stone-500 dark:bg-neutral-800">
                                <Layers className="size-6" />
                            </div>
                            <h3 className="font-display text-base font-bold text-foreground">
                                Belum Ada Space / Lapangan
                            </h3>
                            <p className="mt-1 max-w-sm text-xs text-stone-500">
                                Venue ini belum memiliki unit space. Tambahkan
                                lapangan, studio, atau ruangan untuk mulai
                                menerima pesanan dari pengguna.
                            </p>
                            <Button
                                asChild
                                className="mt-5 gap-2 bg-cobalt text-white hover:bg-cobalt-deep"
                            >
                                <Link href={spaceCreate({ venue: venue.slug })}>
                                    <Plus className="size-4" />
                                    <span>Tambah Space Pertama</span>
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {spaces.data.map((space) => (
                            <Card
                                key={space.id}
                                className={`flex flex-col justify-between border-stone-200/90 transition-shadow hover:shadow-xs dark:border-neutral-800 ${
                                    !space.is_active
                                        ? 'opacity-75 grayscale-20'
                                        : ''
                                }`}
                            >
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <Badge
                                                variant="secondary"
                                                className="mb-1 text-[11px] font-semibold tracking-wide uppercase"
                                            >
                                                {space.category_label}
                                            </Badge>
                                            <CardTitle className="font-display text-lg font-bold">
                                                {space.name}
                                            </CardTitle>
                                        </div>
                                        <Badge
                                            variant={
                                                space.is_active
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            className={
                                                space.is_active
                                                    ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                                                    : 'border-stone-300 text-stone-500 dark:border-neutral-700'
                                            }
                                        >
                                            {space.is_active
                                                ? 'Aktif'
                                                : 'Nonaktif'}
                                        </Badge>
                                    </div>
                                    <CardDescription className="flex flex-wrap items-center gap-3 pt-1 text-xs">
                                        <span className="font-semibold text-foreground">
                                            {formatCurrency(space.base_price)}
                                            <span className="font-normal text-stone-500">
                                                {' '}
                                                / slot
                                            </span>
                                        </span>
                                        {space.capacity && (
                                            <span className="flex items-center gap-1 text-stone-500">
                                                <Users className="size-3 text-stone-400" />
                                                <span>
                                                    Maks. {space.capacity} orang
                                                </span>
                                            </span>
                                        )}
                                    </CardDescription>
                                </CardHeader>

                                <CardContent className="space-y-3 pb-3 text-xs">
                                    <div className="flex flex-wrap items-center gap-1.5">
                                        {space.instant_book ? (
                                            <Badge
                                                variant="outline"
                                                className="border-cobalt/30 bg-cobalt/5 text-cobalt dark:border-cobalt/40 dark:bg-cobalt/15 dark:text-blue-300"
                                            >
                                                <Zap className="mr-1 size-3" />
                                                Instant Book
                                            </Badge>
                                        ) : (
                                            <Badge
                                                variant="outline"
                                                className="border-stone-200 text-stone-600 dark:border-neutral-700 dark:text-stone-400"
                                            >
                                                Konfirmasi Manual
                                            </Badge>
                                        )}

                                        <Badge
                                            variant="outline"
                                            className="border-stone-200 text-stone-600 dark:border-neutral-700 dark:text-stone-400"
                                        >
                                            {space.cancellation_policy_label}
                                        </Badge>
                                    </div>

                                    {/* Stats Counter */}
                                    <div className="grid grid-cols-2 gap-2 rounded-md border border-stone-100 bg-stone-50/70 p-2 text-stone-600 dark:border-neutral-800 dark:bg-neutral-900/40 dark:text-stone-400">
                                        <div className="flex items-center gap-2">
                                            <PackagePlus className="size-3.5 text-stone-400" />
                                            <span>
                                                <strong className="font-semibold text-foreground">
                                                    {space.add_ons_count}
                                                </strong>{' '}
                                                Add-On
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Calendar className="size-3.5 text-stone-400" />
                                            <span>
                                                <strong className="font-semibold text-foreground">
                                                    {space.bookings_count}
                                                </strong>{' '}
                                                Pemesanan
                                            </span>
                                        </div>
                                    </div>
                                </CardContent>

                                <CardFooter className="flex flex-col gap-2 border-t border-stone-100 p-3 pt-3 dark:border-neutral-800">
                                    {/* Quick Actions Matrix */}
                                    <div className="grid w-full grid-cols-2 gap-2">
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                            className="h-8 justify-center gap-1.5 border-stone-200 text-xs font-medium dark:border-neutral-700"
                                        >
                                            <Link
                                                href={schedulesIndex({
                                                    venue: venue.slug,
                                                    space: space.slug,
                                                })}
                                            >
                                                <Clock className="size-3.5 text-cobalt" />
                                                <span>Atur Jadwal</span>
                                            </Link>
                                        </Button>

                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                            className="h-8 justify-center gap-1.5 border-stone-200 text-xs font-medium dark:border-neutral-700"
                                        >
                                            <Link
                                                href={addOnsIndex({
                                                    venue: venue.slug,
                                                    space: space.slug,
                                                })}
                                            >
                                                <PackagePlus className="size-3.5 text-amber-600" />
                                                <span>
                                                    Add-On (
                                                    {space.add_ons_count})
                                                </span>
                                            </Link>
                                        </Button>
                                    </div>

                                    <div className="flex w-full items-center justify-between gap-2 pt-1">
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="sm"
                                            className="h-7 gap-1 px-2 text-xs text-stone-600 hover:text-foreground dark:text-stone-400"
                                        >
                                            <Link
                                                href={spaceEdit({
                                                    venue: venue.slug,
                                                    space: space.slug,
                                                })}
                                            >
                                                <Edit2 className="size-3" />
                                                <span>Ubah Space</span>
                                            </Link>
                                        </Button>

                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                setDeleteTarget(space)
                                            }
                                            className="h-7 gap-1 px-2 text-xs text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-rose-950/40"
                                        >
                                            <Trash2 className="size-3" />
                                            <span>Hapus</span>
                                        </Button>
                                    </div>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}

                {/* Pagination */}
                {spaces.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-stone-200 pt-4 text-xs dark:border-neutral-800">
                        <span className="text-stone-500">
                            Halaman {spaces.current_page} dari{' '}
                            {spaces.last_page} ({spaces.total} space)
                        </span>
                        <div className="flex items-center gap-2">
                            {spaces.prev_page_url && (
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="h-8 border-stone-200 text-xs dark:border-neutral-700"
                                >
                                    <Link href={spaces.prev_page_url}>
                                        Sebelumnya
                                    </Link>
                                </Button>
                            )}
                            {spaces.next_page_url && (
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="h-8 border-stone-200 text-xs dark:border-neutral-700"
                                >
                                    <Link href={spaces.next_page_url}>
                                        Berikutnya
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* Delete Confirmation Dialog */}
            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteTarget(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus Space?</DialogTitle>
                        <DialogDescription className="text-xs text-stone-600 dark:text-stone-400">
                            Apakah kamu yakin ingin menghapus space{' '}
                            <strong>{deleteTarget?.name}</strong>? Jika space
                            sudah memiliki riwayat booking, penghapusan akan
                            ditolak untuk menjaga arsip pesanan pelanggan.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setDeleteTarget(null)}
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

OwnerSpacesIndex.layout = {
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
