import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Building2,
    CheckCircle2,
    Clock,
    ExternalLink,
    FileText,
    Layers,
    MapPin,
    Phone,
    ShieldAlert,
    UserCheck,
    Users,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { dashboard } from '@/routes';
import {
    approve as venueApprove,
    index as venuesIndex,
    reject as venueReject,
    unpublish as venueUnpublish,
} from '@/routes/superadmin/venues';
import { show as publicVenueShow } from '@/routes/venues';

type Schedule = {
    id: number;
    day_of_week: number;
    open_time: string;
    close_time: string;
    slot_duration_minutes: number;
    is_active: boolean;
};

type AddOn = {
    id: number;
    name: string;
    price: number | string;
    pricing_type?: string;
    calculation_type?: string;
    stock_quantity?: number | null;
    description?: string | null;
    is_active: boolean;
};
type Amenity = {
    id: number;
    name: string;
    icon: string | null;
};

type Space = {
    id: number;
    name: string;
    slug: string;
    category: string;
    capacity: number;
    base_price?: number | string;
    price_per_slot?: number | string;
    instant_book?: boolean;
    is_instant?: boolean;
    cancellation_policy: string;
    refund_percentage: number | string;
    description?: string | null;
    schedules?: Schedule[];
    add_ons?: AddOn[];
    amenities?: Amenity[];
};

type AuditItem = {
    id: number;
    action: string;
    payload: Record<string, any> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
    actor?: {
        id: number;
        name: string;
        email: string;
    } | null;
};

type VenueDetail = {
    id: number;
    name: string;
    slug: string;
    city: string;
    address: string;
    timezone: string;
    contact_phone: string | null;
    is_published: boolean;
    approval_status: 'pending' | 'approved' | 'rejected';
    approved_at: string | null;
    approved_by: number | null;
    rejection_reason: string | null;
    cover_url: string | null;
    created_at: string;
    updated_at: string;
    business?: {
        id: number;
        name: string;
        legal_name: string | null;
        status: string;
        user?: {
            id: number;
            name: string;
            email: string;
        } | null;
    } | null;
    spaces: Space[];
};

type Props = {
    venue: VenueDetail;
    auditLogs: AuditItem[];
};

const DAYS = [
    { day: 1, label: 'Senin' },
    { day: 2, label: 'Selasa' },
    { day: 3, label: 'Rabu' },
    { day: 4, label: 'Kamis' },
    { day: 5, label: 'Jumat' },
    { day: 6, label: 'Sabtu' },
    { day: 0, label: 'Minggu' },
];

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

function formatRupiah(amount: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatDateTime(iso: string): string {
    try {
        const d = new Date(iso);
        return d.toLocaleString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return iso;
    }
}

export default function SuperadminVenueShow({ venue, auditLogs }: Props) {
    const [isApproveOpen, setIsApproveOpen] = useState(false);
    const [isRejectOpen, setIsRejectOpen] = useState(false);
    const [isUnpublishOpen, setIsUnpublishOpen] = useState(false);

    const [rejectionReason, setRejectionReason] = useState('');
    const [unpublishReason, setUnpublishReason] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleApprove = () => {
        setIsSubmitting(true);
        router.post(
            venueApprove({ venue: venue.slug }).url,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsSubmitting(false);
                    setIsApproveOpen(false);
                },
            },
        );
    };

    const handleReject = (e: React.FormEvent) => {
        e.preventDefault();
        if (rejectionReason.trim().length < 5) return;

        setIsSubmitting(true);
        router.post(
            venueReject({ venue: venue.slug }).url,
            { rejection_reason: rejectionReason },
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsSubmitting(false);
                    setIsRejectOpen(false);
                },
            },
        );
    };

    const handleUnpublish = (e: React.FormEvent) => {
        e.preventDefault();
        if (unpublishReason.trim().length < 5) return;

        setIsSubmitting(true);
        router.post(
            venueUnpublish({ venue: venue.slug }).url,
            { reason: unpublishReason },
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsSubmitting(false);
                    setIsUnpublishOpen(false);
                },
            },
        );
    };

    const isPending = venue.approval_status === 'pending';
    const isApproved = venue.approval_status === 'approved';
    const isRejected = venue.approval_status === 'rejected';

    return (
        <>
            <Head title={`Kurasi: ${venue.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-8">
                {/* Back Link & Flash */}
                <div className="flex items-center justify-between">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="gap-1.5 text-xs text-stone-600 dark:text-stone-400"
                    >
                        <Link href={venuesIndex()}>
                            <ArrowLeft className="size-4" />
                            <span>Kembali ke Antrean Kurasi</span>
                        </Link>
                    </Button>

                    {venue.is_published && (
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="gap-1.5 text-xs"
                        >
                            <Link
                                href={publicVenueShow({ venue: venue.slug })}
                                target="_blank"
                            >
                                <span>Lihat di Katalog Publik</span>
                                <ExternalLink className="size-3.5" />
                            </Link>
                        </Button>
                    )}
                </div>

                {/* Main Split Grid */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Sisi Kiri: Detail Pengajuan (2 Kolom) */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* Cover Image & Primary Info Card */}
                        <Card className="overflow-hidden border-stone-200/90 dark:border-neutral-800">
                            <div className="relative aspect-[21/9] w-full overflow-hidden bg-stone-100 dark:bg-neutral-900">
                                {venue.cover_url ? (
                                    <img
                                        src={venue.cover_url}
                                        alt={venue.name}
                                        className="h-full w-full object-cover"
                                    />
                                ) : (
                                    <div className="flex h-full w-full items-center justify-center text-stone-400">
                                        <Building2 className="size-16 stroke-[1.25]" />
                                    </div>
                                )}
                            </div>

                            <CardHeader className="p-6">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="dark:text-cobalt-light text-xs font-bold tracking-wider text-cobalt uppercase">
                                        {venue.business?.name ?? 'Mitra Usaha'}
                                    </span>
                                    <span className="text-stone-300 dark:text-neutral-700">
                                        ·
                                    </span>
                                    <span className="text-xs text-stone-500">
                                        Diajukan pada{' '}
                                        {formatDateTime(venue.created_at)}
                                    </span>
                                </div>
                                <CardTitle className="mt-1 font-display text-2xl font-black text-foreground md:text-3xl">
                                    {venue.name}
                                </CardTitle>
                                <CardDescription className="flex items-center gap-1.5 text-sm text-stone-600 dark:text-stone-400">
                                    <MapPin className="size-4 shrink-0 text-stone-400" />
                                    <span>
                                        {venue.address}, {venue.city} (
                                        {formatTz(venue.timezone)})
                                    </span>
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="grid gap-4 border-t border-stone-100 p-6 sm:grid-cols-2 dark:border-neutral-800">
                                <div>
                                    <span className="text-xs font-semibold tracking-wider text-stone-400 uppercase">
                                        Kontak Pengelola
                                    </span>
                                    <p className="mt-1 flex items-center gap-2 text-sm font-medium text-foreground">
                                        <Phone className="size-4 text-stone-400" />
                                        {venue.contact_phone ||
                                            'Tidak dicantumkan'}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs font-semibold tracking-wider text-stone-400 uppercase">
                                        Pemilik Akun & Email
                                    </span>
                                    <p className="mt-1 flex items-center gap-2 text-sm font-medium text-foreground">
                                        <UserCheck className="size-4 text-stone-400" />
                                        {venue.business?.user?.name ?? '-'} (
                                        {venue.business?.user?.email ?? '-'})
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs font-semibold tracking-wider text-stone-400 uppercase">
                                        Status Bisnis Mitra
                                    </span>
                                    <p className="mt-1 text-sm font-medium text-foreground capitalize">
                                        {venue.business?.status ?? 'active'}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs font-semibold tracking-wider text-stone-400 uppercase">
                                        Status Tayang Publik
                                    </span>
                                    <p className="mt-1 text-sm font-medium">
                                        {venue.is_published ? (
                                            <span className="inline-flex items-center gap-1.5 text-emerald-600">
                                                <span className="size-2 rounded-full bg-emerald-600" />
                                                Tayang di Marketplace
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1.5 text-stone-400">
                                                <span className="size-2 rounded-full bg-stone-300 dark:bg-neutral-600" />
                                                Tidak Tayang (Draft)
                                            </span>
                                        )}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Units / Spaces Section */}
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <h2 className="font-display text-lg font-bold text-foreground">
                                    Daftar Ruangan & Unit (
                                    {venue.spaces?.length ?? 0})
                                </h2>
                            </div>

                            {!venue.spaces || venue.spaces.length === 0 ? (
                                <Card className="border-dashed border-stone-200 p-8 text-center dark:border-neutral-800">
                                    <Layers className="mx-auto size-8 text-stone-400" />
                                    <p className="mt-2 text-sm font-medium text-stone-600 dark:text-stone-400">
                                        Belum ada data ruangan atau space yang
                                        didaftarkan pada venue ini.
                                    </p>
                                </Card>
                            ) : (
                                venue.spaces.map((space) => (
                                    <Card
                                        key={space.id}
                                        className="overflow-hidden border-stone-200/90 dark:border-neutral-800"
                                    >
                                        <CardHeader className="bg-stone-50/60 p-5 dark:bg-neutral-900/40">
                                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <Badge
                                                            variant="outline"
                                                            className="text-xs font-semibold uppercase"
                                                        >
                                                            {space.category}
                                                        </Badge>
                                                        {(space.instant_book ??
                                                            space.is_instant) && (
                                                            <Badge className="border-emerald-500/30 bg-emerald-500/15 text-xs text-emerald-700 dark:text-emerald-400">
                                                                Instant Booking
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <CardTitle className="mt-1 font-display text-lg font-bold text-foreground">
                                                        {space.name}
                                                    </CardTitle>
                                                </div>
                                                <div className="text-left sm:text-right">
                                                    <span className="text-xs text-stone-400">
                                                        Tarif Dasar
                                                    </span>
                                                    <p className="font-display text-lg font-extrabold text-foreground">
                                                        {formatRupiah(
                                                            Number(
                                                                space.base_price ??
                                                                    space.price_per_slot ??
                                                                    0,
                                                            ),
                                                        )}
                                                        <span className="text-xs font-normal text-stone-500">
                                                            {' '}
                                                            / slot
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>
                                        </CardHeader>

                                        <CardContent className="space-y-4 p-5">
                                            {/* Space Metadata */}
                                            <div className="grid gap-3 rounded-xl bg-stone-100/60 p-3 text-xs sm:grid-cols-3 dark:bg-neutral-900/60">
                                                <div>
                                                    <span className="text-stone-400">
                                                        Kapasitas Maksimal
                                                    </span>
                                                    <p className="mt-0.5 flex items-center gap-1.5 font-semibold text-foreground">
                                                        <Users className="size-3.5 text-stone-400" />
                                                        {space.capacity} Orang
                                                    </p>
                                                </div>
                                                <div>
                                                    <span className="text-stone-400">
                                                        Kebijakan Batal
                                                    </span>
                                                    <p className="mt-0.5 font-semibold text-foreground capitalize">
                                                        {
                                                            space.cancellation_policy
                                                        }
                                                    </p>
                                                </div>
                                                <div>
                                                    <span className="text-stone-400">
                                                        Pengembalian Dana
                                                        (Refund)
                                                    </span>
                                                    <p className="mt-0.5 font-semibold text-foreground">
                                                        {
                                                            space.refund_percentage
                                                        }
                                                        %
                                                    </p>
                                                </div>
                                            </div>

                                            {space.description && (
                                                <p className="text-xs text-stone-600 dark:text-stone-400">
                                                    {space.description}
                                                </p>
                                            )}

                                            {/* Schedules Grid */}
                                            <div>
                                                <h4 className="mb-2 text-xs font-bold tracking-wider text-stone-500 uppercase">
                                                    Jadwal Operasional Ruangan
                                                    (7 Hari)
                                                </h4>
                                                <div className="grid grid-cols-2 gap-2 text-xs sm:grid-cols-4 md:grid-cols-7">
                                                    {DAYS.map(
                                                        ({ day, label }) => {
                                                            const sch =
                                                                space.schedules?.find(
                                                                    (s) =>
                                                                        s.day_of_week ===
                                                                        day,
                                                                );
                                                            const active =
                                                                sch?.is_active ??
                                                                false;
                                                            return (
                                                                <div
                                                                    key={day}
                                                                    className={`rounded-lg border p-2 text-center ${
                                                                        active
                                                                            ? 'border-emerald-200 bg-emerald-50/40 text-emerald-950 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-200'
                                                                            : 'border-stone-200 bg-stone-50 text-stone-400 dark:border-neutral-800 dark:bg-neutral-900/40'
                                                                    }`}
                                                                >
                                                                    <span className="block text-[11px] font-bold">
                                                                        {label}
                                                                    </span>
                                                                    {active &&
                                                                    sch ? (
                                                                        <div className="mt-1 space-y-0.5 text-[10px]">
                                                                            <span>
                                                                                {sch.open_time.slice(
                                                                                    0,
                                                                                    5,
                                                                                )}{' '}
                                                                                -{' '}
                                                                                {sch.close_time.slice(
                                                                                    0,
                                                                                    5,
                                                                                )}
                                                                            </span>
                                                                            <span className="block text-stone-400">
                                                                                (
                                                                                {
                                                                                    sch.slot_duration_minutes
                                                                                }
                                                                                m)
                                                                            </span>
                                                                        </div>
                                                                    ) : (
                                                                        <span className="mt-1 block text-[10px]">
                                                                            Tutup
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            );
                                                        },
                                                    )}
                                                </div>
                                            </div>

                                            {/* Add-Ons if any */}
                                            {space.add_ons &&
                                                space.add_ons.length > 0 && (
                                                    <div className="border-t border-stone-100 pt-3 dark:border-neutral-800">
                                                        <h4 className="mb-2 text-xs font-bold tracking-wider text-stone-500 uppercase">
                                                            Layanan Tambahan
                                                            (Add-On)
                                                        </h4>
                                                        <div className="grid gap-2 sm:grid-cols-2">
                                                            {space.add_ons.map(
                                                                (addon) => (
                                                                    <div
                                                                        key={
                                                                            addon.id
                                                                        }
                                                                        className="flex items-center justify-between rounded-lg border border-stone-200/80 bg-white p-2.5 text-xs dark:border-neutral-800 dark:bg-neutral-900"
                                                                    >
                                                                        <div>
                                                                            <span className="font-semibold text-foreground">
                                                                                {
                                                                                    addon.name
                                                                                }
                                                                            </span>
                                                                            <span className="block text-[10px] text-stone-400 capitalize">
                                                                                {(
                                                                                    addon.pricing_type ??
                                                                                    addon.calculation_type ??
                                                                                    'per booking'
                                                                                ).replace(
                                                                                    '_',
                                                                                    ' ',
                                                                                )}
                                                                            </span>
                                                                        </div>
                                                                        <span className="dark:text-cobalt-light font-bold text-cobalt">
                                                                            {formatRupiah(
                                                                                Number(
                                                                                    addon.price ??
                                                                                        0,
                                                                                ),
                                                                            )}
                                                                        </span>
                                                                    </div>
                                                                ),
                                                            )}
                                                        </div>
                                                    </div>
                                                )}
                                        </CardContent>
                                    </Card>
                                ))
                            )}
                        </div>
                    </div>

                    {/* Sisi Kanan: Panel Keputusan Superadmin (1 Kolom) */}
                    <div className="space-y-6">
                        {/* Status Panel Card */}
                        <Card className="sticky top-6 border-stone-200/90 shadow-sm dark:border-neutral-800">
                            <CardHeader className="p-5 pb-3">
                                <span className="text-xs font-bold tracking-wider text-stone-400 uppercase">
                                    Status Keputusan
                                </span>
                                <CardTitle className="mt-1 flex items-center gap-2 font-display text-xl font-extrabold">
                                    {isPending && (
                                        <>
                                            <Clock className="size-5 text-amber-500" />
                                            <span className="text-amber-700 dark:text-amber-400">
                                                Menunggu Kurasi
                                            </span>
                                        </>
                                    )}
                                    {isApproved && (
                                        <>
                                            <CheckCircle2 className="size-5 text-emerald-500" />
                                            <span className="text-emerald-700 dark:text-emerald-400">
                                                Telah Disetujui
                                            </span>
                                        </>
                                    )}
                                    {isRejected && (
                                        <>
                                            <XCircle className="size-5 text-rose-500" />
                                            <span className="text-rose-700 dark:text-rose-400">
                                                Pengajuan Ditolak
                                            </span>
                                        </>
                                    )}
                                </CardTitle>
                                <CardDescription className="text-xs text-stone-500">
                                    {isPending &&
                                        'Tinjau data tempat dan tentukan persetujuan atau penolakan pengajuan.'}
                                    {isApproved &&
                                        `Disetujui pada ${venue.approved_at ? formatDateTime(venue.approved_at) : 'sebelumnya'}.`}
                                    {isRejected &&
                                        'Pengajuan ini ditolak dan tidak dapat ditayangkan sebelum diperbaiki.'}
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-4 p-5 pt-0">
                                {isRejected && venue.rejection_reason && (
                                    <div className="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-200">
                                        <span className="mb-1 flex items-center gap-1.5 font-bold">
                                            <AlertCircle className="size-3.5 shrink-0" />
                                            Alasan Penolakan:
                                        </span>
                                        <p>{venue.rejection_reason}</p>
                                    </div>
                                )}

                                {/* Action Buttons */}
                                <div className="space-y-2 pt-2">
                                    {isPending && (
                                        <>
                                            <Button
                                                onClick={() =>
                                                    setIsApproveOpen(true)
                                                }
                                                className="w-full gap-2 bg-emerald-600 text-white hover:bg-emerald-700"
                                            >
                                                <CheckCircle2 className="size-4" />
                                                <span>Setujui Venue</span>
                                            </Button>

                                            <Button
                                                onClick={() =>
                                                    setIsRejectOpen(true)
                                                }
                                                variant="outline"
                                                className="w-full gap-2 border-rose-200 text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:border-rose-900/40 dark:hover:bg-rose-950/20"
                                            >
                                                <XCircle className="size-4" />
                                                <span>Tolak Pengajuan</span>
                                            </Button>
                                        </>
                                    )}

                                    {isApproved && (
                                        <>
                                            <div className="rounded-lg bg-emerald-50/60 p-3 text-xs text-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-300">
                                                Venue ini telah memenuhi standar
                                                kurasi. Pemilik venue dapat
                                                mengontrol publikasi venue
                                                secara mandiri.
                                            </div>

                                            {venue.is_published && (
                                                <Button
                                                    onClick={() =>
                                                        setIsUnpublishOpen(true)
                                                    }
                                                    variant="destructive"
                                                    className="w-full gap-2"
                                                >
                                                    <ShieldAlert className="size-4" />
                                                    <span>
                                                        Cabut Publikasi
                                                        (Unpublish)
                                                    </span>
                                                </Button>
                                            )}

                                            <Button
                                                onClick={() =>
                                                    setIsRejectOpen(true)
                                                }
                                                variant="outline"
                                                size="sm"
                                                className="w-full gap-2 border-stone-200 text-xs text-stone-600"
                                            >
                                                <span>
                                                    Ubah Menjadi Ditolak
                                                </span>
                                            </Button>
                                        </>
                                    )}

                                    {isRejected && (
                                        <Button
                                            onClick={() =>
                                                setIsApproveOpen(true)
                                            }
                                            className="w-full gap-2 bg-cobalt text-white hover:bg-cobalt-deep"
                                        >
                                            <CheckCircle2 className="size-4" />
                                            <span>Tinjau Ulang & Setujui</span>
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Audit Logs Section */}
                <div className="space-y-4 border-t border-stone-200/80 pt-4 dark:border-neutral-800">
                    <div className="flex items-center gap-2">
                        <FileText className="dark:text-cobalt-light size-5 text-cobalt" />
                        <h3 className="font-display text-lg font-bold text-foreground">
                            Riwayat Audit Venue ({auditLogs.length})
                        </h3>
                    </div>

                    {auditLogs.length === 0 ? (
                        <Card className="border-dashed border-stone-200 p-6 text-center text-xs text-stone-500 dark:border-neutral-800">
                            Belum ada riwayat audit tercatat untuk venue ini.
                        </Card>
                    ) : (
                        <div className="space-y-3">
                            {auditLogs.map((log) => {
                                const isApprovedLog =
                                    log.action === 'venue.approved';
                                const isRejectedLog =
                                    log.action === 'venue.rejected';
                                const isUnpublishedLog =
                                    log.action === 'venue.unpublished';

                                return (
                                    <div
                                        key={log.id}
                                        className="flex flex-col justify-between gap-3 rounded-xl border border-stone-200/80 bg-white p-4 text-xs shadow-xs sm:flex-row sm:items-start dark:border-neutral-800 dark:bg-neutral-900"
                                    >
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <Badge
                                                    className={
                                                        isApprovedLog
                                                            ? 'border-emerald-500/30 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                                            : isRejectedLog
                                                              ? 'border-rose-500/30 bg-rose-500/15 text-rose-700 dark:text-rose-400'
                                                              : isUnpublishedLog
                                                                ? 'border-amber-500/30 bg-amber-500/15 text-amber-700 dark:text-amber-400'
                                                                : 'border-stone-500/30 bg-stone-500/15 text-stone-700 dark:text-stone-400'
                                                    }
                                                >
                                                    {log.action}
                                                </Badge>
                                                <span className="font-semibold text-foreground">
                                                    {log.actor?.name ??
                                                        'Sistem'}
                                                </span>
                                                <span className="text-stone-400">
                                                    ({log.actor?.email ?? '-'})
                                                </span>
                                            </div>

                                            {log.payload?.reason && (
                                                <p className="text-stone-700 dark:text-stone-300">
                                                    <span className="font-semibold">
                                                        Catatan:
                                                    </span>{' '}
                                                    {log.payload.reason}
                                                </p>
                                            )}

                                            <div className="flex items-center gap-3 pt-0.5 text-[11px] text-stone-400">
                                                <span>
                                                    IP:{' '}
                                                    {log.ip_address ||
                                                        '127.0.0.1'}
                                                </span>
                                            </div>
                                        </div>

                                        <span className="shrink-0 text-[11px] text-stone-400">
                                            {formatDateTime(log.created_at)}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* Dialog Approve */}
                <Dialog open={isApproveOpen} onOpenChange={setIsApproveOpen}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle className="font-display">
                                Setujui Pengajuan Venue
                            </DialogTitle>
                            <DialogDescription className="text-xs">
                                Apakah Anda yakin data venue{' '}
                                <strong className="text-foreground">
                                    {venue.name}
                                </strong>{' '}
                                telah memenuhi seluruh kriteria kelayakan DOKO?
                            </DialogDescription>
                        </DialogHeader>

                        <div className="rounded-lg bg-emerald-50/60 p-3 text-xs text-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-300">
                            Setelah disetujui, pemilik venue akan dapat
                            mempublikasikan venue ini secara langsung ke
                            marketplace publik.
                        </div>

                        <DialogFooter className="gap-2 sm:gap-0">
                            <DialogClose asChild>
                                <Button variant="outline" size="sm">
                                    Batal
                                </Button>
                            </DialogClose>
                            <Button
                                onClick={handleApprove}
                                disabled={isSubmitting}
                                size="sm"
                                className="bg-emerald-600 text-white hover:bg-emerald-700"
                            >
                                {isSubmitting
                                    ? 'Memproses...'
                                    : 'Ya, Setujui Venue'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* Dialog Reject */}
                <Dialog open={isRejectOpen} onOpenChange={setIsRejectOpen}>
                    <DialogContent className="sm:max-w-md">
                        <form onSubmit={handleReject}>
                            <DialogHeader>
                                <DialogTitle className="font-display">
                                    Tolak Pengajuan Venue
                                </DialogTitle>
                                <DialogDescription className="text-xs">
                                    Berikan alasan penolakan yang jelas agar
                                    pemilik venue dapat memperbaiki data sebelum
                                    mengajukan kembali.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="py-4">
                                <label className="mb-1.5 block text-xs font-semibold text-stone-700 dark:text-stone-300">
                                    Alasan Penolakan{' '}
                                    <span className="text-rose-500">*</span>
                                </label>
                                <textarea
                                    required
                                    rows={4}
                                    value={rejectionReason}
                                    onChange={(e) =>
                                        setRejectionReason(e.target.value)
                                    }
                                    placeholder="Contoh: Foto ruangan tidak jelas, informasi jam operasional tidak sinkron, atau alamat tidak lengkap..."
                                    className="w-full rounded-md border border-stone-200 bg-transparent p-3 text-xs shadow-xs outline-none focus-visible:border-stone-400 focus-visible:ring-1 focus-visible:ring-stone-400 dark:border-neutral-800"
                                />
                                <span className="text-[11px] text-stone-400">
                                    Minimal 5 karakter.
                                </span>
                            </div>

                            <DialogFooter className="gap-2 sm:gap-0">
                                <DialogClose asChild>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        type="button"
                                    >
                                        Batal
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={
                                        isSubmitting ||
                                        rejectionReason.trim().length < 5
                                    }
                                    variant="destructive"
                                    size="sm"
                                >
                                    {isSubmitting
                                        ? 'Memproses...'
                                        : 'Kirim Penolakan'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                {/* Dialog Unpublish */}
                <Dialog
                    open={isUnpublishOpen}
                    onOpenChange={setIsUnpublishOpen}
                >
                    <DialogContent className="sm:max-w-md">
                        <form onSubmit={handleUnpublish}>
                            <DialogHeader>
                                <DialogTitle className="font-display">
                                    Cabut Publikasi Venue
                                </DialogTitle>
                                <DialogDescription className="text-xs">
                                    Cabut penayangan venue{' '}
                                    <strong className="text-foreground">
                                        {venue.name}
                                    </strong>{' '}
                                    dari marketplace publik. Masukkan alasan
                                    penindakan.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="py-4">
                                <label className="mb-1.5 block text-xs font-semibold text-stone-700 dark:text-stone-300">
                                    Alasan Pencabutan{' '}
                                    <span className="text-rose-500">*</span>
                                </label>
                                <textarea
                                    required
                                    rows={4}
                                    value={unpublishReason}
                                    onChange={(e) =>
                                        setUnpublishReason(e.target.value)
                                    }
                                    placeholder="Contoh: Terjadi laporan pelanggaran ketentuan sewa, tempat sedang renovasi darurat, dsb..."
                                    className="w-full rounded-md border border-stone-200 bg-transparent p-3 text-xs shadow-xs outline-none focus-visible:border-stone-400 focus-visible:ring-1 focus-visible:ring-stone-400 dark:border-neutral-800"
                                />
                                <span className="text-[11px] text-stone-400">
                                    Minimal 5 karakter.
                                </span>
                            </div>

                            <DialogFooter className="gap-2 sm:gap-0">
                                <DialogClose asChild>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        type="button"
                                    >
                                        Batal
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={
                                        isSubmitting ||
                                        unpublishReason.trim().length < 5
                                    }
                                    variant="destructive"
                                    size="sm"
                                >
                                    {isSubmitting
                                        ? 'Memproses...'
                                        : 'Cabut Publikasi'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

SuperadminVenueShow.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Kurasi Venue',
            href: venuesIndex(),
        },
        {
            title: 'Tinjau Pengajuan',
            href: '#',
        },
    ],
};
