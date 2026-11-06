import { Link } from '@inertiajs/react';

import { CoverImage } from '@/components/public/cover-image';
import { eyebrowClass } from '@/components/public/eyebrow';
import { Price } from '@/components/public/price';
import { Badge } from '@/components/ui/badge';
import { venueCategoryLabels, venueCoverSrc } from '@/lib/catalog';
import { show as venueShow } from '@/routes/venues';
import type { CatalogVenue } from '@/types/catalog';

export function VenueCard({ venue }: { venue: CatalogVenue }) {
    return (
        <Link
            href={venueShow(venue.slug)}
            className="group relative flex flex-col overflow-hidden rounded-card border border-sand bg-white transition-colors hover:border-cobalt focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt motion-reduce:transition-none"
        >
            <CoverImage
                src={venueCoverSrc(venue)}
                className="aspect-[16/10] md:aspect-[4/3]"
                imgClassName="transition-transform duration-700 ease-out group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100"
            />
            <Badge className="absolute top-3 left-3 border-transparent bg-orange font-data text-[11px] font-normal tracking-[0.12em] text-ink uppercase">
                Terverifikasi
            </Badge>

            <div className="flex flex-1 flex-col gap-3 p-4 lg:p-5">
                <p className={eyebrowClass}>
                    {[venue.city, venueCategoryLabels(venue)]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
                <h3 className="font-display text-[22px] leading-tight font-bold tracking-[-0.01em] lg:text-[24px]">
                    {venue.name}
                </h3>
                <div className="mt-auto flex flex-wrap items-end justify-between gap-x-4 gap-y-2 border-t border-sand pt-4">
                    {venue.spaces_count > 0 ? (
                        <p>
                            <span className="block text-[13px] text-ink/70">
                                Mulai
                            </span>
                            <span className="text-[16px] font-medium whitespace-nowrap">
                                <Price amount={venue.starting_price} />
                                <span className="text-[13px] text-ink/70">
                                    {' '}
                                    / jam
                                </span>
                            </span>
                        </p>
                    ) : (
                        <p className="text-[14px] text-ink/70">
                            Belum ada space aktif
                        </p>
                    )}
                    <span className={`${eyebrowClass} whitespace-nowrap`}>
                        {venue.spaces_count} space
                    </span>
                </div>
            </div>
        </Link>
    );
}
