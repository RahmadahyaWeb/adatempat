import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Clock, ShieldCheck, UploadCloud, X } from 'lucide-react';
import { type ChangeEvent, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import {
    create as venueCreate,
    index as venueIndex,
    store as venueStore,
} from '@/routes/owner/venues';

type BusinessOption = {
    id: number;
    name: string;
};

type TimezoneOption = {
    value: string;
    label: string;
};

type Props = {
    businesses: BusinessOption[];
    timezones: TimezoneOption[];
};

export default function OwnerVenuesCreate({ businesses, timezones }: Props) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);

    const { data, setData, post, processing, errors } = useForm<{
        business_id: string;
        name: string;
        city: string;
        address: string;
        timezone: string;
        contact_phone: string;
        cover_photo: File | null;
    }>({
        business_id: businesses.length > 0 ? String(businesses[0].id) : '',
        name: '',
        city: '',
        address: '',
        timezone: timezones.length > 0 ? timezones[0].value : 'Asia/Jakarta',
        contact_phone: '',
        cover_photo: null,
    });

    const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('cover_photo', file);
            setPreviewUrl(URL.createObjectURL(file));
        }
    };

    const handleRemovePhoto = () => {
        setData('cover_photo', null);
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            setPreviewUrl(null);
        }
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(venueStore().url, {
            forceFormData: true,
        });
    };

    return (
        <>
            <Head title="Tambah Venue Baru" />

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
                    <span className="text-xs font-bold tracking-wider text-cobalt uppercase">
                        Formulir Pendaftaran
                    </span>
                    <h1 className="mt-0.5 font-display text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">
                        Tambah Venue Baru
                    </h1>
                    <p className="mt-1 text-sm text-stone-600 dark:text-stone-400">
                        Daftarkan tempat sewa kamu. Venue baru akan diverifikasi
                        oleh tim kurasi sebelum dapat ditayangkan di
                        marketplace.
                    </p>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="grid gap-6 lg:grid-cols-3"
                >
                    {/* Main Form Fields (2 Cols) */}
                    <div className="space-y-6 lg:col-span-2">
                        <Card className="border-stone-200/90 dark:border-neutral-800">
                            <CardHeader className="pb-4">
                                <CardTitle className="font-display text-lg font-bold">
                                    Informasi Lokasi & Tempat
                                </CardTitle>
                                <CardDescription className="text-xs text-stone-500">
                                    Lengkapi data venue secara akurat untuk
                                    memudahkan verifikasi dan penemuan oleh
                                    pelanggan.
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
                                        placeholder="Contoh: Borneo Arena Futsal & Badminton"
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
                                            placeholder="Contoh: Samarinda"
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
                                        <p className="text-[11px] text-stone-500">
                                            Dipakai untuk perhitungan jam slot
                                            dan batas check-in tiket.
                                        </p>
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
                                        placeholder="Tuliskan nama jalan, nomor, kelurahan, dan patokan terdekat..."
                                        value={data.address}
                                        onChange={(e) =>
                                            setData('address', e.target.value)
                                        }
                                        className="flex w-full rounded-md border border-stone-200 bg-background px-3 py-2 text-sm shadow-2xs focus-visible:border-cobalt focus-visible:ring-1 focus-visible:ring-cobalt focus-visible:outline-none dark:border-neutral-700"
                                    />
                                    <InputError message={errors.address} />
                                </div>

                                {/* Nomor Telepon Kontak */}
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
                                        placeholder="Contoh: 08123456789 atau +628123456789"
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
                                        Nomor pengelola ini tertera pada tiket
                                        pelanggan untuk konfirmasi saat tiba di
                                        lokasi.
                                    </p>
                                    <InputError
                                        message={errors.contact_phone}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar Column: Cover Photo & Actions */}
                    <div className="space-y-6">
                        {/* Cover Photo Upload */}
                        <Card className="border-stone-200/90 dark:border-neutral-800">
                            <CardHeader className="pb-3">
                                <CardTitle className="font-display text-base font-bold">
                                    Foto Sampul Venue
                                </CardTitle>
                                <CardDescription className="text-xs text-stone-500">
                                    Foto utama tempat kamu yang tampil di kartu
                                    katalog publik.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-3">
                                {previewUrl ? (
                                    <div className="relative aspect-video w-full overflow-hidden rounded-lg border border-stone-200 bg-stone-100 dark:border-neutral-700">
                                        <img
                                            src={previewUrl}
                                            alt="Pratinjau cover venue"
                                            className="size-full object-cover"
                                        />
                                        <button
                                            type="button"
                                            onClick={handleRemovePhoto}
                                            className="absolute top-2 right-2 flex size-7 items-center justify-center rounded-full bg-stone-900/80 text-white shadow-xs hover:bg-stone-900"
                                            title="Hapus foto"
                                        >
                                            <X className="size-4" />
                                        </button>
                                    </div>
                                ) : (
                                    <label
                                        htmlFor="cover_photo"
                                        className="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-stone-300 p-6 text-center transition-colors focus-within:ring-2 focus-within:ring-cobalt focus-within:ring-offset-2 hover:border-cobalt hover:bg-cobalt/5 dark:border-neutral-700 dark:hover:border-cobalt"
                                    >
                                        <div className="mb-2 flex size-10 items-center justify-center rounded-full bg-stone-100 text-stone-500 dark:bg-neutral-800">
                                            <UploadCloud className="size-5" />
                                        </div>
                                        <p className="text-xs font-semibold text-foreground">
                                            Unggah Foto Sampul
                                        </p>
                                        <p className="mt-0.5 text-[11px] text-stone-500">
                                            JPEG, PNG, atau WEBP (maks. 3 MB)
                                        </p>
                                    </label>
                                )}

                                <input
                                    ref={fileInputRef}
                                    id="cover_photo"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={handleFileChange}
                                    className="sr-only"
                                />
                                <InputError message={errors.cover_photo} />
                            </CardContent>
                        </Card>

                        {/* Onboarding Info Card */}
                        <Card className="border-stone-200/90 bg-stone-50/70 dark:border-neutral-800 dark:bg-neutral-900/40">
                            <CardContent className="p-4 text-xs text-stone-600 dark:text-stone-400">
                                <div className="flex items-start gap-2.5">
                                    <Clock className="mt-0.5 size-4 shrink-0 text-amber-600" />
                                    <div>
                                        <p className="font-semibold text-foreground">
                                            Alur Verifikasi Cepat
                                        </p>
                                        <p className="mt-1 leading-relaxed">
                                            Venue yang didaftarkan akan
                                            berstatus{' '}
                                            <strong className="text-amber-700 dark:text-amber-300">
                                                Menunggu Verifikasi
                                            </strong>
                                            . Setelah disetujui kurasi, kamu
                                            dapat mengaktifkan publikasi dan
                                            menambahkan ruang/lapangan.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Action Buttons */}
                        <div className="flex flex-col gap-2.5">
                            <Button
                                type="submit"
                                disabled={processing}
                                className="h-10 w-full gap-2 bg-cobalt font-semibold text-white hover:bg-cobalt-deep"
                            >
                                <ShieldCheck className="size-4" />
                                <span>
                                    {processing
                                        ? 'Menyimpan...'
                                        : 'Simpan & Daftarkan Venue'}
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

OwnerVenuesCreate.layout = {
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
            title: 'Tambah Venue',
            href: venueCreate(),
        },
    ],
};
