import { Link } from '@inertiajs/react';
import { isRoomCategory, venueCoverSrc } from '@/lib/catalog';
import { show as venueShow } from '@/routes/venues';
import type { CatalogVenue } from '@/types/catalog';
import { Price } from './price';

export function VenueCard({ venue }: { venue: CatalogVenue }) {
    const firstSpace = venue.spaces[0];
    const categoryLabel = firstSpace?.category_label ?? 'Venue';
    const unit = isRoomCategory(firstSpace?.category) ? 'ruang' : 'lapangan';
    const hasPrice = venue.starting_price > 0;

    return (
        <article className="min-w-0">
            <Link
                href={venueShow(venue.slug)}
                className="group block focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cobalt"
            >
                <div className="relative aspect-[4/3] overflow-hidden bg-sand outline-1 outline-offset-[-1px] outline-black/10">
                    <img
                        src={venueCoverSrc(venue)}
                        alt=""
                        width={1200}
                        height={800}
                        loading="lazy"
                        decoding="async"
                        className="size-full object-cover object-[center_62%] motion-safe:transition-transform motion-safe:duration-500 motion-safe:ease-out motion-safe:group-hover:scale-[1.02]"
                    />
                    <span className="absolute top-3 left-3 bg-orange px-2 py-1 font-data text-[11px] font-medium tracking-[0.14em] text-ink uppercase">
                        {categoryLabel}
                    </span>
                </div>

                <div className="pt-4">
                    <h3 className="font-display text-xl font-bold tracking-[-0.02em] text-ink [font-stretch:85%] group-hover:underline group-hover:decoration-1 group-hover:underline-offset-4">
                        {venue.name}
                    </h3>
                    <p className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 font-data text-xs text-stone">
                        <span className="inline-flex items-center gap-1.5 font-medium text-ink">
                            <span
                                aria-hidden="true"
                                className="size-1.5 rounded-full bg-cobalt"
                            />
                            Buka hari ini
                        </span>
                        <span aria-hidden="true" className="text-ink/20">
                            ·
                        </span>
                        <span>{venue.city}</span>
                        <span aria-hidden="true" className="text-ink/20">
                            ·
                        </span>
                        <span>
                            {venue.spaces_count} {unit}
                        </span>
                    </p>
                    {hasPrice ? (
                        <p className="mt-3 flex flex-wrap items-baseline gap-x-1.5">
                            <span className="font-body text-xs text-stone">
                                Mulai
                            </span>
                            <Price
                                amount={venue.starting_price}
                                className="text-ink"
                            />
                            <span className="font-data text-xs text-stone">
                                / jam
                            </span>
                        </p>
                    ) : (
                        <p className="mt-3 font-body text-xs text-stone">
                            Belum ada {unit} aktif
                        </p>
                    )}
                </div>
            </Link>
        </article>
    );
}
