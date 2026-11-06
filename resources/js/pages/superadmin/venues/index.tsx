import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    Eye,
    Layers,
    MapPin,
    Search,
    ShieldCheck,
    XCircle,
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
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import {
    index as venuesIndex,
    show as venueShow,
} from '@/routes/superadmin/venues';

type VenueItem = {
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
    created_at: string;
    updated_at: string;
    business?: {
        id: number;
        name: string;
        user?: {
            id: number;
            name: string;
            email: string;
        } | null;
    } | null;
};

type PaginatedVenues = {
    data: VenueItem[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    venues: PaginatedVenues;
    filters: {
        status: 'pending' | 'approved' | 'rejected' | 'all';
        search: string;
    };
    stats: {
        pending_count: number;
        approved_count: number;
        rejected_count: number;
        total_count: number;
    };
};

function formatTz(tz: string): string {
    switch (tz) {
        case 'Asia/Makassar':
            return 'WITA';
        case 'Asia/Jayapura':
            return 'WIT';
        default:
            return 'WIB';
    }
}

function formatDate(iso: string): string {
    try {
        const d = new Date(iso);
        return d.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    } catch {
        return iso;
    }
}

export default function SuperadminVenuesIndex({
    venues,
    filters,
    stats,
}: Props) {
    const [searchValue, setSearchValue] = useState(filters.search || '');

    const handleTabChange = (
        status: 'pending' | 'approved' | 'rejected' | 'all',
    ) => {
        router.get(
            venuesIndex(),
            {
                status,
                ...(searchValue ? { search: searchValue } : {}),
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            venuesIndex(),
            {
                status: filters.status,
                ...(searchValue ? { search: searchValue } : {}),
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const tabs: Array<{
        key: 'pending' | 'approved' | 'rejected' | 'all';
        label: string;
        count: number;
        color?: string;
    }> = [
        {
            key: 'pending',
            label: 'Menunggu Kurasi',
            count: stats.pending_count,
            color: 'text-amber-700 bg-amber-500/10 border-amber-500/20 dark:text-amber-400',
        },
        {
            key: 'approved',
            label: 'Disetujui',
            count: stats.approved_count,
            color: 'text-emerald-700 bg-emerald-500/10 border-emerald-500/20 dark:text-emerald-400',
        },
        {
            key: 'rejected',
            label: 'Ditolak',
            count: stats.rejected_count,
            color: 'text-rose-700 bg-rose-500/10 border-rose-500/20 dark:text-rose-400',
        },
        {
            key: 'all',
            label: 'Semua Venue',
            count: stats.total_count,
        },
    ];

    return (
        <>
            <Head title="Kurasi & Persetujuan Venue" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-8">
                {/* Header Section */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="dark:text-cobalt-light inline-flex items-center gap-1.5 rounded-full bg-cobalt/10 px-2.5 py-0.5 text-xs font-bold tracking-wider text-cobalt uppercase dark:bg-cobalt/20">
                                <ShieldCheck className="size-3.5" />
                                Superadmin Gatekeeper
                            </span>
                            {stats.pending_count > 0 && (
                                <Badge className="border-amber-500/30 bg-amber-500/15 text-xs font-semibold text-amber-700 dark:text-amber-400">
                                    {stats.pending_count} butuh kurasi
                                </Badge>
                            )}
                        </div>
                        <h1 className="mt-1.5 font-display text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">
                            Antrean Kurasi Venue
                        </h1>
                        <p className="mt-1 text-sm text-stone-600 dark:text-stone-400">
                            Tinjau kelayakan data tempat, bisnis mitra,
                            ketersediaan jadwal, dan add-on sebelum tayang di
                            marketplace DOKO.
                        </p>
                    </div>
                </div>

                {/* Filter Tabs & Search Bar */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap gap-2">
                        {tabs.map((tab) => {
                            const isActive = filters.status === tab.key;
                            return (
                                <button
                                    key={tab.key}
                                    type="button"
                                    onClick={() => handleTabChange(tab.key)}
                                    className={`inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-semibold transition-colors ${
                                        isActive
                                            ? 'bg-stone-900 text-stone-50 shadow-sm dark:bg-stone-100 dark:text-stone-900'
                                            : 'border border-stone-200/80 bg-white text-stone-600 hover:bg-stone-100 dark:border-neutral-800 dark:bg-neutral-900 dark:text-stone-300 dark:hover:bg-neutral-800'
                                    }`}
                                >
                                    <span>{tab.label}</span>
                                    <span
                                        className={`rounded-full px-2 py-0.5 text-[11px] font-bold ${
                                            isActive
                                                ? 'bg-stone-700 text-stone-200 dark:bg-stone-300 dark:text-stone-800'
                                                : (tab.color ??
                                                  'bg-stone-100 text-stone-600 dark:bg-neutral-800 dark:text-stone-400')
                                        }`}
                                    >
                                        {tab.count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    <form
                        onSubmit={handleSearch}
                        className="relative w-full sm:w-72"
                    >
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-400" />
                        <Input
                            type="text"
                            placeholder="Cari venue, kota, bisnis..."
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                            className="h-9 border-stone-200/80 bg-white pr-3 pl-9 text-xs dark:border-neutral-800 dark:bg-neutral-900"
                        />
                    </form>
                </div>

                {/* Venue Grid or Empty State */}
                {venues.data.length === 0 ? (
                    <Card className="flex flex-col items-center justify-center border-dashed border-stone-300 p-12 text-center dark:border-neutral-700">
                        <div className="rounded-2xl bg-stone-100 p-4 dark:bg-neutral-800">
                            {filters.status === 'pending' ? (
                                <CheckCircle2 className="size-10 text-emerald-600 dark:text-emerald-400" />
                            ) : (
                                <Building2 className="size-10 text-stone-400" />
                            )}
                        </div>
                        <h3 className="mt-4 font-display text-lg font-bold text-stone-900 dark:text-stone-100">
                            {filters.status === 'pending'
                                ? 'Semua Pengajuan Bersih'
                                : 'Tidak Ada Venue yang Ditemukan'}
                        </h3>
                        <p className="mt-1 max-w-sm text-sm text-stone-500">
                            {filters.status === 'pending'
                                ? 'Tidak ada antrean kurasi venue baru saat ini. Semua pengajuan telah diverifikasi.'
                                : 'Coba ubah filter status atau kata kunci pencarian Anda untuk melihat venue lainnya.'}
                        </p>
                    </Card>
                ) : (
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {venues.data.map((venue) => {
                            const isPending =
                                venue.approval_status === 'pending';
                            const isApproved =
                                venue.approval_status === 'approved';
                            const isRejected =
                                venue.approval_status === 'rejected';

                            return (
                                <Card
                                    key={venue.id}
                                    className="flex flex-col justify-between overflow-hidden border-stone-200/90 shadow-sm transition-all hover:border-stone-300 dark:border-neutral-800 dark:hover:border-neutral-700"
                                >
                                    <div className="relative aspect-[16/9] w-full overflow-hidden bg-stone-100 dark:bg-neutral-900">
                                        {venue.cover_url ? (
                                            <img
                                                src={venue.cover_url}
                                                alt={venue.name}
                                                className="h-full w-full object-cover"
                                            />
                                        ) : (
                                            <div className="flex h-full w-full items-center justify-center text-stone-400">
                                                <Building2 className="size-12 stroke-[1.25]" />
                                            </div>
                                        )}
                                        <div className="absolute top-3 right-3">
                                            {isPending && (
                                                <Badge className="border-amber-500/40 bg-amber-500/90 text-xs font-semibold text-white shadow-sm backdrop-blur">
                                                    <Clock className="mr-1 size-3" />
                                                    Menunggu Kurasi
                                                </Badge>
                                            )}
                                            {isApproved && (
                                                <Badge className="border-emerald-500/40 bg-emerald-600 text-xs font-semibold text-white shadow-sm backdrop-blur">
                                                    <CheckCircle2 className="mr-1 size-3" />
                                                    Disetujui
                                                </Badge>
                                            )}
                                            {isRejected && (
                                                <Badge className="border-rose-500/40 bg-rose-600 text-xs font-semibold text-white shadow-sm backdrop-blur">
                                                    <XCircle className="mr-1 size-3" />
                                                    Ditolak
                                                </Badge>
                                            )}
                                        </div>
                                    </div>

                                    <CardHeader className="p-5 pb-2">
                                        <div className="flex items-center justify-between text-xs font-semibold text-stone-500">
                                            <span className="dark:text-cobalt-light truncate tracking-wider text-cobalt uppercase">
                                                {venue.business?.name ??
                                                    'Bisnis Mitra'}
                                            </span>
                                            <span>
                                                {venue.is_published ? (
                                                    <span className="inline-flex items-center gap-1 font-medium text-emerald-600">
                                                        <span className="size-1.5 rounded-full bg-emerald-600" />
                                                        Tayang
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 text-stone-400">
                                                        <span className="size-1.5 rounded-full bg-stone-300 dark:bg-neutral-600" />
                                                        Draft / Tak Tayang
                                                    </span>
                                                )}
                                            </span>
                                        </div>
                                        <CardTitle className="mt-1 line-clamp-1 font-display text-lg font-bold text-foreground">
                                            {venue.name}
                                        </CardTitle>
                                        <CardDescription className="flex items-center gap-1.5 text-xs text-stone-600 dark:text-stone-400">
                                            <MapPin className="size-3.5 shrink-0 text-stone-400" />
                                            <span className="truncate">
                                                {venue.city} ·{' '}
                                                {formatTz(venue.timezone)}
                                            </span>
                                        </CardDescription>
                                    </CardHeader>

                                    <CardContent className="px-5 py-2 text-xs text-stone-600 dark:text-stone-400">
                                        <div className="flex items-center gap-4 border-t border-stone-100 pt-3 dark:border-neutral-800">
                                            <div className="flex items-center gap-1.5">
                                                <Layers className="size-3.5 text-stone-400" />
                                                <span>
                                                    {venue.spaces_count} Ruangan
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-1.5">
                                                <Calendar className="size-3.5 text-stone-400" />
                                                <span>
                                                    {formatDate(
                                                        venue.created_at,
                                                    )}
                                                </span>
                                            </div>
                                        </div>
                                        {isRejected &&
                                            venue.rejection_reason && (
                                                <div className="mt-3 rounded-lg border border-rose-200 bg-rose-50/60 p-2 text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-300">
                                                    <div className="flex items-center gap-1 text-[11px] font-semibold">
                                                        <AlertCircle className="size-3 shrink-0" />
                                                        <span>
                                                            Alasan Penolakan:
                                                        </span>
                                                    </div>
                                                    <p className="mt-0.5 line-clamp-2 text-[11px]">
                                                        {venue.rejection_reason}
                                                    </p>
                                                </div>
                                            )}
                                    </CardContent>

                                    <CardFooter className="flex items-center justify-between border-t border-stone-100 p-4 dark:border-neutral-800">
                                        <span className="truncate text-[11px] text-stone-500">
                                            {venue.business?.user?.name ??
                                                'Mitra'}
                                        </span>
                                        <Button
                                            asChild
                                            size="sm"
                                            className="h-8 gap-1.5 bg-cobalt px-3 text-xs text-white hover:bg-cobalt-deep"
                                        >
                                            <Link
                                                href={venueShow({
                                                    venue: venue.slug,
                                                })}
                                            >
                                                <Eye className="size-3.5" />
                                                <span>Tinjau Pengajuan</span>
                                            </Link>
                                        </Button>
                                    </CardFooter>
                                </Card>
                            );
                        })}
                    </div>
                )}

                {/* Pagination */}
                {venues.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-stone-200/80 pt-4 dark:border-neutral-800">
                        <span className="text-xs text-stone-500">
                            Menampilkan halaman {venues.current_page} dari{' '}
                            {venues.last_page} ({venues.total} total)
                        </span>
                        <div className="flex items-center gap-2">
                            {venues.prev_page_url ? (
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="h-8 text-xs"
                                >
                                    <Link
                                        href={venues.prev_page_url}
                                        preserveScroll
                                    >
                                        Sebelumnya
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled
                                    className="h-8 text-xs opacity-50"
                                >
                                    Sebelumnya
                                </Button>
                            )}

                            {venues.next_page_url ? (
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="h-8 text-xs"
                                >
                                    <Link
                                        href={venues.next_page_url}
                                        preserveScroll
                                    >
                                        Berikutnya
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled
                                    className="h-8 text-xs opacity-50"
                                >
                                    Berikutnya
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

SuperadminVenuesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Kurasi Venue',
            href: venuesIndex(),
        },
    ],
};
