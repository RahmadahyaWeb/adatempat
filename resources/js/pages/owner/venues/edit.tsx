import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Clock,
    Globe,
    RefreshCw,
    Save,
    Trash2,
    UploadCloud,
    X,
    XCircle,
} from 'lucide-react';
import { type ChangeEvent, useRef, useState } from 'react';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import {
    destroy as venueDestroy,
    index as venueIndex,
    publish as venuePublish,
    submitApproval as venueSubmitApproval,
    update as venueUpdate,
} from '@/routes/owner/venues';

type VenueDetail = {
    id: number;
    business_id: number;
    business_name: string;
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
};

type BusinessOption = {
    id: number;
    name: string;
};

type TimezoneOption = {
    value: string;
    label: string;
};

type Props = {
    venue: VenueDetail;
    businesses: BusinessOption[];
    timezones: TimezoneOption[];
};

export default function OwnerVenuesEdit({
    venue,
    businesses,
    timezones,
}: Props) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(
        venue.cover_url,
    );
    const [isDeleting, setIsDeleting] = useState(false);

    const { data, setData, post, processing, errors } = useForm<{
        _method: string;
        business_id: string;
        name: string;
        city: string;
        address: string;
        timezone: string;
        contact_phone: string;
        cover_photo: File | null;
    }>({
        _method: 'put',
        business_id: String(venue.business_id),
        name: venue.name,
        city: venue.city,
        address: venue.address,
        timezone: venue.timezone,
        contact_phone: venue.contact_phone ?? '',
        cover_photo: null,
    });

    const isApproved = venue.approval_status === 'approved';
    const isPending = venue.approval_status === 'pending';
    const isRejected = venue.approval_status === 'rejected';

    const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('cover_photo', file);
            setPreviewUrl(URL.createObjectURL(file));
        }
    };

    const handleRemoveNewPhoto = () => {
        setData('cover_photo', null);
        setPreviewUrl(venue.cover_url);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(venueUpdate({ venue: venue.slug }).url, {
            forceFormData: true,
        });
    };

    const handleTogglePublish = () => {
        router.post(
            venuePublish({ venue: venue.slug }).url,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const handleSubmitApproval = () => {
        router.post(
            venueSubmitApproval({ venue: venue.slug }).url,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const handleDeleteVenue = () => {
        if (
            confirm(
                `Apakah kamu yakin ingin menghapus venue "${venue.name}"? Tindakan ini tidak dapat dibatalkan.`,
            )
        ) {
            setIsDeleting(true);
            router.delete(venueDestroy({ venue: venue.slug }).url);
        }
    };

    return (
        <>
            <Head title={`Ubah Venue: ${venue.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-8">
                {/* Back Link & Header */}
                <div>
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="mb-2 gap-1.5 px-0 text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-white"
                    >
                        <Link href={venueIndex()}>
                            <ArrowLeft className="size-4" />
                            <span>Kembali ke Kelola Venue</span>
                        </Link>
                    </Button>

                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <span className="text-xs font-bold tracking-wider text-cobalt uppercase">
                                {venue.business_name} · Ubah Data
                            </span>
                            <h1 className="mt-0.5 font-display text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">
                                {venue.name}
                            </h1>
                            <p className="mt-1 text-sm text-stone-600 dark:text-stone-400">
                                Perbarui informasi tempat atau sesuaikan status
                                penayangan di marketplace AdaTempat.
                            </p>
                        </div>

                        <div className="flex items-center gap-2">
                            {isApproved && (
                                <Badge className="border-emerald-600 bg-emerald-600 text-white shadow-xs">
                                    <CheckCircle2 className="mr-1 size-3" />
                                    Disetujui
                                </Badge>
                            )}
                            {isPending && (
                                <Badge className="border-amber-500 bg-amber-500 text-white shadow-xs">
                                    <Clock className="mr-1 size-3" />
                                    Menunggu Persetujuan
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
                            {isApproved && venue.is_published && (
                                <Badge className="bg-cobalt text-white shadow-xs">
                                    <Globe className="mr-1 size-3" />
                                    Tayang
                                </Badge>
                            )}
                        </div>
                    </div>
                </div>

                {/* Status Banners */}
                {isRejected && (
                    <div className="flex flex-col gap-3 rounded-lg border border-rose-300 bg-rose-50 p-4 text-rose-950 sm:flex-row sm:items-center sm:justify-between dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200">
                        <div className="flex items-start gap-3">
                            <XCircle className="mt-0.5 size-5 shrink-0 text-rose-600 dark:text-rose-400" />
                            <div>
                                <p className="text-sm font-semibold">
                                    Pengajuan venue ini ditolak oleh tim kurasi
                                    AdaTempat.
                                </p>
                                <p className="mt-1 text-xs text-rose-900 dark:text-rose-300">
                                    Catatan:{' '}
                                    {venue.rejection_reason ||
                                        'Informasi kurang lengkap atau tidak sesuai standar.'}
                                </p>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={handleSubmitApproval}
                            className="shrink-0 gap-1.5 text-xs font-semibold"
                        >
                            <RefreshCw className="size-3.5" />
                            <span>Ajukan Verifikasi Ulang</span>
                        </Button>
                    </div>
                )}

                {isPending && (
                    <div className="flex items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                        <Clock className="size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                        <div>
                            <p className="text-sm font-semibold">
                                Venue sedang dalam antrean verifikasi
                                superadmin.
                            </p>
                            <p className="mt-0.5 text-xs text-amber-900 dark:text-amber-300">
                                Setelah disetujui, tombol publikasi akan
                                otomatis aktif sehingga venue dapat ditayangkan
                                di marketplace.
                            </p>
                        </div>
                    </div>
                )}

                {/* 2-Column Responsive Layout */}
                <form
                    onSubmit={handleSubmit}
                    className="grid gap-6 lg:grid-cols-3"
                >
                    {/* Left 2 Cols: Form Fields */}
                    <div className="space-y-6 lg:col-span-2">
                        <Card className="border-stone-200/90 dark:border-neutral-800">
                            <CardHeader className="pb-4">
                                <CardTitle className="font-display text-lg font-bold">
                                    Data & Lokasi Venue
                                </CardTitle>
                                <CardDescription className="text-xs text-stone-500">
                                    {isApproved ? (
                                        <span className="flex items-center gap-1 text-amber-700 dark:text-amber-400">
                                            <AlertTriangle className="size-3.5 shrink-0" />
                                            <span>
                                                Perhatian: Mengubah{' '}
                                                <strong>Nama</strong> atau{' '}
                                                <strong>Alamat</strong> venue
                                                yang sudah disetujui akan
                                                otomatis mengembalikan status ke
                                                &apos;Menunggu Verifikasi&apos;
                                                dan menarik tayangan sementara
                                                dari marketplace.
                                            </span>
                                        </span>
                                    ) : (
                                        'Perbarui data tempat kamu untuk memastikan kelancaran verifikasi.'
                                    )}
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-5">
                                {/* Bisnis Pemilik */}
                                <div className="space-y-2">
                                    <Label
                                        htmlFor="business_id"
                                        className="text-xs font-semibold"
                                    >
                                        Bisnis Mitra Pemilik{' '}
                                        <span className="text-rose-600 dark:text-rose-400">
                                            *
                                        </span>
                                    </Label>
                                    <select
                                        id="business_id"
                                        value={data.business_id}
                                        onChange={(e) =>
                                            setData(
                                                'business_id',
                                                e.target.value,
                                            )
                                        }
                                        className="flex h-10 w-full rounded-md border border-stone-200 bg-background px-3 py-2 text-sm shadow-2xs focus-visible:border-cobalt focus-visible:ring-1 focus-visible:ring-cobalt focus-visible:outline-none dark:border-neutral-700"
                                    >
                                        {businesses.map((b) => (
                                            <option key={b.id} value={b.id}>
                                                {b.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.business_id} />
                                </div>

                                {/* Nama Venue */}
                                <div className="space-y-2">
                                    <Label
                                        htmlFor="name"
                                        className="text-xs font-semibold"
                                    >
                                        Nama Venue{' '}
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

                                {/* Kota & Zona Waktu */}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="city"
                                            className="text-xs font-semibold"
                                        >
                                            Kota{' '}
                                            <span className="text-rose-600 dark:text-rose-400">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="city"
                                            type="text"
                                            value={data.city}
                                            onChange={(e) =>
                                                setData('city', e.target.value)
                                            }
                                            className="h-10 border-stone-200 text-sm shadow-2xs focus-visible:border-cobalt focus-visible:ring-1 focus-visible:ring-cobalt dark:border-neutral-700"
                                            aria-invalid={
                                                errors.city ? 'true' : 'false'
                                            }
                                        />
                                        <InputError message={errors.city} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="timezone"
                                            className="text-xs font-semibold"
                                        >
                                            Zona Waktu{' '}
                                            <span className="text-rose-600 dark:text-rose-400">
                                                *
                                            </span>
                                        </Label>
                                        <select
                                            id="timezone"
                                            value={data.timezone}
                                            onChange={(e) =>
                                                setData(
                                                    'timezone',
                                                    e.target.value,
                                                )
                                            }
                                            className="flex h-10 w-full rounded-md border border-stone-200 bg-background px-3 py-2 text-sm shadow-2xs focus-visible:border-cobalt focus-visible:ring-1 focus-visible:ring-cobalt focus-visible:outline-none dark:border-neutral-700"
                                        >
                                            {timezones.map((tz) => (
                                                <option
                                                    key={tz.value}
                                                    value={tz.value}
                                                >
                                                    {tz.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.timezone} />
                                    </div>
                                </div>

                                {/* Alamat Lengkap */}
                                <div className="space-y-2">
                                    <Label
                                        htmlFor="address"
                                        className="text-xs font-semibold"
                                    >
                                        Alamat Lengkap{' '}
                                        <span className="text-rose-600 dark:text-rose-400">
                                            *
                                        </span>
                                    </Label>
                                    <textarea
                                        id="address"
                                        rows={3}
                                        value={data.address}
                                        onChange={(e) =>
                                            setData('address', e.target.value)
                                        }
                                        className="flex w-full rounded-md border border-stone-200 bg-background px-3 py-2 text-sm shadow-2xs focus-visible:border-cobalt focus-visible:ring-1 focus-visible:ring-cobalt focus-visible:outline-none dark:border-neutral-700"
                                    />
                                    <InputError message={errors.address} />
                                </div>

                                {/* Nomor Kontak */}
                                <div className="space-y-2">
                                    <Label
                                        htmlFor="contact_phone"
                                        className="text-xs font-semibold"
                                    >
                                        Nomor Telepon / WhatsApp Kontak
                                    </Label>
                                    <Input
                                        id="contact_phone"
                                        type="tel"
                                        value={data.contact_phone}
                                        onChange={(e) =>
                                            setData(
                                                'contact_phone',
                                                e.target.value,
                                            )
                                        }
                                        className="h-10 border-stone-200 text-sm shadow-2xs focus-visible:border-cobalt focus-visible:ring-1 focus-visible:ring-cobalt dark:border-neutral-700"
                                        aria-invalid={
                                            errors.contact_phone
                                                ? 'true'
                                                : 'false'
                                        }
                                    />
                                    <p className="text-[11px] text-stone-500">
                                        Nomor ini dicantumkan pada tiket
                                        pelanggan untuk konfirmasi check-in.
                                    </p>
                                    <InputError
                                        message={errors.contact_phone}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Danger Zone */}
                        <Card className="border-rose-200 bg-rose-50/30 dark:border-rose-950 dark:bg-rose-950/15">
                            <CardHeader className="pb-3">
                                <CardTitle className="font-display text-base font-bold text-rose-900 dark:text-rose-200">
                                    Zona Berbahaya
                                </CardTitle>
                                <CardDescription className="text-xs text-rose-700 dark:text-rose-400">
                                    Hapus venue ini dari sistem. Venue yang
                                    sudah memiliki riwayat pemesanan tidak dapat
                                    dihapus demi integritas data transaksi.
                                </CardDescription>
                            </CardHeader>
                            <CardFooter className="pt-0">
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    disabled={isDeleting}
                                    onClick={handleDeleteVenue}
                                    className="gap-1.5 text-xs font-semibold"
                                >
                                    <Trash2 className="size-3.5" />
                                    <span>
                                        {isDeleting
                                            ? 'Menghapus...'
                                            : 'Hapus Venue Ini'}
                                    </span>
                                </Button>
                            </CardFooter>
                        </Card>
                    </div>

                    {/* Right Column: Status & Controls */}
                    <div className="space-y-6">
                        {/* Marketplace Publication Card */}
                        <Card className="border-stone-200/90 dark:border-neutral-800">
                            <CardHeader className="pb-3">
                                <CardTitle className="font-display text-base font-bold">
                                    Publikasi Marketplace
                                </CardTitle>
                                <CardDescription className="text-xs text-stone-500">
                                    Kontrol apakah venue ini dapat dicari dan
                                    dipesan oleh pelanggan umum.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-3">
                                <div className="flex items-center justify-between rounded-lg border border-stone-100 bg-stone-50 p-3 dark:border-neutral-800 dark:bg-neutral-900/40">
                                    <div className="flex items-center gap-2">
                                        <Globe className="size-4 text-stone-500" />
                                        <span className="text-xs font-semibold text-foreground">
                                            Status Tayang:
                                        </span>
                                    </div>
                                    <Badge
                                        variant={
                                            venue.is_published
                                                ? 'default'
                                                : 'outline'
                                        }
                                        className={
                                            venue.is_published
                                                ? 'bg-cobalt text-white'
                                                : 'border-stone-300 text-stone-600'
                                        }
                                    >
                                        {venue.is_published
                                            ? 'Tayang Aktif'
                                            : 'Draft / Nonaktif'}
                                    </Badge>
                                </div>

                                {isApproved ? (
                                    <Button
                                        type="button"
                                        variant={
                                            venue.is_published
                                                ? 'outline'
                                                : 'default'
                                        }
                                        size="sm"
                                        onClick={handleTogglePublish}
                                        className={`w-full gap-2 text-xs font-semibold ${
                                            !venue.is_published
                                                ? 'bg-cobalt text-white hover:bg-cobalt-deep'
                                                : 'border-stone-200'
                                        }`}
                                    >
                                        <Globe className="size-3.5" />
                                        <span>
                                            {venue.is_published
                                                ? 'Tarik dari Marketplace'
                                                : 'Tayangkan ke Marketplace'}
                                        </span>
                                    </Button>
                                ) : (
                                    <p className="text-[11px] text-stone-500">
                                        Tombol publikasi terkunci hingga venue
                                        disetujui oleh tim kurasi.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Cover Photo */}
                        <Card className="border-stone-200/90 dark:border-neutral-800">
                            <CardHeader className="pb-3">
                                <CardTitle className="font-display text-base font-bold">
                                    Foto Sampul Venue
                                </CardTitle>
                                <CardDescription className="text-xs text-stone-500">
                                    Foto utama tempat yang disimpan di storage
                                    privat.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-3">
                                {previewUrl ? (
                                    <div className="relative aspect-video w-full overflow-hidden rounded-lg border border-stone-200 bg-stone-100 dark:border-neutral-700">
                                        <img
                                            src={previewUrl}
                                            alt={venue.name}
                                            className="size-full object-cover"
                                        />
                                        {data.cover_photo && (
                                            <button
                                                type="button"
                                                onClick={handleRemoveNewPhoto}
                                                className="absolute top-2 right-2 flex size-7 items-center justify-center rounded-full bg-stone-900/80 text-white shadow-xs hover:bg-stone-900"
                                                title="Batal ganti foto"
                                            >
                                                <X className="size-4" />
                                            </button>
                                        )}
                                    </div>
                                ) : (
                                    <div className="flex aspect-video w-full items-center justify-center rounded-lg border border-stone-200 bg-stone-100 text-stone-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-stone-400">
                                        Belum ada foto sampul
                                    </div>
                                )}

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        fileInputRef.current?.click()
                                    }
                                    className="w-full gap-2 border-stone-200 text-xs text-stone-700 dark:border-neutral-700 dark:text-stone-300"
                                >
                                    <UploadCloud className="size-3.5" />
                                    <span>Ganti Foto Sampul</span>
                                </Button>

                                <input
                                    ref={fileInputRef}
                                    id="cover_photo"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={handleFileChange}
                                    className="hidden"
                                />
                                <InputError message={errors.cover_photo} />
                            </CardContent>
                        </Card>

                        {/* Save Actions */}
                        <div className="flex flex-col gap-2.5">
                            <Button
                                type="submit"
                                disabled={processing}
                                className="h-10 w-full gap-2 bg-cobalt font-semibold text-white hover:bg-cobalt-deep"
                            >
                                <Save className="size-4" />
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
                                <Link href={venueIndex()}>Batal</Link>
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}

OwnerVenuesEdit.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Kelola Venue',
            href: venueIndex(),
        },
        {
            title: 'Ubah Venue',
            href: '#',
        },
    ],
};
