import { Link } from '@inertiajs/react';
import { home, login } from '@/routes';
import { business as registerBusiness } from '@/routes/register';
import { index as spacesIndex } from '@/routes/spaces';
import { Container } from './container';
import { Wordmark } from './wordmark';

const exploreLinks = [
    { label: 'Cari tempat', href: spacesIndex().url },
    { label: 'Kategori ruang', href: `${home.url()}#kategori`, native: true },
    { label: 'Cara pemesanan', href: `${home.url()}#cara-kerja`, native: true },
];

const accountLinks = [
    { label: 'Masuk ke akun', href: login().url },
    { label: 'Daftarkan venue kamu', href: registerBusiness().url },
];

const linkClass =
    'inline-flex min-h-11 items-center font-body text-sm text-cream/80 underline-offset-4 transition-colors hover:text-cream hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-butter';

export function SiteFooter() {
    return (
        <footer className="bg-navy text-cream">
            <Container className="grid gap-12 py-14 md:py-20 lg:grid-cols-12 lg:gap-16">
                <div className="lg:col-span-6">
                    <Link
                        href={home()}
                        className="inline-flex min-h-11 items-center text-cream focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-butter"
                    >
                        <Wordmark />
                    </Link>
                    <p className="mt-4 max-w-[38ch] font-body text-sm leading-[1.6] text-cream/70">
                        Sewa tempat olahraga, ruang kerja, dan venue
                        terverifikasi di Indonesia.
                    </p>
                </div>

                <div className="grid grid-cols-2 gap-8 lg:col-span-6">
                    <nav aria-label="Jelajahi">
                        <p className="font-data text-xs tracking-[0.18em] text-cream/60 uppercase">
                            Jelajahi
                        </p>
                        <ul className="mt-3 flex flex-col">
                            {exploreLinks.map((item) => (
                                <li key={item.label}>
                                    {item.native ? (
                                        <a
                                            href={item.href}
                                            className={linkClass}
                                        >
                                            {item.label}
                                        </a>
                                    ) : (
                                        <Link
                                            href={item.href}
                                            className={linkClass}
                                        >
                                            {item.label}
                                        </Link>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </nav>

                    <nav aria-label="Mitra pemilik tempat">
                        <p className="font-data text-xs tracking-[0.18em] text-cream/60 uppercase">
                            Mitra Pemilik
                        </p>
                        <ul className="mt-3 flex flex-col">
                            {accountLinks.map((item) => (
                                <li key={item.label}>
                                    <Link
                                        href={item.href}
                                        className={linkClass}
                                    >
                                        {item.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </nav>
                </div>
            </Container>

            <Container>
                <div className="flex flex-col gap-2 border-t border-cream/15 py-6 font-data text-xs text-cream/60 sm:flex-row sm:items-center sm:justify-between">
                    <p>
                        © {new Date().getFullYear()} AdaTempat · Platform
                        Pemesanan Tempat &amp; Ruang Kegiatan
                    </p>
                    <p>Semua harga transparan dalam Rupiah.</p>
                </div>
            </Container>
        </footer>
    );
}
