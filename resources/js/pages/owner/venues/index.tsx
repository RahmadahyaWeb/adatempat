import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    CheckCircle2,
    Clock,
    ExternalLink,
    Globe,
    Layers,
    MapPin,
    Phone,
    Plus,
    RefreshCw,
    XCircle,
} from 'lucide-react';
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
import { dashboard } from '@/routes';
import {
    create as venueCreate,
    edit as venueEdit,
    index as venueIndex,
    publish as venuePublish,
    submitApproval as venueSubmitApproval,
} from '@/routes/owner/venues';
import { index as spaceIndex } from '@/routes/owner/venues/spaces';
import { show as publicVenueShow } from '@/routes/venues';

type VenueListItem = {
    id: number;
    name: string;
    slug: string;
    city: string;
    address: string;
    timezone: string;
    contact_phone: string | null;
    is_published: boolean;
    approval_status: 'pending' | 'approved' | 'rejected';
    rejection_reason: string | null;
    cover_url: string | null;
    spaces_count: number;
    business_name: string;
};

type PaginatedVenues = {
    data: VenueListItem[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    venues: PaginatedVenues;
    businesses: Array<{ id: number; name: string }>;
};

function formatTimezone(tz: string): string {
    switch (tz) {
        case 'Asia/Makassar':
            return 'WITA';
        case 'Asia/Jayapura':
            return 'WIT';
        default:
            return 'WIB';
    }
}

export default function OwnerVenuesIndex({ venues }: Props) {
    const { flash } = usePage<{
        flash?: { success?: string; error?: string; info?: string };
    }>().props;

    const handleTogglePublish = (slug: string) => {
        router.post(
            venuePublish({ venue: slug }).url,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const handleSubmitApproval = (slug: string) => {
        router.post(
            venueSubmitApproval({ venue: slug }).url,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const publishedCount = venues.data.filter((v) => v.is_published).length;
    const pendingCount = venues.data.filter(
        (v) => v.approval_status === 'pending',
    ).length;
    const rejectedCount = venues.data.filter(
        (v) => v.approval_status === 'rejected',
    ).length;

    return (
        <>
            <Head title="Kelola Venue & Tempat" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-8">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <span className="text-xs font-bold tracking-wider text-cobalt uppercase">
                            Portal Mitra Pemilik
                        </span>
                        <h1 className="mt-0.5 font-display text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">
                            Kelola Venue & Lokasi
                        </h1>
                        <p className="mt-1 text-sm text-stone-600 dark:text-stone-400">
                            Atur profil tempat, periksa status verifikasi
                            kurasi, dan aktifkan penayangan di marketplace
                            publik.
                        </p>
                    </div>
                    <Button
                        asChild
                        className="gap-2 bg-cobalt text-white hover:bg-cobalt-deep"
                    >
                        <Link href={venueCreate()}>
                            <Plus className="size-4" />
                            <span>Tambah Venue Baru</span>
                        </Link>
                    </Button>
                </div>

                {/* Quick Status Pill Bar */}
                {venues.total > 0 && (
                    <div className="flex flex-wrap items-center gap-2 text-xs">
                        <div className="flex items-center gap-1.5 rounded-full border border-stone-200 bg-stone-100/80 px-3 py-1 font-medium text-stone-700 dark:border-neutral-800 dark:bg-neutral-800 dark:text-stone-300">
                            <span>Total Venue:</span>
                            <span className="font-bold text-foreground">
                                {venues.total}
                            </span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-full border border-cobalt/20 bg-cobalt/5 px-3 py-1 font-medium text-cobalt dark:border-cobalt/30 dark:bg-cobalt/15 dark:text-blue-300">
                            <Globe className="size-3" />
                            <span>Tayang:</span>
                            <span className="font-bold">{publishedCount}</span>
                        </div>
                        {pendingCount > 0 && (
                            <div className="flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 font-medium text-amber-700 dark:text-amber-300">
                                <Clock className="size-3" />
                                <span>Menunggu:</span>
                                <span className="font-bold">
                                    {pendingCount}
                                </span>
                            </div>
                        )}
                        {rejectedCount > 0 && (
                            <div className="flex items-center gap-1.5 rounded-full border border-rose-500/30 bg-rose-500/10 px-3 py-1 font-medium text-rose-700 dark:text-rose-300">
                                <XCircle className="size-3" />
                                <span>Perlu Perbaikan:</span>
                                <span className="font-bold">
                                    {rejectedCount}
                                </span>
                            </div>
                        )}
                    </div>
                )}

                {/* Flash Messages */}
                {flash?.success && (
                    <div className="flex items-center gap-3 rounded-lg border border-emerald-500/30 bg-emerald-50 p-4 text-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">
                        <CheckCircle2 className="size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <p className="text-sm font-medium">{flash.success}</p>
                    </div>
                )}
                {flash?.error && (
                    <div className="flex items-center gap-3 rounded-lg border border-rose-500/30 bg-rose-50 p-4 text-rose-900 dark:bg-rose-950/30 dark:text-rose-200">
                        <XCircle className="size-5 shrink-0 text-rose-600 dark:text-rose-400" />
                        <p className="text-sm font-medium">{flash.error}</p>
                    </div>
                )}
                {flash?.info && (
                    <div className="flex items-center gap-3 rounded-lg border border-amber-500/30 bg-amber-50 p-4 text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                        <AlertCircle className="size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                        <p className="text-sm font-medium">{flash.info}</p>
                    </div>
                )}

                {/* Venues Grid */}
                {venues.data.length === 0 ? (
                    <Card className="flex flex-col items-center justify-center border-dashed border-stone-300 p-12 text-center dark:border-neutral-700">
                        <div className="mb-4 flex size-14 items-center justify-center rounded-full bg-stone-100 text-stone-500 dark:bg-neutral-800 dark:text-stone-400">
                            <Building2 className="size-7" />
                        </div>
                        <h2 className="font-display text-lg font-bold text-foreground">
                            Belum Ada Venue Terdaftar
                        </h2>
                        <p className="mt-1 max-w-md text-sm text-stone-600 dark:text-stone-400">
                            Kamu belum mendaftarkan venue. Tambahkan tempat atau
                            lapangan pertamamu agar pelanggan dapat mulai
                            memesan secara online.
                        </p>
                        <Button
                            asChild
                            className="mt-6 gap-2 bg-cobalt text-white hover:bg-cobalt-deep"
                        >
                            <Link href={venueCreate()}>
                                <Plus className="size-4" />
                                <span>Tambah Venue Sekarang</span>
                            </Link>
                        </Button>
                    </Card>
                ) : (
                    <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {venues.data.map((venue) => {
                            const isApproved =
                                venue.approval_status === 'approved';
                            const isPending =
                                venue.approval_status === 'pending';
                            const isRejected =
                                venue.approval_status === 'rejected';

                            return (
                                <Card
                                    key={venue.id}
                                    className="flex flex-col overflow-hidden border-stone-200/90 transition-shadow hover:shadow-md dark:border-neutral-800"
                                >
                                    {/* Cover Image */}
                                    <div className="relative aspect-video w-full overflow-hidden border-b border-stone-100 bg-stone-100 dark:border-neutral-800 dark:bg-neutral-800">
                                        {venue.cover_url ? (
                                            <img
                                                src={venue.cover_url}
                                                alt={`Cover foto ${venue.name}`}
                                                className="size-full object-cover"
                                            />
                                        ) : (
                                            <div className="flex size-full items-center justify-center text-stone-400">
                                                <Building2 className="size-12 stroke-[1.5]" />
                                            </div>
                                        )}

                                        {/* Status Badges Overlay */}
                                        <div className="absolute top-3 right-3 flex flex-wrap gap-1.5">
                                            {isApproved && (
                                                <Badge
                                                    variant="secondary"
                                                    className="border-emerald-600 bg-emerald-600 text-white shadow-xs"
                                                >
                                                    <CheckCircle2 className="mr-1 size-3" />
                                                    Disetujui
                                                </Badge>
                                            )}
                                            {isPending && (
                                                <Badge
                                                    variant="secondary"
                                                    className="border-amber-500 bg-amber-500 text-white shadow-xs"
                                                >
                                                    <Clock className="mr-1 size-3" />
                                                    Menunggu
                                                </Badge>
                                            )}
                                            {isRejected && (
                                                <Badge
                                                    variant="destructive"
                                                    className="shadow-xs"
                                                >
                                                    <XCircle className="mr-1 size-3" />
                                                    Ditolak
                                                </Badge>
                                            )}

                                            {isApproved &&
                                                venue.is_published && (
                                                    <Badge className="bg-cobalt text-white shadow-xs">
                                                        <Globe className="mr-1 size-3" />
                                                        Tayang
                                                    </Badge>
                                                )}
                                            {isApproved &&
                                                !venue.is_published && (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-stone-300 bg-white/95 text-stone-700 shadow-xs dark:border-neutral-700 dark:bg-neutral-900/95 dark:text-stone-300"
                                                    >
                                                        Draft
                                                    </Badge>
                                                )}
                                        </div>
                                    </div>

                                    {/* Content */}
                                    <CardHeader className="p-5 pb-3">
                                        <span className="text-xs font-semibold tracking-wider text-stone-500 uppercase">
                                            {venue.business_name}
                                        </span>
                                        <CardTitle className="font-display text-lg font-bold text-foreground">
                                            {venue.name}
                                        </CardTitle>
                                        <CardDescription className="flex items-center gap-1.5 text-xs text-stone-500">
                                            <MapPin className="size-3.5 shrink-0" />
                                            <span>
                                                {venue.city} ·{' '}
                                                {formatTimezone(venue.timezone)}
                                            </span>
                                        </CardDescription>
                                    </CardHeader>

                                    <CardContent className="flex-1 space-y-3 px-5 py-0 text-sm">
                                        <p className="line-clamp-2 text-xs text-stone-600 dark:text-stone-400">
                                            {venue.address}
                                        </p>

                                        <div className="flex items-center justify-between border-t border-stone-100 pt-2 text-xs text-stone-500 dark:border-neutral-800">
                                            <span className="flex items-center gap-1.5">
                                                <Layers className="size-3.5 text-stone-400" />
                                                <span className="font-medium text-foreground">
                                                    {venue.spaces_count}{' '}
                                                    Lapangan / Ruang
                                                </span>
                                            </span>
                                            {venue.contact_phone && (
                                                <span className="flex items-center gap-1.5">
                                                    <Phone className="size-3.5 text-stone-400" />
                                                    <span>
                                                        {venue.contact_phone}
                                                    </span>
                                                </span>
                                            )}
                                        </div>

                                        {/* Status Alerts */}
                                        {isRejected &&
                                            venue.rejection_reason && (
                                                <div className="rounded-md border border-rose-300 bg-rose-50 p-3 text-xs text-rose-900 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200">
                                                    <p className="font-semibold text-rose-950 dark:text-rose-100">
                                                        Catatan tim kurasi:
                                                    </p>
                                                    <p className="mt-0.5">
                                                        {venue.rejection_reason}
                                                    </p>
                                                </div>
                                            )}

                                        {isPending && (
                                            <div className="rounded-md border border-amber-300 bg-amber-50 p-2.5 text-xs text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                                                Sedang dalam antrean verifikasi
                                                tim AdaTempat.
                                            </div>
                                        )}
                                    </CardContent>

                                    {/* Actions Footer */}
                                    <CardFooter className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-stone-100 p-4 dark:border-neutral-800">
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                            className="h-8 border-stone-200 text-xs font-medium dark:border-neutral-700"
                                        >
                                            <Link
                                                href={spaceIndex({
                                                    venue: venue.slug,
                                                })}
                                                className="gap-1.5"
                                            >
                                                <Layers className="size-3.5 text-cobalt" />
                                                <span>
                                                    Space ({venue.spaces_count})
                                                </span>
                                            </Link>
                                        </Button>

                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                            className="h-8 border-stone-200 text-xs font-medium dark:border-neutral-700"
                                        >
                                            <Link
                                                href={venueEdit({
                                                    venue: venue.slug,
                                                })}
                                            >
                                                Ubah Data
                                            </Link>
                                        </Button>
                                        <div className="flex items-center gap-2">
                                            {isRejected && (
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    className="h-8 gap-1.5 text-xs font-semibold"
                                                    onClick={() =>
                                                        handleSubmitApproval(
                                                            venue.slug,
                                                        )
                                                    }
                                                >
                                                    <RefreshCw className="size-3" />
                                                    <span>Ajukan Ulang</span>
                                                </Button>
                                            )}

                                            {isApproved && (
                                                <>
                                                    <Button
                                                        size="sm"
                                                        variant={
                                                            venue.is_published
                                                                ? 'outline'
                                                                : 'default'
                                                        }
                                                        className={`h-8 text-xs font-medium ${
                                                            !venue.is_published
                                                                ? 'bg-cobalt text-white hover:bg-cobalt-deep'
                                                                : 'border-stone-200 dark:border-neutral-700'
                                                        }`}
                                                        onClick={() =>
                                                            handleTogglePublish(
                                                                venue.slug,
                                                            )
                                                        }
                                                    >
                                                        {venue.is_published
                                                            ? 'Nonaktifkan'
                                                            : 'Tayangkan'}
                                                    </Button>
                                                    {venue.is_published && (
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            variant="ghost"
                                                            className="h-8 px-2 text-stone-500 hover:text-stone-900 dark:hover:text-white"
                                                        >
                                                            <a
                                                                href={
                                                                    publicVenueShow(
                                                                        {
                                                                            venue: venue.slug,
                                                                        },
                                                                    ).url
                                                                }
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                title="Buka Pratinjau di Marketplace"
                                                            >
                                                                <ExternalLink className="size-4" />
                                                            </a>
                                                        </Button>
                                                    )}
                                                </>
                                            )}
                                        </div>
                                    </CardFooter>
                                </Card>
                            );
                        })}
                    </div>
                )}

                {/* Pagination */}
                {venues.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-stone-200 pt-4 dark:border-neutral-800">
                        <span className="text-xs text-stone-500">
                            Halaman {venues.current_page} dari{' '}
                            {venues.last_page} ({venues.total} total venue)
                        </span>
                        <div className="flex items-center gap-2">
                            {venues.prev_page_url && (
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="h-8 text-xs"
                                >
                                    <Link href={venues.prev_page_url}>
                                        Sebelumnya
                                    </Link>
                                </Button>
                            )}
                            {venues.next_page_url && (
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="h-8 text-xs"
                                >
                                    <Link href={venues.next_page_url}>
                                        Selanjutnya
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

OwnerVenuesIndex.layout = {
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
