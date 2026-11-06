import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Check,
    Copy,
    Info,
    Sparkles,
} from 'lucide-react';
import { type FormEventHandler, useMemo, useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index as venueIndex } from '@/routes/owner/venues';
import { index as spaceIndex } from '@/routes/owner/venues/spaces';
import { update as schedulesUpdate } from '@/routes/owner/venues/spaces/schedules';

type ScheduleItem = {
    id?: number;
    day_of_week: number;
    open_time: string;
    close_time: string;
    slot_duration_minutes: number;
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
    version: {
        id: number;
        version: number;
        effective_from: string;
        effective_to: string | null;
    } | null;
    schedules: ScheduleItem[];
    hasFutureBookings: boolean;
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

function buildDefaultSchedules(existing: ScheduleItem[]): ScheduleItem[] {
    const map = new Map<number, ScheduleItem>();
    for (const item of existing) {
        map.set(item.day_of_week, item);
    }

    return DAYS.map(({ day }) => {
        const found = map.get(day);
        if (found) {
            return {
                day_of_week: day,
                open_time: found.open_time,
                close_time: found.close_time,
                slot_duration_minutes: found.slot_duration_minutes,
                is_active: found.is_active,
            };
        }
        return {
            day_of_week: day,
            open_time: '08:00',
            close_time: '22:00',
            slot_duration_minutes: 60,
            is_active: true,
        };
    });
}

function generateSimulationSlots(
    open: string,
    close: string,
    duration: number,
): string[] {
    if (!open || !close || duration <= 0) return [];

    const [openH, openM] = open.split(':').map(Number);
    const [closeH, closeM] = close.split(':').map(Number);

    if (isNaN(openH) || isNaN(openM) || isNaN(closeH) || isNaN(closeM)) {
        return [];
    }

    let current = openH * 60 + openM;
    const end = closeH * 60 + closeM;

    if (current >= end) return [];

    const slots: string[] = [];
    while (current + duration <= end) {
        const startH = String(Math.floor(current / 60)).padStart(2, '0');
        const startM = String(current % 60).padStart(2, '0');

        const next = current + duration;
        const endH = String(Math.floor(next / 60)).padStart(2, '0');
        const endM = String(next % 60).padStart(2, '0');

        slots.push(`${startH}:${startM} - ${endH}:${endM}`);
        current = next;
    }

    return slots;
}

export default function OwnerSpacesSchedules({
    venue,
    space,
    version,
    schedules,
    hasFutureBookings,
}: Props) {
    const { flash } = usePage<{
        flash?: { success?: string; error?: string };
    }>().props;

    const { data, setData, put, processing, errors } = useForm({
        schedules: buildDefaultSchedules(schedules),
    });

    const [previewDay, setPreviewDay] = useState<number>(1);

    const activePreviewSchedule = useMemo(() => {
        return (
            data.schedules.find((s) => s.day_of_week === previewDay) ??
            data.schedules[0]
        );
    }, [data.schedules, previewDay]);

    const simulatedSlots = useMemo(() => {
        if (!activePreviewSchedule || !activePreviewSchedule.is_active) {
            return [];
        }
        return generateSimulationSlots(
            activePreviewSchedule.open_time,
            activePreviewSchedule.close_time,
            activePreviewSchedule.slot_duration_minutes,
        );
    }, [activePreviewSchedule]);

    const handleFieldChange = (
        dayIndex: number,
        field: keyof ScheduleItem,
        value: string | number | boolean,
    ) => {
        const next = [...data.schedules];
        next[dayIndex] = {
            ...next[dayIndex],
            [field]: value,
        };
        setData('schedules', next);
    };

    const handleApplyToAll = (sourceDayIndex: number) => {
        const source = data.schedules[sourceDayIndex];
        const next = data.schedules.map((item) => ({
            ...item,
            open_time: source.open_time,
            close_time: source.close_time,
            slot_duration_minutes: source.slot_duration_minutes,
            is_active: item.is_active,
        }));
        setData('schedules', next);
    };

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        put(
            schedulesUpdate({
                venue: venue.slug,
                space: space.slug,
            }).url,
        );
    };

    return (
        <>
            <Head title={`Jadwal Operasional - ${space.name}`} />

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
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-bold tracking-wider text-cobalt uppercase">
                                {venue.name} · Mesin Slot Waktu
                            </span>
                            {version && (
                                <Badge
                                    variant="outline"
                                    className="border-cobalt/30 bg-cobalt/5 text-[11px] font-semibold text-cobalt dark:border-cobalt/40 dark:bg-cobalt/15 dark:text-blue-300"
                                >
                                    Versi Jadwal #{version.version}
                                </Badge>
                            )}
                        </div>
                        <h1 className="mt-1 font-display text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">
                            Atur Jam Operasional: {space.name}
                        </h1>
                        <p className="mt-1 text-sm text-stone-600 dark:text-stone-400">
                            Konfigurasikan jam buka, jam tutup, dan durasi slot
                            untuk setiap hari dalam seminggu.
                        </p>
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

                {/* Future Bookings Alert */}
                {hasFutureBookings && (
                    <div className="flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50/80 p-4 text-xs text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                        <Info className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                        <div>
                            <p className="font-semibold text-amber-950 dark:text-amber-100">
                                Space Memiliki Pemesanan Mendatang
                            </p>
                            <p className="mt-0.5 leading-relaxed">
                                Menyimpan perubahan jadwal akan otomatis membuat
                                versi jadwal baru (v
                                {(version?.version ?? 1) + 1}) agar data tiket
                                dan waktu booking yang sudah dipesan pelanggan
                                tidak tergeser.
                            </p>
                        </div>
                    </div>
                )}

                <form
                    onSubmit={handleSubmit}
                    className="grid gap-6 lg:grid-cols-3"
                >
                    {/* Weekly Schedule Form Matrix */}
                    <div className="space-y-4 lg:col-span-2">
                        <Card className="border-stone-200/90 dark:border-neutral-800">
                            <CardHeader className="pb-3">
                                <CardTitle className="font-display text-base font-bold">
                                    Matriks 7 Hari Operasional
                                </CardTitle>
                                <CardDescription className="text-xs text-stone-500">
                                    Atur apakah hari tersebut buka atau libur,
                                    beserta rentang jam dan durasi satuan slot.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-4">
                                {data.schedules.map((schedule, index) => {
                                    const dayMeta = DAYS.find(
                                        (d) => d.day === schedule.day_of_week,
                                    );
                                    const dayLabel =
                                        dayMeta?.label ??
                                        `Hari #${schedule.day_of_week}`;

                                    return (
                                        <div
                                            key={schedule.day_of_week}
                                            className={`rounded-lg border p-4 transition-colors ${
                                                schedule.is_active
                                                    ? 'border-stone-200 bg-background dark:border-neutral-700'
                                                    : 'border-stone-200/60 bg-stone-50/60 opacity-60 dark:border-neutral-800 dark:bg-neutral-900/40'
                                            }`}
                                        >
                                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                {/* Day Toggle */}
                                                <div className="flex items-center gap-3">
                                                    <Checkbox
                                                        id={`day-${schedule.day_of_week}-active`}
                                                        checked={
                                                            schedule.is_active
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            handleFieldChange(
                                                                index,
                                                                'is_active',
                                                                Boolean(
                                                                    checked,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                    <Label
                                                        htmlFor={`day-${schedule.day_of_week}-active`}
                                                        className="cursor-pointer font-display text-sm font-bold text-foreground"
                                                    >
                                                        {dayLabel}
                                                    </Label>
                                                    <Badge
                                                        variant={
                                                            schedule.is_active
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                        className="text-[10px]"
                                                    >
                                                        {schedule.is_active
                                                            ? 'Buka'
                                                            : 'Tutup'}
                                                    </Badge>
                                                </div>

                                                {/* Quick helper */}
                                                {schedule.is_active && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleApplyToAll(
                                                                index,
                                                            )
                                                        }
                                                        className="h-7 gap-1 text-[11px] text-stone-500 hover:text-foreground"
                                                        title="Salin jam dan durasi hari ini ke seluruh hari"
                                                    >
                                                        <Copy className="size-3" />
                                                        <span>
                                                            Terapkan ke Semua
                                                        </span>
                                                    </Button>
                                                )}
                                            </div>

                                            {/* Times row */}
                                            {schedule.is_active && (
                                                <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                    <div className="space-y-1">
                                                        <Label
                                                            htmlFor={`open-${schedule.day_of_week}`}
                                                            className="text-[11px] font-semibold text-stone-600 dark:text-stone-400"
                                                        >
                                                            Jam Buka
                                                        </Label>
                                                        <Input
                                                            id={`open-${schedule.day_of_week}`}
                                                            type="time"
                                                            value={
                                                                schedule.open_time
                                                            }
                                                            onChange={(e) =>
                                                                handleFieldChange(
                                                                    index,
                                                                    'open_time',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="h-9 text-xs"
                                                        />
                                                    </div>

                                                    <div className="space-y-1">
                                                        <Label
                                                            htmlFor={`close-${schedule.day_of_week}`}
                                                            className="text-[11px] font-semibold text-stone-600 dark:text-stone-400"
                                                        >
                                                            Jam Tutup
                                                        </Label>
                                                        <Input
                                                            id={`close-${schedule.day_of_week}`}
                                                            type="time"
                                                            value={
                                                                schedule.close_time
                                                            }
                                                            onChange={(e) =>
                                                                handleFieldChange(
                                                                    index,
                                                                    'close_time',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="h-9 text-xs"
                                                        />
                                                    </div>

                                                    <div className="space-y-1">
                                                        <Label
                                                            htmlFor={`duration-${schedule.day_of_week}`}
                                                            className="text-[11px] font-semibold text-stone-600 dark:text-stone-400"
                                                        >
                                                            Durasi Tiap Slot
                                                        </Label>
                                                        <select
                                                            id={`duration-${schedule.day_of_week}`}
                                                            value={
                                                                schedule.slot_duration_minutes
                                                            }
                                                            onChange={(e) =>
                                                                handleFieldChange(
                                                                    index,
                                                                    'slot_duration_minutes',
                                                                    parseInt(
                                                                        e.target
                                                                            .value,
                                                                        10,
                                                                    ),
                                                                )
                                                            }
                                                            className="flex h-9 w-full rounded-md border border-stone-200 bg-background px-2 py-1 text-xs shadow-2xs focus-visible:border-cobalt focus-visible:ring-1 focus-visible:ring-cobalt focus-visible:outline-none dark:border-neutral-700"
                                                        >
                                                            <option value={30}>
                                                                30 Menit
                                                            </option>
                                                            <option value={60}>
                                                                60 Menit (1 Jam)
                                                            </option>
                                                            <option value={90}>
                                                                90 Menit (1.5
                                                                Jam)
                                                            </option>
                                                            <option value={120}>
                                                                120 Menit (2
                                                                Jam)
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}

                                {errors.schedules && (
                                    <InputError message={errors.schedules} />
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar: Live Slot Simulation Preview & Save */}
                    <div className="space-y-6">
                        {/* Live Slot Simulation Preview Card */}
                        <Card className="border-stone-200/90 dark:border-neutral-800">
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 font-display text-base font-bold">
                                    <Sparkles className="size-4 text-cobalt" />
                                    <span>Simulasi Slot Pelanggan</span>
                                </CardTitle>
                                <CardDescription className="text-xs text-stone-500">
                                    Pratinjau langsung bagaimana pilihan jam
                                    tampil untuk pelanggan yang memesan di
                                    katalog.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-4">
                                {/* Preview Day Selector */}
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-semibold">
                                        Pilih Hari Simulasi
                                    </Label>
                                    <div className="flex flex-wrap gap-1">
                                        {DAYS.map(({ day, label }) => (
                                            <Button
                                                key={day}
                                                type="button"
                                                variant={
                                                    previewDay === day
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                size="sm"
                                                onClick={() =>
                                                    setPreviewDay(day)
                                                }
                                                className={`h-7 px-2.5 text-xs font-medium ${
                                                    previewDay === day
                                                        ? 'bg-cobalt text-white hover:bg-cobalt-deep'
                                                        : 'border-stone-200 dark:border-neutral-700'
                                                }`}
                                            >
                                                {label}
                                            </Button>
                                        ))}
                                    </div>
                                </div>

                                {/* Generated Slots Display */}
                                <div className="rounded-lg border border-stone-200 bg-stone-50/50 p-3 dark:border-neutral-800 dark:bg-neutral-900/40">
                                    <div className="mb-2 flex items-center justify-between text-xs font-semibold text-stone-700 dark:text-stone-300">
                                        <span>Daftar Slot Terbentuk:</span>
                                        <Badge
                                            variant="secondary"
                                            className="text-[10px]"
                                        >
                                            {simulatedSlots.length} Slot
                                        </Badge>
                                    </div>

                                    {!activePreviewSchedule?.is_active ? (
                                        <p className="py-4 text-center text-xs text-stone-500 italic">
                                            Space tutup pada hari ini (tidak ada
                                            slot yang dibuka).
                                        </p>
                                    ) : simulatedSlots.length === 0 ? (
                                        <p className="py-4 text-center text-xs text-amber-600 italic">
                                            Rentang jam buka-tutup tidak cukup
                                            untuk durasi slot yang dipilih.
                                        </p>
                                    ) : (
                                        <div className="flex max-h-60 flex-wrap gap-1.5 overflow-y-auto pt-1">
                                            {simulatedSlots.map((slot) => (
                                                <span
                                                    key={slot}
                                                    className="inline-flex items-center rounded-md border border-stone-200 bg-background px-2 py-1 text-[11px] font-semibold text-foreground shadow-2xs dark:border-neutral-700"
                                                >
                                                    {slot}
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Save Action */}
                        <div className="space-y-2">
                            <Button
                                type="submit"
                                disabled={processing}
                                className="h-10 w-full gap-2 bg-cobalt text-white hover:bg-cobalt-deep"
                            >
                                <Check className="size-4" />
                                <span>
                                    {processing
                                        ? 'Menyimpan Jadwal...'
                                        : 'Simpan Jadwal Operasional'}
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

OwnerSpacesSchedules.layout = {
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
