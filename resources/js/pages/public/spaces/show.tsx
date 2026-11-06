import { Head, Link } from '@inertiajs/react';
import { AmenityList } from '@/components/public/amenity-list';
import { Breadcrumb } from '@/components/public/breadcrumb';
import { Container } from '@/components/public/container';
import { Price } from '@/components/public/price';
import { SpaceSlotPicker } from '@/components/public/space-slot-picker';
import { cn } from '@/lib/utils';
import {
    categoryImage,
    categoryImageAlt,
    dayLabel,
    formatRupiah,
    formatTime,
    isRoomCategory,
    timezoneLabel,
} from '@/lib/catalog';
import { index as spacesIndex } from '@/routes/spaces';
import { show as venueShow } from '@/routes/venues';
import type { OperatingHour, SpaceShowPageProps } from '@/types/catalog';

const labelStyles = 'font-data text-xs tracking-[0.18em] text-stone uppercase';

const addOnUnits: Record<string, string> = {
    per_hour: 'per jam',
    per_item: 'per item',
    per_booking: 'per pemesanan',
};

const refundFormat = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 2,
});

const orderedDays = [1, 2, 3, 4, 5, 6, 0];

type HoursRow = {
    label: string;
    time: string;
};

function dayRangeLabel(start: number, end: number): string {
    if (start === end) {
        return dayLabel(orderedDays[start]);
    }

    return `${dayLabel(orderedDays[start])}–${dayLabel(orderedDays[end])}`;
}

function groupOperatingHours(hours: OperatingHour[]): HoursRow[] {
    if (hours.length === 0) {
        return [];
    }

    const byDay = new Map<number, OperatingHour[]>();
    for (const hour of hours) {
        const entries = byDay.get(hour.day_of_week) ?? [];
        entries.push(hour);
        byDay.set(hour.day_of_week, entries);
    }

    const timeForDay = (day: number): string => {
        const entries = byDay.get(day);

        if (!entries || entries.length === 0) {
            return 'Tutup';
        }

        return entries
            .sort((a, b) => a.open_time.localeCompare(b.open_time))
            .map(
                (entry) =>
                    `${formatTime(entry.open_time)}–${formatTime(entry.close_time)}`,
            )
            .join(', ');
    };

    const groups: { start: number; end: number; time: string }[] = [];

    orderedDays.forEach((day, index) => {
        const time = timeForDay(day);
        const last = groups.at(-1);

        if (last && last.time === time) {
            last.end = index;
        } else {
            groups.push({ start: index, end: index, time });
        }
    });

    return groups.map((group) => {
        const everyDay =
            groups.length === 1 && group.start === 0 && group.end === 6;

        return {
            label: everyDay
                ? 'Setiap hari'
                : dayRangeLabel(group.start, group.end),
            time: group.time,
        };
    });
}

export default function SpaceShow({
    space,
    venue,
    initial_date,
    initial_slots,
}: SpaceShowPageProps) {
    const categoryName = space.category_label
        .toLowerCase()
        .replace(/^ruang\s+/, '');
    const subject =
        space.category === 'other'
            ? 'unit'
            : isRoomCategory(space.category)
              ? `ruang ${categoryName}`
              : `lapangan ${categoryName}`;
    const subjectLabel = `${subject.charAt(0).toUpperCase()}${subject.slice(1)}`;
    const about = space.capacity
        ? `${subjectLabel} berkapasitas ${space.capacity} orang di ${venue.name}, ${venue.city}.`
        : `${subjectLabel} di ${venue.name}, ${venue.city}.`;

    const refund = refundFormat.format(space.refund_percentage);
    const cutoffText =
        space.cancellation_cutoff_hours > 0
            ? `paling lambat ${space.cancellation_cutoff_hours} jam sebelum jadwal`
            : 'sebelum jadwal dimulai';
    const cancellationDetail =
        space.refund_percentage > 0
            ? `Pengembalian dana ${refund}% bila dibatalkan ${cutoffText}.`
            : null;

    const hoursRows = groupOperatingHours(space.operating_hours);

    return (
        <>
            <Head title={`${space.name} di ${venue.name}`}>
                <meta
                    name="description"
                    content={`${space.name} di ${venue.name}, ${venue.city}. Harga ${formatRupiah(space.base_price)} per jam. Lihat fasilitas, layanan tambahan, dan jam operasional.`}
                />
            </Head>

            <div className="pt-5 md:pt-6">
                <Container>
                    <Breadcrumb
                        items={[
                            {
                                label: 'Cari venue',
                                href: spacesIndex(),
                            },
                            {
                                label: venue.name,
                                href: venueShow(venue.slug),
                            },
                            { label: space.name },
                        ]}
                    />
                </Container>
            </div>

            <Container className="mt-2 pb-20 md:pb-28">
                <div className="grid gap-10 lg:grid-cols-12 lg:gap-12">
                    <div className="min-w-0 lg:col-span-6 xl:col-span-6">
                        <figure className="lg:sticky lg:top-24">
                            <div className="relative aspect-[4/3] overflow-hidden bg-sand outline-1 outline-offset-[-1px] outline-black/10">
                                <img
                                    src={categoryImage(space.category)}
                                    alt={categoryImageAlt(space.category)}
                                    width={1200}
                                    height={900}
                                    fetchPriority="high"
                                    decoding="async"
                                    className={cn(
                                        'absolute inset-0 size-full object-cover',
                                        space.category === 'futsal'
                                            ? 'object-[80%_15%]'
                                            : 'object-center',
                                    )}
                                />
                            </div>
                            <figcaption className="mt-3 font-data text-xs text-stone">
                                Foto contoh kategori{' '}
                                {space.category_label.toLowerCase()}.
                            </figcaption>
                        </figure>
                    </div>

                    <div className="flex min-w-0 flex-col gap-6 lg:col-span-6 xl:col-span-6">
                        <div>
                            <p className="flex items-center gap-2 font-data text-xs tracking-[0.18em] text-stone uppercase">
                                <span
                                    aria-hidden="true"
                                    className="size-1.5 shrink-0 bg-orange"
                                />
                                <span className="min-w-0">
                                    {space.category_label} · {venue.city}
                                </span>
                            </p>

                            <h1 className="mt-3 font-display text-heading font-extrabold text-balance break-words text-ink [font-stretch:82%]">
                                {space.name}
                            </h1>

                            <div className="mt-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <Price
                                    amount={space.base_price}
                                    className="text-2xl font-medium text-ink sm:text-3xl"
                                />
                                <span className="font-data text-sm text-stone">
                                    / jam
                                </span>
                                {space.capacity && (
                                    <>
                                        <span className="font-data text-xs text-stone/40">
                                            ·
                                        </span>
                                        <span className="font-data text-xs text-stone">
                                            Kapasitas {space.capacity} orang
                                        </span>
                                    </>
                                )}
                            </div>

                            <p className="mt-4 max-w-[56ch] font-body text-base leading-[1.65] text-pretty text-stone">
                                {about}
                            </p>
                        </div>

                        <div className="border-t border-ink/15 pt-3">
                            <Link
                                href={venueShow(venue.slug)}
                                className="inline-flex min-h-11 items-center font-display text-base font-bold tracking-[-0.02em] break-words text-ink [font-stretch:85%] underline decoration-1 underline-offset-4 hover:decoration-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt"
                            >
                                {venue.name}
                            </Link>
                            <address className="font-body text-xs leading-[1.6] break-words text-stone not-italic">
                                {venue.address}
                            </address>
                        </div>

                        <SpaceSlotPicker
                            space={space}
                            venue={venue}
                            initialDate={initial_date}
                            initialSlots={initial_slots}
                        />
                    </div>
                </div>

                <div className="mt-14 border-t border-ink/15 pt-10 md:mt-18 md:pt-12">
                    <div className="mb-8">
                        <p className={labelStyles}>
                            Fasilitas &amp; Ketentuan Tempat
                        </p>
                        <h2 className="mt-1 font-display text-2xl font-bold tracking-[-0.02em] text-ink [font-stretch:85%]">
                            Fasilitas dan jam operasional.
                        </h2>
                    </div>

                    <div className="grid gap-10 md:grid-cols-2 lg:gap-14">
                        <div className="space-y-8">
                            <section
                                aria-labelledby="facilities-heading"
                                className="border-t border-ink/15 pt-5"
                            >
                                <div className="flex items-baseline justify-between">
                                    <h3
                                        id="facilities-heading"
                                        className={labelStyles}
                                    >
                                        Sudah termasuk
                                    </h3>
                                    {space.amenities.length > 0 && (
                                        <span className="font-data text-xs text-stone">
                                            {space.amenities.length} fasilitas
                                        </span>
                                    )}
                                </div>

                                {space.amenities.length > 0 ? (
                                    <AmenityList
                                        amenities={space.amenities}
                                        className="mt-4"
                                    />
                                ) : (
                                    <p className="mt-3 font-body text-sm leading-[1.6] text-stone">
                                        Belum ada fasilitas yang dicatat untuk
                                        unit ini.
                                    </p>
                                )}
                            </section>

                            <section
                                aria-labelledby="addons-heading"
                                className="border-t border-ink/15 pt-5"
                            >
                                <div className="flex items-baseline justify-between">
                                    <h3
                                        id="addons-heading"
                                        className={labelStyles}
                                    >
                                        Layanan tambahan
                                    </h3>
                                    {space.add_ons.length > 0 && (
                                        <span className="font-data text-xs text-stone">
                                            {space.add_ons.length} opsi
                                        </span>
                                    )}
                                </div>

                                {space.add_ons.length > 0 ? (
                                    <ul className="mt-2 divide-y divide-ink/10">
                                        {space.add_ons.map((addOn) => {
                                            const unit =
                                                addOnUnits[
                                                    addOn.pricing_type
                                                ] ??
                                                addOn.pricing_type_label.toLowerCase();

                                            return (
                                                <li
                                                    key={addOn.id}
                                                    className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3"
                                                >
                                                    <span className="min-w-0 font-body text-sm break-words text-ink">
                                                        {addOn.name}
                                                    </span>
                                                    <span className="ms-auto flex flex-wrap items-baseline gap-x-1.5">
                                                        <Price
                                                            amount={addOn.price}
                                                            className="text-ink"
                                                        />
                                                        <span className="font-data text-xs text-stone">
                                                            {unit}
                                                        </span>
                                                    </span>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                ) : (
                                    <p className="mt-3 font-body text-sm leading-[1.6] text-stone">
                                        Belum ada layanan tambahan untuk unit
                                        ini.
                                    </p>
                                )}
                            </section>
                        </div>

                        <div className="space-y-8">
                            <section
                                aria-labelledby="hours-heading"
                                className="border-t border-ink/15 pt-5"
                            >
                                <div className="flex items-baseline justify-between">
                                    <h3
                                        id="hours-heading"
                                        className={labelStyles}
                                    >
                                        Jam operasional
                                    </h3>
                                    <span className="font-data text-xs text-stone">
                                        {timezoneLabel(venue.timezone)}
                                    </span>
                                </div>

                                {hoursRows.length > 0 ? (
                                    <dl className="mt-2 divide-y divide-ink/10">
                                        {hoursRows.map((row) => (
                                            <div
                                                key={row.label}
                                                className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3"
                                            >
                                                <dt className="font-body text-sm text-ink">
                                                    {row.label}
                                                </dt>
                                                <dd className="font-data text-sm text-stone tabular-nums">
                                                    {row.time}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                ) : (
                                    <p className="mt-3 font-body text-sm leading-[1.6] text-stone">
                                        Jam operasional belum diatur.
                                    </p>
                                )}
                            </section>

                            <section
                                aria-labelledby="cancellation-heading"
                                className="border-t border-ink/15 pt-5"
                            >
                                <h3
                                    id="cancellation-heading"
                                    className={labelStyles}
                                >
                                    Kebijakan pembatalan
                                </h3>
                                <p className="mt-3 font-body text-sm font-semibold text-ink">
                                    {space.cancellation_policy_label}
                                </p>
                                {cancellationDetail && (
                                    <p className="mt-1.5 max-w-[52ch] font-body text-sm leading-[1.6] text-stone">
                                        {cancellationDetail}
                                    </p>
                                )}
                            </section>
                        </div>
                    </div>
                </div>
            </Container>
        </>
    );
}
