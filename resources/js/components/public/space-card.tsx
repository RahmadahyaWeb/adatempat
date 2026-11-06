import { Link } from '@inertiajs/react';
import { categoryImage } from '@/lib/catalog';
import { cn } from '@/lib/utils';
import { show as spaceShow } from '@/routes/spaces';
import { show as venueShow } from '@/routes/venues';
import type { SpaceListItem, VenueReference } from '@/types/catalog';
import { Price } from './price';

type SpaceCardData = Pick<
    SpaceListItem,
    | 'id'
    | 'name'
    | 'slug'
    | 'category'
    | 'category_label'
    | 'capacity'
    | 'base_price'
> & {
    instant_book?: boolean;
    venue: Pick<VenueReference, 'name' | 'city'> & { slug?: string };
};

export function SpaceCard({
    space,
    showVenue = true,
}: {
    space: SpaceCardData;
    showVenue?: boolean;
}) {
    const meta = [
        showVenue ? space.venue.city : null,
        space.capacity !== null && space.capacity !== undefined
            ? `Kapasitas ${space.capacity} orang`
            : null,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <article className="group relative min-w-0">
            <div className="relative aspect-[16/10] overflow-hidden bg-sand outline-1 outline-offset-[-1px] outline-black/10">
                <Link
                    href={spaceShow(space.slug)}
                    aria-label={`Lihat detail ${space.name}`}
                    className="block size-full focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cobalt"
                    tabIndex={-1}
                >
                    <img
                        src={categoryImage(space.category)}
                        alt=""
                        width={1200}
                        height={750}
                        loading="lazy"
                        decoding="async"
                        className={cn(
                            'size-full object-cover motion-safe:transition-transform motion-safe:duration-500 motion-safe:ease-out motion-safe:group-hover:scale-[1.02]',
                            space.category === 'futsal'
                                ? 'object-[80%_15%]'
                                : 'object-[center_62%]',
                        )}
                    />
                </Link>
                <span className="pointer-events-none absolute top-3 left-3 bg-orange px-2 py-0.5 font-data text-[11px] font-semibold tracking-[0.14em] text-ink uppercase">
                    {space.category_label}
                </span>
                {space.instant_book && (
                    <span className="pointer-events-none absolute top-3 right-3 border border-ink/10 bg-cream/95 px-2 py-0.5 font-data text-[10px] tracking-wider text-ink uppercase backdrop-blur-xs">
                        Tiket Instan
                    </span>
                )}
            </div>

            <div className="pt-3.5">
                <h3 className="font-display text-lg font-bold tracking-[-0.02em] text-ink [font-stretch:85%]">
                    <Link
                        href={spaceShow(space.slug)}
                        className="hover:underline hover:decoration-1 hover:underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt"
                    >
                        {space.name}
                    </Link>
                </h3>

                {showVenue && (
                    <p className="mt-1 font-body text-sm text-stone">
                        {space.venue.slug ? (
                            <Link
                                href={venueShow(space.venue.slug)}
                                className="underline-offset-4 hover:text-ink hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt"
                            >
                                {space.venue.name}
                            </Link>
                        ) : (
                            space.venue.name
                        )}
                    </p>
                )}

                {meta && (
                    <p className="mt-1 font-data text-xs text-stone">{meta}</p>
                )}

                <div className="mt-3 flex items-baseline justify-between border-t border-ink/10 pt-2.5">
                    <p className="flex items-baseline gap-1.5">
                        <Price
                            amount={space.base_price}
                            className="font-semibold text-ink"
                        />
                        <span className="font-data text-xs text-stone">
                            / jam
                        </span>
                    </p>
                    <Link
                        href={spaceShow(space.slug)}
                        tabIndex={-1}
                        aria-hidden="true"
                        className="font-data text-xs font-medium text-stone transition-colors group-hover:text-cobalt"
                    >
                        Pilih slot →
                    </Link>
                </div>
            </div>
        </article>
    );
}
