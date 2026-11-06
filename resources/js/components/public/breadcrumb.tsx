import { Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';

type BreadcrumbItem = {
    label: string;
    href?: ComponentProps<typeof Link>['href'];
};

export function Breadcrumb({ items }: { items: BreadcrumbItem[] }) {
    return (
        <nav aria-label="Navigasi halaman">
            <ol className="flex flex-wrap items-center gap-x-2 font-data text-xs text-stone">
                {items.map((item, index) => (
                    <li
                        key={index}
                        className="flex min-w-0 items-center gap-x-2"
                    >
                        {index > 0 && (
                            <span aria-hidden="true" className="text-ink/30">
                                /
                            </span>
                        )}
                        {item.href ? (
                            <Link
                                href={item.href}
                                className="inline-flex min-h-11 max-w-full items-center break-words underline-offset-4 hover:text-ink hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt"
                            >
                                {item.label}
                            </Link>
                        ) : (
                            <span
                                aria-current="page"
                                className="inline-flex min-h-11 min-w-0 items-center break-words text-ink"
                            >
                                {item.label}
                            </span>
                        )}
                    </li>
                ))}
            </ol>
        </nav>
    );
}
