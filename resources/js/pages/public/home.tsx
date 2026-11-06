import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Search } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/public/button';
import { Chip } from '@/components/public/chip';
import { Container } from '@/components/public/container';
import { SectionHeading } from '@/components/public/section-heading';
import { VenueCard } from '@/components/public/venue-card';
import { login } from '@/routes';
import { business as registerBusiness } from '@/routes/register';
import { index as spacesIndex } from '@/routes/spaces';
import type { HomePageProps } from '@/types/catalog';

const cityList = new Intl.ListFormat('id-ID', {
    style: 'long',
    type: 'conjunction',
});

const steps = [
    {
        title: 'Pilih tempat dan jam sewa',
        body: 'Cari lapangan olahraga, studio, atau ruang meeting di kotamu. Jam yang kosong langsung kelihatan secara real-time.',
    },
    {
        title: 'Harga transparan di awal',
        body: 'Tarif sewa per jam dan fasilitas tambahan langsung dihitung di depan tanpa ada biaya tersembunyi.',
    },
    {
        title: 'Tunjukkan tiket pas tiba',
        body: 'Begitu pembayaran beres, tiket QR langsung aktif di HP kamu untuk ditunjukkan ke staf venue saat tiba.',
    },
];

export default function Home({ venues, cities, categories }: HomePageProps) {
    const featured = venues.slice(0, 3);
    const [selectedCity, setSelectedCity] = useState('');
    const [selectedCategory, setSelectedCategory] = useState('');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        const query: Record<string, string> = {};
        if (selectedCity) query.city = selectedCity;
        if (selectedCategory) query.category = selectedCategory;
        router.get(spacesIndex().url, query);
    };

    return (
        <>
            <Head title="Cari Tempat Olahraga, Ruang Kerja, & Acara · AdaTempat">
                <meta
                    name="description"
                    content="Cari lapangan olahraga, studio, ruang meeting, dan coworking space. Cek jadwal kosong real-time dan booking instan tanpa ribet chat admin."
                />
            </Head>

            <section className="py-10 md:py-16 lg:py-20">
                <Container className="grid items-stretch gap-10 lg:grid-cols-2 lg:gap-16">
                    <div className="flex min-w-0 flex-col justify-between">
                        <div>
                            <p className="flex items-center gap-2 font-data text-xs tracking-[0.18em] text-stone uppercase">
                                <span
                                    aria-hidden="true"
                                    className="size-1.5 shrink-0 bg-orange"
                                />
                                Booking tempat olahraga, kerja, &amp; kumpul
                                komunitas
                            </p>

                            <h1 className="mt-5 font-display text-display font-extrabold text-ink [font-stretch:80%]">
                                <span className="block">
                                    Mau olahraga, kerja, atau bikin acara?
                                </span>
                                <span className="block text-stone">
                                    Booking tempatnya langsung tanpa ribet chat
                                    admin.
                                </span>
                            </h1>

                            <p className="mt-6 max-w-[50ch] font-body text-base leading-[1.65] text-pretty text-stone sm:text-lg">
                                Cek jadwal kosong secara real-time, bayar instan
                                pakai QRIS, dan simpan tiket digital di HP kamu
                                untuk langsung ditunjukkan di lokasi.
                            </p>

                            {/* Quick Finder Bar — Opsi A */}
                            <form
                                onSubmit={handleSearch}
                                className="mt-8 rounded-control border border-ink/20 bg-sand/50 p-3 sm:p-4"
                            >
                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto]">
                                    <div>
                                        <label
                                            htmlFor="hero-city"
                                            className="block font-data text-[11px] font-medium tracking-[0.14em] text-stone uppercase"
                                        >
                                            Pilih Kota
                                        </label>
                                        <select
                                            id="hero-city"
                                            value={selectedCity}
                                            onChange={(e) =>
                                                setSelectedCity(e.target.value)
                                            }
                                            className="mt-1.5 w-full rounded-control border border-ink/20 bg-cream px-3 py-2.5 font-body text-sm text-ink focus:outline-2 focus:outline-offset-1 focus:outline-cobalt"
                                        >
                                            <option value="">Semua kota</option>
                                            {cities.map((city) => (
                                                <option key={city} value={city}>
                                                    {city}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="hero-category"
                                            className="block font-data text-[11px] font-medium tracking-[0.14em] text-stone uppercase"
                                        >
                                            Kategori
                                        </label>
                                        <select
                                            id="hero-category"
                                            value={selectedCategory}
                                            onChange={(e) =>
                                                setSelectedCategory(
                                                    e.target.value,
                                                )
                                            }
                                            className="mt-1.5 w-full rounded-control border border-ink/20 bg-cream px-3 py-2.5 font-body text-sm text-ink focus:outline-2 focus:outline-offset-1 focus:outline-cobalt"
                                        >
                                            <option value="">
                                                Semua kategori
                                            </option>
                                            {categories.map((cat) => (
                                                <option
                                                    key={cat.value}
                                                    value={cat.value}
                                                >
                                                    {cat.label} ({cat.count})
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="flex items-end sm:col-span-2 lg:col-span-1">
                                        <Button
                                            type="submit"
                                            className="w-full sm:min-h-11 sm:px-6"
                                        >
                                            <Search
                                                aria-hidden="true"
                                                strokeWidth={1.75}
                                                className="size-4"
                                            />
                                            <span>
                                                Cari Tempat &amp; Ruangan
                                            </span>
                                        </Button>
                                    </div>
                                </div>
                            </form>

                            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 font-data text-xs text-stone">
                                <a
                                    href="#cara-kerja"
                                    className="group inline-flex min-h-11 items-center gap-1.5 font-body text-sm font-medium text-ink underline decoration-1 underline-offset-4 hover:decoration-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt"
                                >
                                    Lihat alur pemesanan
                                    <ArrowRight
                                        aria-hidden="true"
                                        strokeWidth={1.5}
                                        className="size-4 motion-safe:transition-transform motion-safe:duration-150 motion-safe:ease-out motion-safe:group-hover:translate-x-0.5"
                                    />
                                </a>
                                <span>Tiket instan tanpa chat admin</span>
                            </div>
                        </div>

                        <div className="mt-10 border-t border-ink/15 pt-5 lg:mt-12">
                            <dl className="grid grid-cols-3 gap-3 font-data text-xs sm:gap-4">
                                <div>
                                    <dt className="text-[11px] tracking-[0.14em] text-stone uppercase">
                                        Verifikasi
                                    </dt>
                                    <dd className="mt-0.5 font-medium text-ink">
                                        Venue resmi
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-[11px] tracking-[0.14em] text-stone uppercase">
                                        Tarif
                                    </dt>
                                    <dd className="mt-0.5 font-medium text-ink">
                                        Transparan / jam
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-[11px] tracking-[0.14em] text-stone uppercase">
                                        Akses
                                    </dt>
                                    <dd className="mt-0.5 font-medium text-ink">
                                        QR instan di HP
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div className="relative aspect-[4/3] overflow-hidden bg-sand outline-1 outline-offset-[-1px] outline-black/10 sm:aspect-[16/10] lg:aspect-auto lg:h-full lg:min-h-[560px]">
                        <img
                            src="/images/venues/futsal.jpg"
                            alt="Tiga bola futsal di atas lapangan rumput sintetis"
                            width={1200}
                            height={800}
                            fetchPriority="high"
                            decoding="async"
                            className="absolute inset-0 size-full object-cover object-[center_45%]"
                        />
                    </div>
                </Container>
            </section>

            <section
                id="kategori"
                aria-labelledby="venue-heading"
                className="scroll-mt-24 border-t border-ink/15 py-16 md:py-24"
            >
                <Container>
                    <SectionHeading
                        eyebrow="Katalog Tempat"
                        title="Pilihan tempat favorit."
                        titleId="venue-heading"
                        description="Semua venue sudah terverifikasi resmi. Cek tarif per jam terbaik dan amankan jadwal kegiatan kamu."
                    />

                    <div className="mt-10 flex flex-col gap-4 border-t border-ink/15 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex flex-wrap items-center gap-2">
                            <Chip href={spacesIndex()} active>
                                Semua
                            </Chip>
                            {categories.map((category) => (
                                <Chip
                                    key={category.value}
                                    href={spacesIndex({
                                        query: { category: category.value },
                                    })}
                                >
                                    {category.label}
                                </Chip>
                            ))}
                        </div>

                        <div className="flex items-center gap-4 font-data text-xs text-stone">
                            <span>{venues.length} venue pilihan</span>
                            <span aria-hidden="true" className="text-ink/20">
                                ·
                            </span>
                            <Link
                                href={spacesIndex()}
                                className="group inline-flex min-h-11 items-center gap-1.5 font-body text-sm font-medium text-ink underline decoration-1 underline-offset-4 hover:decoration-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt"
                            >
                                Lihat semua
                                <ArrowRight
                                    aria-hidden="true"
                                    strokeWidth={1.5}
                                    className="size-4 motion-safe:transition-transform motion-safe:duration-150 motion-safe:ease-out motion-safe:group-hover:translate-x-0.5 rtl:rotate-180"
                                />
                            </Link>
                        </div>
                    </div>

                    {cities.length > 0 && (
                        <p className="mt-3 font-data text-xs text-stone">
                            Venue tersedia di {cityList.format(cities)}.
                        </p>
                    )}

                    {featured.length > 0 ? (
                        <ul className="mt-8 grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                            {featured.map((venue) => (
                                <li key={venue.id} className="min-w-0">
                                    <VenueCard venue={venue} />
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="mt-8 max-w-[44ch] font-body text-base leading-[1.6] text-stone">
                            Belum ada venue terverifikasi. Cek lagi nanti.
                        </p>
                    )}
                </Container>
            </section>

            <section
                id="cara-kerja"
                aria-labelledby="how-heading"
                className="scroll-mt-24 border-y border-ink/15 bg-sand py-16 md:py-24"
            >
                <Container>
                    <SectionHeading
                        eyebrow="Cara Booking"
                        title="Tiga langkah gampang, tanpa tunggu balasan."
                        titleId="how-heading"
                        description="Cek jadwal kosong dan booking kapan pun kamu mau, bahkan di tengah malam."
                    />

                    <div className="mt-12 grid gap-10 lg:grid-cols-12 lg:gap-16">
                        <figure className="lg:col-span-5">
                            <div className="relative aspect-[4/3] overflow-hidden bg-cream outline-1 outline-offset-[-1px] outline-black/10 lg:aspect-[4/5]">
                                <img
                                    src="/images/venues/meeting-2.jpg"
                                    alt="Koridor dengan ruang meeting berpintu kaca"
                                    width={1200}
                                    height={801}
                                    loading="lazy"
                                    decoding="async"
                                    className="absolute inset-0 size-full object-cover"
                                />
                            </div>
                            <figcaption className="mt-3 font-data text-xs text-stone">
                                Foto: Nusantara Meeting Space · Surabaya
                            </figcaption>
                        </figure>

                        <ol className="min-w-0 border-b border-ink/20 lg:col-span-7">
                            {steps.map((step, index) => (
                                <li
                                    key={step.title}
                                    className="grid grid-cols-[2.5rem_minmax(0,1fr)] gap-4 border-t border-ink/20 py-6 md:grid-cols-[3rem_minmax(0,1fr)] md:gap-5 md:py-8"
                                >
                                    <span className="pt-1 font-data text-xs font-semibold tracking-wider text-stone tabular-nums">
                                        {String(index + 1).padStart(2, '0')}
                                    </span>
                                    <div>
                                        <h3 className="font-display text-xl font-bold tracking-[-0.02em] text-ink [font-stretch:85%] md:text-2xl">
                                            {step.title}
                                        </h3>
                                        <p className="mt-2 max-w-[46ch] font-body text-sm leading-[1.6] text-pretty text-stone">
                                            {step.body}
                                        </p>

                                        {/* Mockup Tiket Digital pada Langkah 03 */}
                                        {index === 2 && (
                                            <div className="mt-5 max-w-sm rounded-control border border-ink/20 bg-cream p-4">
                                                <div className="flex items-center justify-between border-b border-ink/15 pb-2.5">
                                                    <span className="font-data text-xs font-bold tracking-wider text-ink uppercase">
                                                        AdaTempat Pass
                                                    </span>
                                                    <span className="rounded-control bg-cobalt px-2 py-0.5 font-data text-[10px] font-medium text-cream uppercase">
                                                        Lunas · Siap Pakai
                                                    </span>
                                                </div>

                                                <div className="mt-3 flex items-center justify-between gap-4">
                                                    <div>
                                                        <p className="font-display text-base font-bold text-ink">
                                                            Borneo Futsal Arena
                                                        </p>
                                                        <p className="font-data text-xs text-stone">
                                                            Lapangan A ·
                                                            Sintetis
                                                        </p>
                                                        <p className="mt-1.5 font-data text-[11px] font-medium text-ink">
                                                            Hari ini, 19:00 -
                                                            21:00 WITA
                                                        </p>
                                                    </div>

                                                    <div className="size-14 shrink-0 bg-cream p-1 outline-1 outline-ink/20">
                                                        <svg
                                                            viewBox="0 0 24 24"
                                                            className="size-full fill-current text-ink"
                                                            aria-hidden="true"
                                                        >
                                                            <path d="M2 2h7v7H2V2zm2 2v3h3V4H4zm11-2h7v7h-7V2zm2 2v3h3V4h-3zM2 15h7v7H2v-7zm2 2v3h3v-3H4zm8-13h2v2h-2V4zm0 4h2v2h-2V8zm-2 2h2v2h-2v-2zm4 0h2v2h-2v-2zm2 2h2v2h-2v-2zm-6 2h2v2h-2v-2zm4 0h2v2h-2v-2zm-2 2h2v2h-2v-2zm4 2h2v2h-2v-2zm-4 2h2v2h-2v-2zm2 2h2v2h-2v-2zm2-4h2v2h-2v-2zm0 4h2v2h-2v-2z" />
                                                        </svg>
                                                    </div>
                                                </div>

                                                <div className="mt-3 border-t border-ink/10 pt-2 font-data text-[10px] text-stone">
                                                    Tiket #ADT-8492 · Tunjukkan
                                                    di lokasi
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </div>
                </Container>
            </section>

            <section
                aria-labelledby="owner-heading"
                className="bg-cobalt text-cream"
            >
                <Container className="grid gap-10 py-16 md:py-20 lg:grid-cols-12 lg:items-end lg:gap-16">
                    <div className="lg:col-span-7">
                        <p className="flex items-center gap-2 font-data text-xs tracking-[0.18em] text-cream uppercase">
                            <span
                                aria-hidden="true"
                                className="size-1.5 shrink-0 bg-butter"
                            />
                            Punya tempat olahraga, studio, atau ruang acara?
                        </p>
                        <h2
                            id="owner-heading"
                            className="mt-5 max-w-[18ch] font-display text-heading font-extrabold text-balance [font-stretch:82%]"
                        >
                            Bikin jadwal tempatmu selalu terisi penuh.
                        </h2>
                    </div>

                    <div className="lg:col-span-5">
                        <p className="max-w-[46ch] font-body text-base leading-[1.65] text-cream">
                            Daftarkan venue kamu, atur jam buka mandiri, dan
                            terima pembayaran booking otomatis langsung masuk ke
                            rekening tanpa biaya langganan bulanan.
                        </p>

                        <div className="mt-6 border-t border-cream/20 pt-6">
                            <dl className="grid grid-cols-2 gap-4 font-data text-xs text-cream/90">
                                <div>
                                    <dt className="text-[11px] tracking-[0.14em] text-cream/90 uppercase">
                                        Komisi Pendaftaran
                                    </dt>
                                    <dd className="mt-0.5 font-medium text-cream">
                                        0% (Gratis daftar)
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-[11px] tracking-[0.14em] text-cream/90 uppercase">
                                        Pencairan Dana
                                    </dt>
                                    <dd className="mt-0.5 font-medium text-cream">
                                        Otomatis ke rekening
                                    </dd>
                                </div>
                            </dl>

                            <div className="mt-8 flex flex-col gap-4 sm:flex-row sm:items-center">
                                <Button
                                    asChild
                                    variant="light"
                                    className="w-full focus-visible:outline-butter sm:w-auto"
                                >
                                    <Link href={registerBusiness()}>
                                        Daftarkan Venue Kamu
                                    </Link>
                                </Button>
                                <p className="font-body text-sm text-cream">
                                    Sudah punya akun?{' '}
                                    <Link
                                        href={login()}
                                        className="inline-flex min-h-11 items-center underline decoration-1 underline-offset-4 hover:decoration-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-butter"
                                    >
                                        Masuk
                                    </Link>
                                </p>
                            </div>
                        </div>
                    </div>
                </Container>
            </section>
        </>
    );
}
