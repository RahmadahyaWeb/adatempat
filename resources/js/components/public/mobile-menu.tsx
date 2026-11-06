import * as Dialog from '@radix-ui/react-dialog';
import { Link } from '@inertiajs/react';
import { ChevronRight, Menu, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from './button';
import { ACCOUNT_LINKS, NAV_LINKS, type NavItem } from './nav';
import { Wordmark } from './wordmark';

function MenuLink({
    item,
    onNavigate,
}: {
    item: NavItem;
    onNavigate: () => void;
}) {
    const className =
        'flex min-h-14 items-center justify-between gap-4 border-b border-ink/15 font-display text-2xl font-bold tracking-[-0.02em] text-ink transition-colors hover:text-cobalt focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cobalt [font-stretch:85%]';
    const content = (
        <>
            {item.label}
            <ChevronRight
                aria-hidden="true"
                strokeWidth={1.5}
                className="size-5 rtl:rotate-180"
            />
        </>
    );

    if (item.native) {
        return (
            <a href={item.href} onClick={onNavigate} className={className}>
                {content}
            </a>
        );
    }

    return (
        <Link href={item.href} onClick={onNavigate} className={className}>
            {content}
        </Link>
    );
}

export function MobileMenu() {
    const [open, setOpen] = useState(false);
    const close = () => setOpen(false);

    return (
        <Dialog.Root open={open} onOpenChange={setOpen}>
            <Dialog.Trigger asChild>
                <button
                    type="button"
                    className="inline-flex size-11 items-center justify-center rounded-control border border-ink text-ink transition-colors hover:bg-ink hover:text-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt [&_svg]:pointer-events-none"
                >
                    <Menu
                        aria-hidden="true"
                        strokeWidth={1.5}
                        className="size-5"
                    />
                    <span className="sr-only">Buka menu</span>
                </button>
            </Dialog.Trigger>

            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-50 bg-ink/40 transition-opacity duration-200 motion-reduce:transition-none starting:opacity-0" />
                <Dialog.Content
                    style={{ colorScheme: 'light' }}
                    className="fixed inset-y-0 end-0 z-50 flex h-dvh w-[88vw] max-w-sm flex-col overflow-y-auto overscroll-contain border-s border-ink/15 bg-cream p-6 text-ink shadow-none transition-transform duration-300 ease-out focus-visible:outline-none motion-reduce:transition-none starting:translate-x-full rtl:starting:-translate-x-full"
                >
                    <Dialog.Title className="sr-only">Menu</Dialog.Title>
                    <Dialog.Description className="sr-only">
                        Navigasi utama dan tautan akun.
                    </Dialog.Description>

                    <div className="flex items-center justify-between">
                        <Wordmark />
                        <Dialog.Close
                            type="button"
                            className="inline-flex size-11 items-center justify-center rounded-control border border-ink/20 text-ink transition-colors hover:bg-ink hover:text-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt"
                        >
                            <X
                                aria-hidden="true"
                                strokeWidth={1.5}
                                className="size-5"
                            />
                            <span className="sr-only">Tutup menu</span>
                        </Dialog.Close>
                    </div>

                    <nav aria-label="Navigasi utama" className="mt-10">
                        <div className="flex flex-col border-t border-ink/15">
                            {NAV_LINKS.map((item) => (
                                <MenuLink
                                    key={item.label}
                                    item={item}
                                    onNavigate={close}
                                />
                            ))}
                        </div>
                    </nav>

                    <div className="mt-auto flex flex-col gap-3 pt-10">
                        {ACCOUNT_LINKS.map((item, index) => (
                            <Button
                                key={item.label}
                                asChild
                                variant={index === 0 ? 'outline' : 'primary'}
                                className="w-full"
                            >
                                <Link href={item.href} onClick={close}>
                                    {item.label}
                                </Link>
                            </Button>
                        ))}
                    </div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
