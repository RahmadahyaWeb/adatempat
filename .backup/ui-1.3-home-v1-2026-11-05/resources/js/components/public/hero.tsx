import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

import { Container } from '@/components/public/container';
import { CoverImage } from '@/components/public/cover-image';
import { Eyebrow } from '@/components/public/eyebrow';
import { HeroSearch } from '@/components/public/hero-search';
import { Button } from '@/components/ui/button';
import { business as registerBusiness } from '@/routes/register';
import { index as spacesIndex } from '@/routes/spaces';
import type { CategoryOption } from '@/types/catalog';

export function Hero({
    cities,
    categories,
}: {
    cities: string[];
    categories: CategoryOption[];
}) {
    return (
        <Container className="grid items-start gap-12 pt-10 pb-12 md:pt-14 lg:grid-cols-[55fr_45fr] lg:gap-12 lg:pt-20 lg:pb-16">
            <div className="flex flex-col">
                <Eyebrow>MARKETPLACE VENUE OLAHRAGA · INDONESIA</Eyebrow>
                <h1 className="mt-6 font-display text-[40px] leading-[0.98] font-extrabold tracking-[-0.02em] font-stretch-[87.5%] sm:text-[56px] lg:text-[68px] xl:text-[72px]">
                    <span className="block">Temukan venue.</span>
                    <span className="block">Mainkan waktunya.</span>
                </h1>
                <p className="mt-6 max-w-[28rem] text-[16px] leading-normal text-ink/80 md:text-[18px]">
                    Futsal, badminton, basket, sampai ruang meeting. Bandingkan
                    venue, lihat harga per jam, dan booking tanpa ribet.
                </p>

                <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                    <Button
                        asChild
                        className="h-12 rounded-md bg-cobalt px-6 text-[15px] font-medium text-white shadow-none hover:bg-cobalt/90 focus-visible:ring-cobalt/60"
                    >
                        <Link href={spacesIndex.url()}>Cari venue</Link>
                    </Button>
                    <Button
                        asChild
                        variant="outline"
                        className="h-12 rounded-md border-ink bg-transparent px-6 text-[15px] font-medium text-ink shadow-none hover:bg-ink hover:text-cream focus-visible:border-ink focus-visible:ring-cobalt/60"
                    >
                        <Link href={registerBusiness()}>
                            Untuk pemilik venue
                            <ArrowRight />
                        </Link>
                    </Button>
                </div>

                <HeroSearch
                    cities={cities}
                    categories={categories}
                    className="mt-10"
                />
            </div>

            <CoverImage
                src="/images/venues/badminton.jpg"
                alt="Pemain badminton melompat di dalam lapangan indoor"
                loading="eager"
                className="aspect-[4/3] rounded-panel motion-safe:animate-in motion-safe:duration-700 motion-safe:fade-in md:aspect-[16/10] lg:aspect-[4/5]"
                imgClassName="[object-position:70%_50%]"
            />
        </Container>
    );
}
