import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    CheckCircle2,
    Clock,
    Compass,
    MapPin,
    Plus,
    QrCode,
    ShieldAlert,
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
import { scanner as scannerRoute } from '@/routes/gate';
import {
    create as venueCreate,
    edit as venueEdit,
    index as venueIndex,
} from '@/routes/owner/venues';
import {
    index as superadminVenuesIndex,
    show as superadminVenueShow,
} from '@/routes/superadmin/venues';
import { index as spacesIndex } from '@/routes/spaces';
import type { Auth } from '@/types';

type OwnerVenue = {
    id: number;
    name: string;
    slug: string;
    city: string;
    timezone: string;
    contact_phone: string | null;
    is_published: boolean;
    approval_status: 'pending' | 'approved' | 'rejected';
    rejection_reason: string | null;
    cover_url: string | null;
    spaces_count: number;
    updated_at: string;
};

type OwnerData = {
    business_name: string;
    stats: {
        total_venues: number;
        approved_count: number;
        pending_count: number;
        rejected_count: number;
        published_count: number;
    };
    recent_venues: OwnerVenue[];
};

type SuperadminVenue = {
    id: number;
    name: string;
    slug: string;
    city: string;
    timezone: string;
    contact_phone: string | null;
    business_name: string;
    created_at: string;
    cover_url: string | null;
};

type SuperadminData = {
    stats: {
        pending_venues_count: number;
        approved_venues_count: number;
        rejected_venues_count: number;
        total_venues: number;
        total_businesses: number;
    };
    pending_venues: SuperadminVenue[];
};

type Props = {
    ownerData?: OwnerData | null;
    superadminData?: SuperadminData | null;
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

export default function Dashboard({ ownerData, superadminData }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const role = auth?.user?.role;
    const isSuperadmin = role === 'superadmin';
    const isOwner = role === 'owner';

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-8">
                {/* 1. Superadmin View */}
                {isSuperadmin && superadminData && (
                    <div className="flex flex-col gap-8">
                        {/* Header */}
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <span className="text-xs font-bold tracking-wider text-cobalt uppercase">
                                    Panel Kontrol Superadmin
                                </span>
                                <h1 className="mt-0.5 font-display text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">
                                    Pusat Kendali Platform
                                </h1>
                                <p className="mt-1 max-w-2xl text-sm text-stone-600 dark:text-stone-400">
                                    Pantau antrean persetujuan venue, status
                                    publikasi tempat, dan operasional seluruh
                                    mitra bisnis AdaTempat.
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="gap-1.5"
                                >
                                    <Link href={scannerRoute()}>
                                        <QrCode className="size-4 text-stone-500" />
                                        <span>Gate Scanner</span>
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    size="sm"
                                    className="gap-1.5 bg-cobalt text-white hover:bg-cobalt-deep"
                                >
                                    <Link href={venueIndex()}>
                                        <Building2 className="size-4" />
                                        <span>Daftar Semua Venue</span>
                                    </Link>
                                </Button>
                            </div>
                        </div>

                        {/* Metrics Bar */}
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <Card className="border-stone-200/90 bg-amber-50/40 p-4 dark:border-neutral-800 dark:bg-amber-950/20">
                                <span className="text-xs font-medium text-amber-800 dark:text-amber-300">
                                    Menunggu Persetujuan
                                </span>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <span className="font-display text-3xl font-extrabold text-amber-900 dark:text-amber-200">
                                        {
                                            superadminData.stats
                                                .pending_venues_count
                                        }
                                    </span>
                                    <span className="text-xs text-amber-700 dark:text-amber-400">
                                        venue
                                    </span>
                                </div>
                            </Card>

                            <Card className="border-stone-200/90 bg-emerald-50/40 p-4 dark:border-neutral-800 dark:bg-emerald-950/20">
                                <span className="text-xs font-medium text-emerald-800 dark:text-emerald-300">
                                    Venue Disetujui
                                </span>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <span className="font-display text-3xl font-extrabold text-emerald-900 dark:text-emerald-200">
                                        {
                                            superadminData.stats
                                                .approved_venues_count
                                        }
                                    </span>
                                    <span className="text-xs text-emerald-700 dark:text-emerald-400">
                                        venue
                                    </span>
                                </div>
                            </Card>

                            <Card className="border-stone-200/90 bg-stone-50 p-4 dark:border-neutral-800 dark:bg-neutral-900/40">
                                <span className="text-xs font-medium text-stone-600 dark:text-stone-400">
                                    Total Bisnis Terdaftar
                                </span>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <span className="font-display text-3xl font-extrabold text-stone-900 dark:text-stone-100">
                                        {superadminData.stats.total_businesses}
                                    </span>
                                    <span className="text-xs text-stone-500">
                                        bisnis
                                    </span>
                                </div>
                            </Card>

                            <Card className="border-stone-200/90 bg-stone-50 p-4 dark:border-neutral-800 dark:bg-neutral-900/40">
                                <span className="text-xs font-medium text-stone-600 dark:text-stone-400">
                                    Total Seluruh Venue
                                </span>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <span className="font-display text-3xl font-extrabold text-stone-900 dark:text-stone-100">
                                        {superadminData.stats.total_venues}
                                    </span>
                                    <span className="text-xs text-stone-500">
                                        tempat
                                    </span>
                                </div>
                            </Card>
                        </div>

                        {/* Pending Approvals Queue */}
                        <div>
                            <div className="flex items-center justify-between pb-3">
                                <div>
                                    <h2 className="font-display text-lg font-bold text-foreground">
                                        Antrean Verifikasi Venue
                                    </h2>
                                    <p className="text-xs text-stone-500">
                                        Venue yang diajukan oleh mitra pemilik
                                        dan menunggu persetujuan superadmin.
                                    </p>
                                </div>
                                <Button
                                    asChild
                                    variant="ghost"
                                    size="sm"
                                    className="gap-1 text-xs text-cobalt hover:text-cobalt-deep"
                                >
                                    <Link href={superadminVenuesIndex()}>
                                        <span>Lihat Semua</span>
                                        <ArrowRight className="size-3.5" />
                                    </Link>
                                </Button>
                            </div>

                            {superadminData.pending_venues.length === 0 ? (
                                <Card className="flex flex-col items-center justify-center border-dashed border-stone-300 p-8 text-center dark:border-neutral-700">
                                    <CheckCircle2 className="size-10 text-emerald-500" />
                                    <h3 className="mt-3 font-semibold text-stone-900 dark:text-stone-100">
                                        Semua Pengajuan Selesai
                                    </h3>
                                    <p className="mt-1 text-xs text-stone-500">
                                        Tidak ada venue yang menunggu
                                        persetujuan saat ini.
                                    </p>
                                </Card>
                            ) : (
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {superadminData.pending_venues.map(
                                        (venue) => (
                                            <Card
                                                key={venue.id}
                                                className="flex flex-col justify-between border-stone-200/90 dark:border-neutral-800"
                                            >
                                                <CardHeader className="p-4 pb-2">
                                                    <div className="flex items-center justify-between gap-2">
                                                        <span className="truncate text-xs font-semibold text-stone-500 uppercase">
                                                            {
                                                                venue.business_name
                                                            }
                                                        </span>
                                                        <Badge
                                                            variant="secondary"
                                                            className="border-amber-500/40 bg-amber-500/10 text-xs font-semibold text-amber-700 dark:text-amber-400"
                                                        >
                                                            <Clock className="mr-1 size-3" />
                                                            Menunggu
                                                        </Badge>
                                                    </div>
                                                    <CardTitle className="text-base font-bold">
                                                        {venue.name}
                                                    </CardTitle>
                                                    <CardDescription className="flex items-center gap-1.5 text-xs text-stone-500">
                                                        <MapPin className="size-3 shrink-0" />
                                                        <span>
                                                            {venue.city} ·{' '}
                                                            {formatTz(
                                                                venue.timezone,
                                                            )}
                                                        </span>
                                                    </CardDescription>
                                                </CardHeader>

                                                <CardFooter className="flex items-center justify-between border-t border-stone-100 p-4 pt-3 dark:border-neutral-800">
                                                    <span className="text-[11px] text-stone-500">
                                                        Diajukan{' '}
                                                        {venue.created_at}
                                                    </span>
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-8 gap-1 text-xs"
                                                    >
                                                        <Link
                                                            href={superadminVenueShow(
                                                                {
                                                                    venue: venue.slug,
                                                                },
                                                            )}
                                                        >
                                                            <span>Tinjau</span>
                                                            <ArrowRight className="size-3" />
                                                        </Link>
                                                    </Button>
                                                </CardFooter>
                                            </Card>
                                        ),
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* 2. Owner View */}
                {isOwner && ownerData && (
                    <div className="flex flex-col gap-8">
                        {/* Header */}
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <span className="text-xs font-bold tracking-wider text-cobalt uppercase">
                                    {ownerData.business_name} · Portal Pemilik
                                </span>
                                <h1 className="mt-0.5 font-display text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">
                                    Selamat Datang, {auth.user.name}
                                </h1>
                                <p className="mt-1 max-w-2xl text-sm text-stone-600 dark:text-stone-400">
                                    Pantau performa venue, status verifikasi
                                    tempat, dan kesiapan publikasi ke
                                    marketplace AdaTempat.
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="gap-1.5"
                                >
                                    <Link href={scannerRoute()}>
                                        <QrCode className="size-4 text-stone-500" />
                                        <span>Gate Scanner</span>
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    size="sm"
                                    className="gap-1.5 bg-cobalt text-white hover:bg-cobalt-deep"
                                >
                                    <Link href={venueCreate()}>
                                        <Plus className="size-4" />
                                        <span>Tambah Venue Baru</span>
                                    </Link>
                                </Button>
                            </div>
                        </div>

                        {/* Status Banners */}
                        {ownerData.stats.rejected_count > 0 && (
                            <div className="flex items-start gap-3 rounded-lg border border-rose-500/30 bg-rose-50 p-4 text-rose-950 dark:bg-rose-950/25 dark:text-rose-200">
                                <ShieldAlert className="mt-0.5 size-5 shrink-0 text-rose-600 dark:text-rose-400" />
                                <div className="text-sm">
                                    <p className="font-semibold">
                                        Ada {ownerData.stats.rejected_count}{' '}
                                        venue yang perlu perbaikan data.
                                    </p>
                                    <p className="mt-0.5 text-xs text-rose-800 dark:text-rose-300">
                                        Buka menu Kelola Venue untuk membaca
                                        alasan penolakan dan mengajukan ulang
                                        verifikasi setelah diperbaiki.
                                    </p>
                                </div>
                            </div>
                        )}

                        {ownerData.stats.pending_count > 0 && (
                            <div className="flex items-start gap-3 rounded-lg border border-amber-500/30 bg-amber-50 p-4 text-amber-950 dark:bg-amber-950/25 dark:text-amber-200">
                                <Clock className="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                                <div className="text-sm">
                                    <p className="font-semibold">
                                        {ownerData.stats.pending_count} venue
                                        sedang ditinjau oleh tim AdaTempat.
                                    </p>
                                    <p className="mt-0.5 text-xs text-amber-800 dark:text-amber-300">
                                        Proses verifikasi biasanya selesai dalam
                                        1x24 jam kerja. Setelah disetujui,
                                        tombol publikasi akan otomatis aktif.
                                    </p>
                                </div>
                            </div>
                        )}

                        {/* Metrics Bar */}
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <Card className="border-stone-200/90 bg-stone-50 p-4 dark:border-neutral-800 dark:bg-neutral-900/40">
                                <span className="text-xs font-medium text-stone-600 dark:text-stone-400">
                                    Total Venue
                                </span>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <span className="font-display text-3xl font-extrabold text-stone-900 dark:text-stone-100">
                                        {ownerData.stats.total_venues}
                                    </span>
                                    <span className="text-xs text-stone-500">
                                        lokasi
                                    </span>
                                </div>
                            </Card>

                            <Card className="border-stone-200/90 bg-cobalt/5 p-4 dark:border-neutral-800 dark:bg-cobalt/15">
                                <span className="text-xs font-semibold text-cobalt dark:text-blue-300">
                                    Tayang di Marketplace
                                </span>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <span className="font-display text-3xl font-extrabold text-cobalt dark:text-blue-200">
                                        {ownerData.stats.published_count}
                                    </span>
                                    <span className="text-xs text-cobalt/80">
                                        aktif
                                    </span>
                                </div>
                            </Card>

                            <Card className="border-stone-200/90 bg-emerald-50/40 p-4 dark:border-neutral-800 dark:bg-emerald-950/20">
                                <span className="text-xs font-medium text-emerald-800 dark:text-emerald-300">
                                    Disetujui
                                </span>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <span className="font-display text-3xl font-extrabold text-emerald-900 dark:text-emerald-200">
                                        {ownerData.stats.approved_count}
                                    </span>
                                    <span className="text-xs text-emerald-700 dark:text-emerald-400">
                                        lulus kurasi
                                    </span>
                                </div>
                            </Card>

                            <Card className="border-stone-200/90 bg-amber-50/40 p-4 dark:border-neutral-800 dark:bg-amber-950/20">
                                <span className="text-xs font-medium text-amber-800 dark:text-amber-300">
                                    Menunggu Verifikasi
                                </span>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <span className="font-display text-3xl font-extrabold text-amber-900 dark:text-amber-200">
                                        {ownerData.stats.pending_count}
                                    </span>
                                    <span className="text-xs text-amber-700 dark:text-amber-400">
                                        antrean
                                    </span>
                                </div>
                            </Card>
                        </div>

                        {/* Recent Venues */}
                        <div>
                            <div className="flex items-center justify-between pb-3">
                                <div>
                                    <h2 className="font-display text-lg font-bold text-foreground">
                                        Venue Terkelola
                                    </h2>
                                    <p className="text-xs text-stone-500">
                                        Daftar lokasi venue milik bisnis kamu.
                                    </p>
                                </div>
                                <Button
                                    asChild
                                    variant="ghost"
                                    size="sm"
                                    className="gap-1 text-xs text-cobalt hover:text-cobalt-deep"
                                >
                                    <Link href={venueIndex()}>
                                        <span>
                                            Kelola Semua (
                                            {ownerData.stats.total_venues})
                                        </span>
                                        <ArrowRight className="size-3.5" />
                                    </Link>
                                </Button>
                            </div>

                            {ownerData.recent_venues.length === 0 ? (
                                <Card className="flex flex-col items-center justify-center border-dashed border-stone-300 p-10 text-center dark:border-neutral-700">
                                    <Building2 className="size-12 text-stone-400" />
                                    <h3 className="mt-3 font-semibold text-stone-900 dark:text-stone-100">
                                        Belum Ada Venue Terdaftar
                                    </h3>
                                    <p className="mt-1 max-w-sm text-xs text-stone-500">
                                        Mulai daftarkan venue pertamamu agar
                                        pelanggan dapat menemukan lapangan,
                                        studio, atau ruanganmu di marketplace.
                                    </p>
                                    <Button
                                        asChild
                                        size="sm"
                                        className="mt-4 gap-1.5 bg-cobalt text-white hover:bg-cobalt-deep"
                                    >
                                        <Link href={venueCreate()}>
                                            <Plus className="size-4" />
                                            <span>Tambah Venue Sekarang</span>
                                        </Link>
                                    </Button>
                                </Card>
                            ) : (
                                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                    {ownerData.recent_venues.map((venue) => {
                                        const isApproved =
                                            venue.approval_status ===
                                            'approved';
                                        const isPending =
                                            venue.approval_status === 'pending';
                                        const isRejected =
                                            venue.approval_status ===
                                            'rejected';

                                        return (
                                            <Card
                                                key={venue.id}
                                                className="flex flex-col overflow-hidden border-stone-200/90 dark:border-neutral-800"
                                            >
                                                {/* Cover Image */}
                                                <div className="relative aspect-video w-full overflow-hidden bg-stone-100 dark:bg-neutral-800">
                                                    {venue.cover_url ? (
                                                        <img
                                                            src={
                                                                venue.cover_url
                                                            }
                                                            alt={venue.name}
                                                            className="size-full object-cover"
                                                        />
                                                    ) : (
                                                        <div className="flex size-full items-center justify-center text-stone-400">
                                                            <Building2 className="size-10 stroke-[1.5]" />
                                                        </div>
                                                    )}

                                                    {/* Status Overlays */}
                                                    <div className="absolute top-2.5 right-2.5 flex flex-wrap gap-1.5">
                                                        {isApproved && (
                                                            <Badge className="border-emerald-600 bg-emerald-600 text-white">
                                                                <CheckCircle2 className="mr-1 size-3" />
                                                                Disetujui
                                                            </Badge>
                                                        )}
                                                        {isPending && (
                                                            <Badge className="border-amber-500 bg-amber-500 text-white">
                                                                <Clock className="mr-1 size-3" />
                                                                Menunggu
                                                            </Badge>
                                                        )}
                                                        {isRejected && (
                                                            <Badge variant="destructive">
                                                                <XCircle className="mr-1 size-3" />
                                                                Ditolak
                                                            </Badge>
                                                        )}
                                                        {isApproved &&
                                                            venue.is_published && (
                                                                <Badge className="bg-cobalt text-white">
                                                                    Tayang
                                                                </Badge>
                                                            )}
                                                    </div>
                                                </div>

                                                <CardHeader className="p-4 pb-2">
                                                    <CardTitle className="text-base font-bold">
                                                        {venue.name}
                                                    </CardTitle>
                                                    <CardDescription className="flex items-center gap-1.5 text-xs text-stone-500">
                                                        <MapPin className="size-3.5 shrink-0" />
                                                        <span>
                                                            {venue.city} ·{' '}
                                                            {formatTz(
                                                                venue.timezone,
                                                            )}
                                                        </span>
                                                    </CardDescription>
                                                </CardHeader>

                                                <CardContent className="px-4 py-1 text-xs text-stone-600 dark:text-stone-400">
                                                    <div className="flex items-center justify-between">
                                                        <span>
                                                            Ruang/Lapangan:
                                                        </span>
                                                        <span className="font-semibold text-foreground">
                                                            {venue.spaces_count}{' '}
                                                            Ruang
                                                        </span>
                                                    </div>
                                                </CardContent>

                                                <CardFooter className="mt-auto flex items-center justify-between border-t border-stone-100 p-4 pt-3 dark:border-neutral-800">
                                                    <span className="text-[11px] text-stone-500">
                                                        {venue.updated_at}
                                                    </span>
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-8 gap-1 text-xs"
                                                    >
                                                        <Link
                                                            href={venueEdit({
                                                                venue: venue.slug,
                                                            })}
                                                        >
                                                            <span>Kelola</span>
                                                            <ArrowRight className="size-3" />
                                                        </Link>
                                                    </Button>
                                                </CardFooter>
                                            </Card>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* 3. Non-Owner / Customer View */}
                {!isOwner && !isSuperadmin && (
                    <div className="flex flex-col gap-6">
                        <div>
                            <span className="text-xs font-bold tracking-wider text-cobalt uppercase">
                                Akun Pengguna
                            </span>
                            <h1 className="mt-0.5 font-display text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">
                                Halo, {auth.user.name}
                            </h1>
                            <p className="mt-1 text-sm text-stone-600 dark:text-stone-400">
                                Siap booking tempat olahraga, studio, atau ruang
                                kerja hari ini?
                            </p>
                        </div>

                        <div className="grid gap-6 md:grid-cols-2">
                            <Card className="flex flex-col justify-between border-stone-200/90 p-6 dark:border-neutral-800">
                                <div>
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-cobalt/10 text-cobalt">
                                        <Compass className="size-5" />
                                    </div>
                                    <h2 className="mt-4 font-display text-lg font-bold">
                                        Cari Lapangan & Ruangan
                                    </h2>
                                    <p className="mt-1 text-xs text-stone-600 dark:text-stone-400">
                                        Cek jadwal kosong secara langsung, bayar
                                        via QRIS, dan simpan tiket digital di HP
                                        kamu.
                                    </p>
                                </div>
                                <Button
                                    asChild
                                    className="mt-6 gap-2 bg-cobalt text-white hover:bg-cobalt-deep"
                                >
                                    <Link href={spacesIndex()}>
                                        <span>Jelajahi Katalog Tempat</span>
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            </Card>

                            <Card className="flex flex-col justify-between border-stone-200/90 p-6 dark:border-neutral-800">
                                <div>
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600">
                                        <Building2 className="size-5" />
                                    </div>
                                    <h2 className="mt-4 font-display text-lg font-bold">
                                        Punya Tempat atau Lapangan?
                                    </h2>
                                    <p className="mt-1 text-xs text-stone-600 dark:text-stone-400">
                                        Daftarkan bisnismu sebagai mitra venue
                                        di AdaTempat dan buka pemesanan online
                                        otomatis tanpa ribet admin manual.
                                    </p>
                                </div>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="mt-6 gap-2 border-stone-300"
                                >
                                    <Link href="/register/business">
                                        <span>Daftarkan Bisnis Venue</span>
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            </Card>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
