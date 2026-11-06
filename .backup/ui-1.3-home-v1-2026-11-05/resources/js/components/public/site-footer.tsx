import { Link } from '@inertiajs/react';

import { Container } from '@/components/public/container';
import { eyebrowClass } from '@/components/public/eyebrow';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import { business as registerBusiness } from '@/routes/register';
import { index as spacesIndex } from '@/routes/spaces';

const linkClass =
    'inline-flex min-h-11 items-center underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sand';

export function SiteFooter() {
    return (
        <footer className="bg-navy text-cream">
            <Container className="grid gap-12 py-14 md:grid-cols-[1.4fr_1fr_1fr] md:py-20">
                <div className="max-w-sm">
                    <p className="font-display text-[32px] leading-none font-extrabold tracking-[-0.02em]">
                        AdaTempat<span className="text-sand">.</span>
                    </p>
                    <p className="mt-5 text-[15px] leading-normal text-cream/80">
                        Booking venue olahraga dan ruang meeting dalam satu
                        tempat.
                    </p>
                </div>

                <div>
                    <h2 className={cn(eyebrowClass, 'text-cream/70')}>
                        Jelajahi
                    </h2>
                    <ul className="mt-4 flex flex-col text-[15px]">
                        <li>
                            <Link href={home()} className={linkClass}>
                                Beranda
                            </Link>
                        </li>
                        <li>
                            <Link
                                href={spacesIndex.url()}
                                className={linkClass}
                            >
                                Semua venue
                            </Link>
                        </li>
                        <li>
                            <Link
                                href={registerBusiness()}
                                className={linkClass}
                            >
                                Untuk pemilik
                            </Link>
                        </li>
                    </ul>
                </div>

                <div>
                    <h2 className={cn(eyebrowClass, 'text-cream/70')}>
                        Kontak
                    </h2>
                    <ul className="mt-4 flex flex-col text-[15px]">
                        <li>
                            <a
                                href="mailto:halo@adatempat.id"
                                className={linkClass}
                            >
                                halo@adatempat.id
                            </a>
                        </li>
                        <li className="inline-flex min-h-11 items-center">
                            Indonesia
                        </li>
                    </ul>
                </div>
            </Container>

            <div className="border-t border-cream/20">
                <Container className="py-6">
                    <p className="font-data text-[12px] text-cream/70">
                        © 2026 AdaTempat
                    </p>
                </Container>
            </div>
        </footer>
    );
}
