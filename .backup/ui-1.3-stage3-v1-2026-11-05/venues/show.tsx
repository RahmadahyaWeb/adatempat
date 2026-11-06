import { Head, Link } from '@inertiajs/react';
import { Container } from '@/components/public/container';
import { SectionHeading } from '@/components/public/section-heading';
import { SpaceCard } from '@/components/public/space-card';
import { home } from '@/routes';
import {
    timezoneLabel,
    venueCategoryLabels,
    venueCoverSrc,
} from '@/lib/catalog';
import type { VenueShowPageProps } from '@/types/catalog';

export default function VenueShow({ venue }: VenueShowPageProps) {
    const hasCover = Boolean(venue.cover_photo_path);
    const allAmenities = Array.from(
        new Set(
            venue.spaces.flatMap((space) =>
                space.amenities.map((amenity) => amenity.name),
            ),
        ),
    );

    return (
        <>
            <Head title={venue.name}>
                <meta
                    name="description"
                    content={`Lapangan dan ruang di ${venue.name}, ${venue.city}. Lihat harga per jam dan pilih lapangan atau ruang yang tersedia.`}
                />
            </Head>

            <section className="relative aspect-[4/3] overflow-hidden bg-sand outline-1 outline-offset-[-1px] outline-black/10 sm:aspect-[16/9] lg:aspect-auto lg:h-[560px]">
                <img
                    src={venueCoverSrc(venue)}
                    alt={hasCover ? `Foto luar ${venue.name}` : ''}
                    width={1600}
                    height={900}
                    loading="lazy"
                    decoding="async"
                    className="size-full object-cover object-[center_62%]"
                />
                <span className="absolute top-3 left-3 bg-orange px-2 py-1 font-data text-[11px] font-medium tracking-[0.14em] text-ink uppercase">
                    {venueCategoryLabels(venue)}
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
                        <li aria-current="page">{venue.name}</li>
                    </ol>
                </nav>

                <div className="flex flex-col gap-4 gap-x-6 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="font-display text-heading font-extrabold text-ink [font-stretch:82%]">
                            {venue.name}
                        </h1>
                        <p className="mt-1 font-body text-base text-stone">
                            {venue.city}
                        </p>
                    </div>
                </div>

                {/* Info strip: alamat, waktu lokal, telepon. */}
                <div className="mt-6 rounded-control border border-ink/10 bg-ink/5 p-4 sm:grid sm:grid-cols-3 sm:gap-8">
                    <div className="flex flex-col gap-1.5 sm:gap-2">
                        <p className="font-data text-xs tracking-[0.14em] text-stone uppercase">
                            Alamat
                        </p>
                        <p className="text-base leading-[1.6]">
                            {venue.address}
                        </p>
                    </div>
                    <div className="flex flex-col gap-1.5 sm:gap-2">
                        <p className="font-data text-xs tracking-[0.14em] text-stone uppercase">
                            Waktu lokal
                        </p>
                        <p className="text-base leading-[1.6]">
                            {timezoneLabel(venue.timezone)}
                        </p>
                    </div>
                    {venue.contact_phone && (
                        <div className="flex flex-col gap-1.5 sm:gap-2">
                            <p className="font-data text-xs tracking-[0.14em] text-stone uppercase">
                                Telepon
                            </p>
                            <a
                                href={`tel:${venue.contact_phone}`}
                                className="text-base leading-[1.6] text-ink underline underline-offset-4 hover:underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cobalt"
                            >
                                {venue.contact_phone}
                            </a>
                        </div>
                    )}
                </div>

                {venue.spaces.length > 0 ? (
                    <>
                        <SectionHeading
                            className="mt-10"
                            eyebrow="Lapangan & ruang"
                            title={`Pilih lapangan atau ruang di ${venue.name}.`}
                        />
                        <ul className="mt-8 grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                            {venue.spaces.map((space) => (
                                <li key={space.id} className="min-w-0">
                                    {/* instant_book tidak ditampilkan di halaman venue; dummy agar tipe cocok SpaceCard */}
                                    <SpaceCard
                                        space={{
                                            ...space,
                                            instant_book: false,
                                            venue: {
                                                id: venue.id,
                                                slug: venue.slug,
                                                timezone: venue.timezone,
                                                cover_photo_path:
                                                    venue.cover_photo_path,
                                                name: venue.name,
                                                city: venue.city,
                                            },
                                        }}
                                    />
                                </li>
                            ))}
                        </ul>
                    </>
                ) : (
                    <div className="mt-10 max-w-[44ch] border-t border-ink/15 pt-10">
                        <h2 className="font-display text-2xl font-bold tracking-[-0.02em] text-ink [font-stretch:85%]">
                            Belum ada lapangan atau ruang yang tayang di{' '}
                            {venue.name}.
                        </h2>
                        <p className="mt-3 text-base leading-[1.6] text-stone">
                            Cek lagi nanti.
                        </p>
                    </div>
                )}

                {allAmenities.length > 0 && (
                    <section className="mt-10 border-t border-ink/15 pt-10">
                        <SectionHeading
                            eyebrow="Amenitas"
                            title="Fasilitas di venue ini."
                        />
                        <p className="mt-4 font-body text-sm text-stone">
                            {allAmenities.join(' · ')}
                        </p>
                    </section>
                )}
            </Container>
        </>
    );
}
