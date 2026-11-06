import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

import { CategoryChips } from '@/components/public/category-chips';
import { Container } from '@/components/public/container';
import { CoverImage } from '@/components/public/cover-image';
import { Eyebrow } from '@/components/public/eyebrow';
import { Hero } from '@/components/public/hero';
import { InfoBar } from '@/components/public/info-bar';
import { SectionHeading } from '@/components/public/section-heading';
import { VenueCard } from '@/components/public/venue-card';
import { Button } from '@/components/ui/button';
import { business as registerBusiness } from '@/routes/register';
import { index as spacesIndex } from '@/routes/spaces';
import type { CatalogVenue, HomePageProps } from '@/types/catalog';

const steps = [
    {
        title: 'Cari venue',
        body: 'Pilih kota dan kategori, lalu bandingkan harga per jam dari tiap venue.',
    },
    {
        title: 'Pilih jadwal',
        body: 'Lihat jam yang masih kosong di setiap space, lalu pilih slot yang kamu mau.',
    },
    {
        title: 'Bayar & main',
        body: 'Selesaikan pembayaran, lalu datang ke venue sesuai jadwal yang sudah kamu pesan.',
    },
];

function VenueSection({
    venues,
    categories,
}: {
    venues: CatalogVenue[];
    categories: HomePageProps['categories'];
}) {
    return (
        <section
            id="venue"
            aria-labelledby="venue-title"
            className="scroll-mt-20 pt-12 sm:pt-16 lg:pt-24"
        >
            <Container className="flex flex-col gap-8">
                <SectionHeading
                    id="venue-title"
                    eyebrow="Venue pilihan"
                    title="Tempat main, dekat dari kamu."
                    description="Venue yang sudah tayang di AdaTempat. Harga per jam dan jumlah space tampil di setiap kartu."
                    action={
                        <Link
                            href={spacesIndex.url()}
                            className="inline-flex min-h-11 items-center gap-2 text-[15px] font-medium whitespace-nowrap text-cobalt underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt"
                        >
                            Lihat semua venue
                            <ArrowRight className="size-4" />
                        </Link>
                    }
                />

                <CategoryChips categories={categories} />

                {venues.length > 0 ? (
                    <div className="grid gap-6 md:grid-cols-3">
                        {venues.map((venue) => (
                            <VenueCard key={venue.id} venue={venue} />
                        ))}
                    </div>
                ) : (
                    <div className="rounded-panel border border-brown bg-white p-8 text-center md:p-12">
                        <p className="font-display text-[26px] leading-tight font-bold">
                            Belum ada venue yang tayang.
                        </p>
                        <p className="mx-auto mt-3 max-w-md text-[16px] text-ink/80">
                            Venue yang sudah disetujui dan dipublikasikan akan
                            muncul di sini.
                        </p>
                        <Button
                            asChild
                            className="mt-6 h-12 rounded-md bg-cobalt px-6 text-[15px] font-medium text-white shadow-none hover:bg-cobalt/90"
                        >
                            <Link href={registerBusiness()}>
                                Daftar sebagai pemilik
                            </Link>
                        </Button>
                    </div>
                )}
            </Container>
        </section>
    );
}

function HowItWorksSection() {
    return (
        <section
            id="cara-kerja"
            aria-labelledby="cara-kerja-title"
            className="scroll-mt-20 pt-12 sm:pt-16 lg:pt-24"
        >
            <Container className="grid gap-12 lg:grid-cols-2 lg:items-center lg:gap-16">
                <div>
                    <Eyebrow>Cara kerja</Eyebrow>
                    <h2
                        id="cara-kerja-title"
                        className="mt-4 font-display text-[36px] leading-[1.02] font-bold tracking-[-0.02em] font-stretch-[87.5%] sm:text-[48px]"
                    >
                        Tiga langkah, langsung main.
                    </h2>

                    <ol className="mt-10 divide-y divide-sand border-y border-sand">
                        {steps.map((step, index) => (
                            <li
                                key={step.title}
                                className="grid grid-cols-[3rem_1fr] gap-4 py-6"
                            >
                                <span className="pt-1.5 font-data text-[13px] text-ink/70">
                                    {String(index + 1).padStart(2, '0')}
                                </span>
                                <div>
                                    <h3 className="font-display text-[22px] leading-tight font-bold">
                                        {step.title}
                                    </h3>
                                    <p className="mt-2 text-[16px] leading-normal text-ink/80">
                                        {step.body}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ol>
                </div>

                <figure className="rounded-panel border border-sand bg-white p-3">
                    <CoverImage
                        src="/images/venues/futsal.jpg"
                        alt="Tiga bola di atas rumput lapangan"
                        className="aspect-[4/3] rounded-card"
                    />
                    <figcaption className="mt-3 px-1 font-data text-[11px] tracking-[0.16em] text-ink/70 uppercase">
                        Foto ilustrasi
                    </figcaption>
                </figure>
            </Container>
        </section>
    );
}

function OwnerCtaSection() {
    return (
        <section
            aria-labelledby="owner-title"
            className="pt-12 pb-12 sm:pt-16 sm:pb-16 lg:pt-24 lg:pb-24"
        >
            <Container>
                <div className="grid overflow-hidden rounded-panel bg-cobalt text-white lg:grid-cols-2">
                    <div className="flex flex-col justify-center gap-6 p-8 md:p-12 lg:p-16">
                        <Eyebrow className="text-white">Untuk pemilik</Eyebrow>
                        <h2
                            id="owner-title"
                            className="font-display text-[36px] leading-[1.02] font-bold tracking-[-0.02em] font-stretch-[87.5%] sm:text-[48px]"
                        >
                            Venue kamu, lebih mudah ditemukan.
                        </h2>
                        <p className="max-w-[30rem] text-[17px] leading-normal text-white">
                            Daftarkan venue dan space kamu, atur harga per jam,
                            lalu biarkan pelanggan menemukan dan membooking
                            sendiri.
                        </p>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                            <Button
                                asChild
                                className="h-12 rounded-md bg-white px-6 text-[15px] font-medium text-cobalt shadow-none hover:bg-cream focus-visible:ring-white/80"
                            >
                                <Link href={registerBusiness()}>
                                    Daftar sebagai pemilik
                                </Link>
                            </Button>
                            <Button
                                asChild
                                variant="outline"
                                className="h-12 rounded-md border-white bg-transparent px-6 text-[15px] font-medium text-white shadow-none hover:bg-white/10 hover:text-white focus-visible:border-white focus-visible:ring-white/80"
                            >
                                <Link href={registerBusiness()}>Pelajari</Link>
                            </Button>
                        </div>
                    </div>

                    <CoverImage
                        src="/images/venues/meeting-2.jpg"
                        alt="Lorong kantor dengan dinding kaca dan lantai beton"
                        className="aspect-[4/3] lg:aspect-auto lg:min-h-80"
                    />
                </div>
            </Container>
        </section>
    );
}

export default function Home({ venues, cities, categories }: HomePageProps) {
    return (
        <>
            <Head title="Beranda" />
            <Hero cities={cities} categories={categories} />
            <InfoBar />
            <VenueSection venues={venues.slice(0, 3)} categories={categories} />
            <HowItWorksSection />
            <OwnerCtaSection />
        </>
    );
}
