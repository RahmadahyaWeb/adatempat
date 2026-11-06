import { Head } from '@inertiajs/react';
import { Button } from '@/components/public/button';
import { Container } from '@/components/public/container';
import { Pagination } from '@/components/public/pagination';
import { SectionHeading } from '@/components/public/section-heading';
import { SpaceCard } from '@/components/public/space-card';
import {
    clearSpaceFilters,
    countActiveFilters,
} from '@/components/public/space-filters';
import { SpaceToolbar } from '@/components/public/space-toolbar';
import type { SpacesIndexPageProps } from '@/types/catalog';

export default function SpacesIndex({
    spaces,
    filters,
    cities,
    categories,
    priceRange,
    sort,
}: SpacesIndexPageProps) {
    const hasFilters = countActiveFilters(filters) > 0;

    return (
        <>
            <Head title="Katalog Tempat & Ruang · AdaTempat">
                <meta
                    name="description"
                    content="Pilih lapangan olahraga, studio, ruang meeting, atau ruang acara. Tarif per jam transparan dan tiket instan dari venue terverifikasi."
                />
            </Head>

            <section className="pt-8 md:pt-12 lg:pt-14">
                <Container>
                    <div className="border-b border-ink/15 pb-8">
                        <SectionHeading
                            headingLevel="h1"
                            eyebrow="Katalog Tempat &amp; Ruang"
                            title="Cari tempat apa hari ini?"
                            description="Pilih lapangan olahraga, studio, ruang meeting, atau ruang acara. Jadwal kosong dan harga transparan langsung kelihatan."
                        />
                    </div>
                </Container>
            </section>

            <section className="pt-8 pb-20 md:pb-28">
                <Container>
                    <SpaceToolbar
                        filters={filters}
                        cities={cities}
                        categories={categories}
                        priceRange={priceRange}
                        sort={sort}
                        total={spaces.total}
                    />

                    {spaces.data.length > 0 ? (
                        <>
                            <h2 className="sr-only">
                                Daftar tempat dan ruang yang tersedia
                            </h2>
                            <ul className="mt-8 grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                                {spaces.data.map((space) => (
                                    <li key={space.id} className="min-w-0">
                                        <SpaceCard space={space} />
                                    </li>
                                ))}
                            </ul>
                        </>
                    ) : (
                        <div className="mt-12 max-w-[48ch] border border-ink/15 bg-sand/30 p-8">
                            <p className="font-data text-xs tracking-widest text-stone uppercase">
                                PENCARIAN
                            </p>
                            <h2 className="mt-2 font-display text-2xl font-bold tracking-[-0.02em] text-ink [font-stretch:85%]">
                                {hasFilters
                                    ? 'Belum ada tempat yang cocok nih.'
                                    : 'Belum ada tempat yang tayang.'}
                            </h2>
                            <p className="mt-3 font-body text-sm leading-[1.6] text-stone">
                                {hasFilters
                                    ? 'Coba ganti kata kunci pencarian, pilih kota lain, atau reset filter harga kamu ya.'
                                    : 'Katalog sedang diperbarui. Silakan kembali lagi beberapa saat lagi.'}
                            </p>
                            {hasFilters && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="mt-6"
                                    onClick={() => clearSpaceFilters(sort)}
                                >
                                    Reset semua filter
                                </Button>
                            )}
                        </div>
                    )}

                    <Pagination
                        links={spaces.links}
                        className="mt-16 justify-center"
                    />
                </Container>
            </section>
        </>
    );
}
