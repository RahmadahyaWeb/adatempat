import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { PaginationLink } from '@/types/catalog';

const labels: Record<string, string> = {
    '&laquo; Previous': 'Sebelumnya',
    'Next &raquo;': 'Berikutnya',
};

function pageLabel(label: string): string {
    return (
        labels[label] ??
        label.replaceAll('&laquo;', '«').replaceAll('&raquo;', '»')
    );
}

export function Pagination({
    links,
    className,
}: {
    links: PaginationLink[];
    className?: string;
}) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav
            aria-label="Navigasi halaman"
            className={cn('flex flex-wrap gap-1', className)}
        >
            {links.map((link, index) => {
                const label = pageLabel(link.label);
                const styles =
                    'inline-flex min-h-11 min-w-11 items-center justify-center rounded-control border border-ink px-3 font-data text-xs tabular-nums';

                if (link.active) {
                    return (
                        <span
                            key={index}
                            aria-current="page"
                            className={cn(styles, 'bg-ink text-cream')}
                        >
                            {label}
                        </span>
                    );
                }

                if (!link.url) {
                    return (
                        <span
                            key={index}
                            aria-disabled="true"
                            className={cn(styles, 'border-ink/20 text-stone')}
                        >
                            {label}
                        </span>
                    );
                }

                return (
                    <Link
                        key={index}
                        href={link.url}
                        className={cn(
                            styles,
                            'text-ink transition-colors hover:bg-ink hover:text-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt',
                        )}
                    >
                        {label}
                    </Link>
                );
            })}
        </nav>
    );
}
