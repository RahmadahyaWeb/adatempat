import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Menu } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';

import { Container } from '@/components/public/container';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { dashboard, home, login } from '@/routes';
import { business as registerBusiness } from '@/routes/register';
import { index as spacesIndex } from '@/routes/spaces';

const navLinks = [
    { label: 'Jelajahi', href: spacesIndex.url() },
    // Anchor biasa agar tetap jalan dari halaman publik mana pun.
    { label: 'Venue', href: '/#venue' },
    { label: 'Cara Kerja', href: '/#cara-kerja' },
    { label: 'Untuk Pemilik', href: registerBusiness.url() },
];

function NavItem({
    href,
    className,
    onNavigate,
    children,
}: {
    href: string;
    className: string;
    onNavigate?: () => void;
    children: ReactNode;
}) {
    if (href.startsWith('/#')) {
        return (
            <a href={href} className={className} onClick={onNavigate}>
                {children}
            </a>
        );
    }

    return (
        <Link href={href} className={className} onClick={onNavigate}>
            {children}
        </Link>
    );
}

const desktopLinkClass =
    'inline-flex min-h-11 items-center rounded-sm px-2 text-[15px] text-ink underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt';

const menuLinkClass =
    'flex min-h-14 items-center justify-between border-b border-ink/15 font-display text-[28px] leading-none font-bold tracking-[-0.01em] text-ink hover:text-cobalt focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-cobalt';

export function SiteHeader() {
    const { auth } = usePage().props;
    const [menuOpen, setMenuOpen] = useState(false);
    const account = auth.user
        ? { label: 'Dashboard', href: dashboard() }
        : { label: 'Masuk', href: login() };

    return (
        <header className="sticky top-0 z-40 border-b border-sand bg-cream">
            <Container className="grid h-16 grid-cols-[1fr_auto_1fr] items-center md:h-18">
                <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
                    <SheetTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Buka menu"
                            className="col-start-1 row-start-1 -ml-2 size-11 justify-self-start hover:bg-sand-light focus-visible:ring-cobalt/60 md:hidden"
                        >
                            <Menu className="size-5" />
                        </Button>
                    </SheetTrigger>

                    <SheetContent
                        side="right"
                        className="w-full max-w-none gap-0 border-sand bg-cream p-0 text-ink motion-reduce:data-[state=closed]:animate-none motion-reduce:data-[state=open]:animate-none sm:max-w-sm [&>button]:top-2 [&>button]:right-2 [&>button]:flex [&>button]:size-11 [&>button]:items-center [&>button]:justify-center [&>button]:rounded-md [&>button]:opacity-100"
                    >
                        <SheetHeader className="h-16 justify-center border-b border-sand px-5 py-0">
                            <SheetTitle className="font-display text-[22px] font-extrabold tracking-[-0.02em]">
                                Menu
                            </SheetTitle>
                            <SheetDescription className="sr-only">
                                Navigasi utama AdaTempat
                            </SheetDescription>
                        </SheetHeader>

                        <nav
                            aria-label="Menu utama"
                            className="flex flex-col px-5 pt-2"
                        >
                            {navLinks.map((link) => (
                                <NavItem
                                    key={link.label}
                                    href={link.href}
                                    className={menuLinkClass}
                                    onNavigate={() => setMenuOpen(false)}
                                >
                                    {link.label}
                                    <ArrowRight
                                        aria-hidden
                                        className="size-5 text-cobalt"
                                    />
                                </NavItem>
                            ))}
                        </nav>

                        <div className="mt-auto border-t border-sand p-5">
                            <Button
                                asChild
                                variant="outline"
                                className="h-12 w-full rounded-md border-ink bg-transparent text-[15px] font-medium text-ink shadow-none hover:bg-ink hover:text-cream focus-visible:border-ink focus-visible:ring-cobalt/60"
                            >
                                <Link
                                    href={account.href}
                                    onClick={() => setMenuOpen(false)}
                                >
                                    {account.label}
                                </Link>
                            </Button>
                        </div>
                    </SheetContent>
                </Sheet>

                <Link
                    href={home()}
                    className="col-start-2 row-start-1 inline-flex min-h-11 items-center justify-self-center rounded-sm font-display text-[22px] leading-none font-extrabold tracking-[-0.02em] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt md:col-start-1 md:justify-self-start md:text-[26px]"
                >
                    AdaTempat<span className="text-cobalt">.</span>
                </Link>

                <nav
                    aria-label="Utama"
                    className="col-start-2 row-start-1 hidden md:block"
                >
                    <ul className="flex items-center gap-4">
                        {navLinks.map((link) => (
                            <li key={link.label}>
                                <NavItem
                                    href={link.href}
                                    className={desktopLinkClass}
                                >
                                    {link.label}
                                </NavItem>
                            </li>
                        ))}
                    </ul>
                </nav>

                <Button
                    asChild
                    variant="outline"
                    className="col-start-3 row-start-1 h-11 justify-self-end rounded-md border-ink bg-transparent px-4 text-[15px] font-medium text-ink shadow-none hover:bg-ink hover:text-cream focus-visible:border-ink focus-visible:ring-cobalt/60"
                >
                    <Link href={account.href}>{account.label}</Link>
                </Button>
            </Container>
        </header>
    );
}
