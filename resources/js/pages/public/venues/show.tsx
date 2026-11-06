import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { AmenityList } from '@/components/public/amenity-list';
import { Breadcrumb } from '@/components/public/breadcrumb';
import { Container } from '@/components/public/container';
import { SectionHeading } from '@/components/public/section-heading';
import { SpaceCard } from '@/components/public/space-card';
import {
    categoryImageAlt,
    formatRupiah,
    isRoomCategory,
    timezoneLabel,
    venueCategoryLabels,
    venueCoverSrc,
} from '@/lib/catalog';
import { cn } from '@/lib/utils';
import { index as spacesIndex } from '@/routes/spaces';
import type { Amenity, VenueShowPageProps } from '@/types/catalog';

const labelStyles = 'font-data text-xs tracking-[0.18em] text-stone uppercase';

function phoneHref(phone: string): string {
    return `tel:${phone.replace(/[^\d+]/g, '')}`;
}

export default function VenueShow({ venue }: VenueShowPageProps) {
    const spaces = venue.spaces;
    const allRooms =
        spaces.length > 0 &&
        spaces.every((space) => isRoomCategory(space.category));
    const allSports =
        spaces.length > 0 &&
        spaces.every((space) => !isRoomCategory(space.category));

    let unitWord = 'lapangan dan ruang';
    let spacesTitle = 'Lapangan dan ruang.';
    let countUnit = 'unit';

    if (spaces.length > 0) {
        if (allRooms) {
            unitWord = 'ruang';
            countUnit = 'ruang';
            spacesTitle = 'Ruang yang bisa disewa.';
        } else if (allSports) {
            unitWord = 'lapangan';
            countUnit = 'lapangan';
            spacesTitle = 'Lapangan yang bisa disewa.';
        } else {
            spacesTitle = 'Lapangan dan ruang yang bisa disewa.';
        }
    }

    const countLabel =
        spaces.length > 0 ? `${spaces.length} ${countUnit}` : 'Daftar unit';

    const categoriesLabel = venueCategoryLabels(venue);
    const minPrice =
        spaces.length > 0
            ? Math.min(...spaces.map((space) => space.base_price))
            : 0;

    const amenityMap = new Map<number, Amenity>();
    for (const space of spaces) {
        for (const amenity of space.amenities) {
            amenityMap.set(amenity.id, amenity);
        }
    }
    const amenities = [...amenityMap.values()];

    const spacesGridClass =
        spaces.length === 1
            ? 'max-w-lg'
            : spaces.length === 2
              ? 'sm:grid-cols-2'
              : 'sm:grid-cols-2 lg:grid-cols-3';

    const coverSrc = venueCoverSrc(venue);
    const futsalCover =
        !venue.cover_photo_path && spaces[0]?.category === 'futsal';
    const coverAlt = venue.cover_photo_path
        ? `Foto ${venue.name}`
        : categoryImageAlt(spaces[0]?.category);
    const coverCategory = spaces[0]?.category_label;
    const coverCaption = coverCategory
        ? `Foto contoh kategori ${coverCategory.toLowerCase()}.`
        : 'Foto contoh.';

    return (
        <>
            <Head title={venue.name}>
                <meta
                    name="description"
                    content={`Sewa ${unitWord} di ${venue.name}, ${venue.city}. Lihat harga per jam, fasilitas, dan kontak venue.`}
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
                            { label: venue.name },
                        ]}
                    />
                </Container>
            </div>

            <section className="pt-2">
                <Container>
                    <div className="grid gap-8 border-b border-ink/15 pb-10 lg:grid-cols-12 lg:items-end lg:gap-12 lg:pb-14">
                        <div className="min-w-0 lg:col-span-7">
                            <p className="flex items-center gap-2 font-data text-xs tracking-[0.18em] text-stone uppercase">
                                <span
                                    aria-hidden="true"
                                    className="size-1.5 shrink-0 bg-orange"
                                />
                                <span className="min-w-0">
                                    {categoriesLabel || 'Venue'} · {venue.city}
                                </span>
                            </p>

                            <h1 className="mt-5 max-w-[16ch] font-display text-display font-extrabold text-balance break-words text-ink [font-stretch:80%]">
                                {venue.name}
                            </h1>
                        </div>

                        <div className="min-w-0 lg:col-span-4 lg:col-start-9">
                            <dl className="flex flex-col">
                                <div className="border-t border-ink/15 pt-3 pb-4">
                                    <dt className={labelStyles}>Alamat</dt>
                                    <dd className="mt-1.5 max-w-[36ch] font-body text-sm leading-[1.6] break-words text-ink">
                                        {venue.address}
                                    </dd>
                                </div>

                                <div className="border-t border-ink/15 pt-3 pb-4">
                                    <dt className={labelStyles}>Zona waktu</dt>
                                    <dd className="mt-1.5 font-body text-sm text-ink">
                                        {timezoneLabel(venue.timezone)}
                                    </dd>
                                </div>

                                {venue.contact_phone && (
                                    <div className="border-t border-ink/15 pt-3">
                                        <dt className={labelStyles}>Telepon</dt>
                                        <dd>
                                            <a
                                                href={phoneHref(
                                                    venue.contact_phone,
                                                )}
                                                className="inline-flex min-h-11 items-center font-body text-sm text-ink underline decoration-1 underline-offset-4 hover:decoration-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt"
                                            >
                                                {venue.contact_phone}
                                            </a>
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </div>
                    </div>
                </Container>
            </section>

            <Container className="mt-8 md:mt-10">
                <figure>
                    <div className="relative aspect-[4/3] overflow-hidden bg-sand outline-1 outline-offset-[-1px] outline-black/10 md:aspect-[16/9] lg:aspect-[21/9]">
                        <img
                            src={coverSrc}
                            alt={coverAlt}
                            width={1200}
                            height={800}
                            fetchPriority="high"
                            decoding="async"
                            className={cn(
                                'absolute inset-0 size-full object-cover',
                                futsalCover
                                    ? 'object-[80%_15%]'
                                    : 'object-center',
                            )}
                        />
                    </div>
                    {!venue.cover_photo_path && (
                        <figcaption className="mt-3 font-data text-xs text-stone">
                            {coverCaption}
                        </figcaption>
                    )}
                </figure>
            </Container>

            <section className="pt-14 pb-20 md:pt-20 md:pb-28">
                <Container>
                    <SectionHeading
                        eyebrow={countLabel}
                        title={spacesTitle}
                        description={
                            minPrice > 0
                                ? `Harga mulai ${formatRupiah(minPrice)} per jam.`
                                : undefined
                        }
                    />

                    {spaces.length > 0 ? (
                        <ul
                            className={cn(
                                'mt-10 grid gap-x-6 gap-y-12',
                                spacesGridClass,
                            )}
                        >
                            {spaces.map((space) => (
                                <li key={space.id} className="min-w-0">
                                    <SpaceCard
                                        space={{ ...space, venue }}
                                        showVenue={false}
                                    />
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <div className="mt-10 max-w-[46ch] border-t border-ink/15 pt-8">
                            <p className="font-body text-base leading-[1.6] text-pretty text-stone">
                                Belum ada lapangan atau ruang yang tayang di
                                venue ini. Cek lagi nanti.
                            </p>
                            <Link
                                href={spacesIndex()}
                                className="group mt-3 inline-flex min-h-11 items-center gap-2 font-body text-sm font-medium text-ink underline decoration-1 underline-offset-4 hover:decoration-2 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cobalt"
                            >
                                Cari venue lain
                                <ArrowRight
                                    aria-hidden="true"
                                    strokeWidth={1.5}
                                    className="size-4 motion-safe:transition-transform motion-safe:duration-150 motion-safe:ease-out motion-safe:group-hover:translate-x-0.5 rtl:rotate-180"
                                />
                            </Link>
                        </div>
                    )}

                    {amenities.length > 0 && (
                        <div className="mt-14 border-t border-ink/15 pt-8 md:mt-20">
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <h2 className="font-display text-2xl font-bold tracking-[-0.02em] text-ink [font-stretch:85%]">
                                    Fasilitas di venue ini.
                                </h2>
                                <p className="font-data text-xs text-stone">
                                    {amenities.length} fasilitas
                                </p>
                            </div>
                            <AmenityList
                                amenities={amenities}
                                className="mt-6 lg:grid-cols-3"
                            />
                        </div>
                    )}
                </Container>
            </section>
        </>
    );
}
