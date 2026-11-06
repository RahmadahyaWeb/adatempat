import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/public/button';
import { Chip } from '@/components/public/chip';
import { Container } from '@/components/public/container';
import { SectionHeading } from '@/components/public/section-heading';
import { Price } from '@/components/public/price';
import { show as venueShow } from '@/routes/venues';
import { index as spacesIndex } from '@/routes/spaces';
import { home } from '@/routes';
import {
    categoryImage,
    dayLabel,
    formatRupiah,
    formatTime,
    timezoneLabel,
} from '@/lib/catalog';
import type { SpaceShowPageProps } from '@/types/catalog';

export default function SpaceShow({ space, venue }: SpaceShowPageProps) {
    return (
        <>
            <Head title={space.name}>
                <meta
                    name="description"
                    content={`Sewa ${space.name} di ${venue.name}, ${venue.city}. ${formatRupiah(space.base_price)} per jam, ${space.capacity} orang.`}
                />
            </Head>

            <section className="relative aspect-[4/3] overflow-hidden bg-sand outline-1 outline-offset-[-1px] outline-black/10 sm:aspect-[16/9] lg:aspect-auto lg:h-[440px]">
                <img
                    src={categoryImage(space.category)}
                    alt=""
                    width={1600}
                    height={900}
                    loading="lazy"
                    decoding="async"
                    className="size-full object-cover object-[center_62%]"
                />
                <span className="absolute top-3 left-3 bg-orange px-2 py-1 font-data text-[11px] font-medium tracking-[0.14em] text-ink uppercase">
                    {space.category_label}
                </span>
            </section>

            <Container className="py-10 md:py-16 lg:py-20">
                <nav aria-label="Breadcrumb" className="mb-6">
                    <ol className="flex flex-wrap gap-2 font-data text-xs text-stone">
                        <li>
                            <Link
                                href={home()}
                                className="inline-flex min-h-11 items-center underline underline-offset-4 hover:underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cobalt"
                            >
                                Beranda
                            </Link>
                        </li>
                        <li className="flex items-center" aria-hidden="true">
                            /
                        </li>
                        <li>
                            <Link
                                href={venueShow(venue.slug)}
                                className="inline-flex min-h-11 items-center underline underline-offset-4 hover:underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cobalt"
                            >
                                {venue.name}
                            </Link>
                        </li>
                        <li className="flex items-center" aria-hidden="true">
                            /
                        </li>
                        <li aria-current="page">{space.name}</li>
                    </ol>
                </nav>

                <div className="mt-6 lg:grid lg:grid-cols-12 lg:gap-12">
                    <div className="lg:col-span-8">
                        <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-3">
                            <Link
                                href={venueShow(venue.slug)}
                                className="font-body text-sm text-stone underline underline-offset-4 hover:underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cobalt"
                            >
                                {venue.name}
                            </Link>
                            <span className="font-data text-xs text-stone">
                                {venue.city}
                            </span>
                        </div>

                        <h1 className="mt-2 font-display text-heading font-extrabold text-ink [font-stretch:82%]">
                            {space.name}
                        </h1>

                        <div className="mt-2 flex flex-wrap gap-2">
                            <Chip
                                href={spacesIndex({
                                    query: { category: space.category },
                                })}
                            >
                                {space.category_label}
                            </Chip>
                            <span className="inline-flex min-h-8 items-center justify-center rounded-control border border-ink/20 px-2 py-0.5 font-data text-xs text-stone">
                                {space.capacity} orang
                            </span>
                        </div>

                        <div className="mt-4 flex items-baseline gap-1.5">
                            <Price
                                amount={space.base_price}
                                className="text-ink"
                            />
                            <span className="font-data text-xs text-stone">
                                / jam
                            </span>
                        </div>

                        {venue.contact_phone && (
                            <p className="mt-3">
                                <a
                                    href={`tel:${venue.contact_phone}`}
                                    className="font-body text-sm text-stone underline underline-offset-4 hover:underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cobalt"
                                >
                                    Hubungi {venue.contact_phone}
                                </a>
                            </p>
                        )}
                    </div>

                    <aside className="mt-10 rounded-control border border-ink/10 bg-ink/5 p-4 lg:sticky lg:top-[80px] lg:mt-0 lg:w-full">
                        <p className="font-body text-sm text-stone">
                            {space.name}
                        </p>
                        <p className="mt-1 font-data text-xs text-stone">
                            {venue.name}, {venue.city}
                        </p>
                        <div className="mt-4 flex items-baseline gap-1.5">
                            <Price
                                amount={space.base_price}
                                className="text-ink"
                            />
                            <span className="font-data text-xs text-stone">
                                / jam
                            </span>
                        </div>
                        <p className="mt-2 font-data text-xs text-stone">
                            {space.capacity} orang
                        </p>
                        <Button disabled className="mt-6 w-full">
                            Pilih tanggal
                        </Button>
                    </aside>
                </div>

                <section className="mt-10 border-t border-ink/15 pt-10 first:mt-0 first:border-t-0 first:pt-0">
                    <SectionHeading eyebrow="Tentang" title={venue.name} />
                    <p className="mt-4 text-base leading-[1.6] text-stone">
                        {venue.address}
                    </p>
                    <p className="mt-2 font-data text-xs text-stone">
                        {venue.city} · {timezoneLabel(venue.timezone)}
                    </p>
                </section>

                <section className="mt-10 border-t border-ink/15 pt-10">
                    <SectionHeading
                        eyebrow="Amenitas"
                        title="Fasilitas yang tersedia."
                    />
                    {space.amenities.length > 0 ? (
                        <ul className="mt-4 grid gap-x-3 gap-y-3 sm:grid-cols-3 lg:grid-cols-4">
                            {space.amenities.map((amenity) => (
                                <li
                                    key={amenity.id}
                                    className="rounded-control border border-ink/10 p-3 text-center font-body text-sm text-ink"
                                >
                                    {amenity.name}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="mt-4 text-stone">
                            Belum ada fasilitas yang terdaftar.
                        </p>
                    )}
                </section>

                <section className="mt-10 border-t border-ink/15 pt-10">
                    <SectionHeading
                        eyebrow="Tambahan"
                        title="Tambahan yang bisa dipilih."
                    />
                    {space.add_ons.length > 0 ? (
                        <ul className="mt-4 space-y-4">
                            {space.add_ons.map((addOn) => (
                                <li
                                    key={addOn.id}
                                    className="border-b border-ink/10 pb-4 last:border-0 last:pb-0"
                                >
                                    <p className="font-body text-sm text-ink">
                                        {addOn.name}
                                    </p>
                                    <p className="mt-1 font-data text-xs text-stone">
                                        {addOn.pricing_type_label}
                                    </p>
                                    <div className="mt-2 flex items-baseline gap-1.5">
                                        <Price
                                            amount={addOn.price}
                                            className="text-ink"
                                        />
                                    </div>
                                    {addOn.stock_quantity !== null &&
                                        addOn.stock_quantity > 0 && (
                                            <p className="mt-2 font-data text-xs text-stone">
                                                Tersedia {addOn.stock_quantity}
                                            </p>
                                        )}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="mt-4 text-stone">
                            Tidak ada tambahan yang tersedia.
                        </p>
                    )}
                </section>

                <section className="mt-10 border-t border-ink/15 pt-10">
                    <SectionHeading eyebrow="Jadwal" title="Jam operasional." />
                    {space.operating_hours.length > 0 ? (
                        <ul className="mt-4 space-y-3">
                            {space.operating_hours.map((hour) => (
                                <li
                                    key={hour.day_of_week}
                                    className="flex flex-col gap-1.5 sm:flex-row sm:items-end sm:justify-between"
                                >
                                    <div>
                                        <p className="font-body text-sm text-ink">
                                            {dayLabel(hour.day_of_week)}
                                        </p>
                                        <p className="font-data text-xs text-stone">
                                            Slot {hour.slot_duration_minutes}{' '}
                                            menit
                                        </p>
                                    </div>
                                    <p className="font-data text-sm text-ink tabular-nums">
                                        {formatTime(hour.open_time)} –{' '}
                                        {formatTime(hour.close_time)}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="mt-4 text-stone">
                            Belum ada jadwal operasional yang tersedia.
                        </p>
                    )}
                </section>

                <section className="mt-10 border-t border-ink/15 pt-10">
                    <SectionHeading eyebrow="Kebijakan" title="Pembatalan." />
                    <p className="mt-4 text-base leading-[1.6] text-stone">
                        {space.cancellation_policy_label} — batalkan hingga{' '}
                        {space.cancellation_cutoff_hours} jam sebelum jadwal,
                        kembali {space.refund_percentage}%.
                    </p>
                </section>
            </Container>
        </>
    );
}
