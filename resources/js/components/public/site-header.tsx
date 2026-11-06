import { Link } from '@inertiajs/react';
import { home, login } from '@/routes';
import { business as registerBusiness } from '@/routes/register';
import { Button } from './button';
import { Container } from './container';
import { MobileMenu } from './mobile-menu';
import { NAV_LINKS } from './nav';
import { Wordmark } from './wordmark';

export function SiteHeader() {
    return (
        <header className="sticky top-0 z-40 border-b border-ink/15 bg-cream">
            <Container className="flex h-16 items-center justify-between gap-6 lg:h-[76px]">
                <Link
                    href={home()}
                    className="inline-flex min-h-11 items-center text-ink focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cobalt"
                >
                    <Wordmark />
                </Link>

                <nav
                    aria-label="Navigasi utama"
                    className="ml-auto hidden items-center gap-7 lg:flex"
                >
                    {NAV_LINKS.map((item) =>
                        item.native ? (
                            <a
                                key={item.label}
                                href={item.href}
                                className="inline-flex min-h-11 items-center font-body text-sm text-ink underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cobalt"
                            >
                                {item.label}
                            </a>
                        ) : (
                            <Link
                                key={item.label}
                                href={item.href}
                                className="inline-flex min-h-11 items-center font-body text-sm text-ink underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cobalt"
                            >
                                {item.label}
                            </Link>
                        ),
                    )}
                </nav>

                <div className="hidden items-center gap-4 border-s border-ink/15 ps-6 lg:flex">
                    <Link
                        href={login()}
                        className="inline-flex min-h-11 items-center font-body text-sm text-ink underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cobalt"
                    >
                        Masuk
                    </Link>
                    <Button asChild className="min-h-11 px-4">
                        <Link href={registerBusiness()}>Daftarkan venue</Link>
                    </Button>
                </div>

                <div className="lg:hidden">
                    <MobileMenu />
                </div>
            </Container>
        </header>
    );
}
