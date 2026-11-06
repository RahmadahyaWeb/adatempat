import { Link } from '@inertiajs/react';

import { index as spacesIndex } from '@/routes/spaces';
import type { CategoryOption } from '@/types/catalog';
import { cn } from '@/lib/utils';

const chipClass =
    'inline-flex min-h-11 items-center gap-2 rounded-full border px-4 text-[14px] transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt motion-reduce:transition-none';

export function CategoryChips({
    categories,
}: {
    categories: CategoryOption[];
}) {
    return (
        <ul aria-label="Kategori venue" className="flex flex-wrap gap-2">
            <li>
                <Link
                    href={spacesIndex.url()}
                    className={cn(chipClass, 'border-ink bg-ink text-white')}
                >
                    Semua
                </Link>
            </li>
            {categories.map((category) => (
                <li key={category.value}>
                    <Link
                        href={spacesIndex.url({
                            query: { category: category.value },
                        })}
                        className={cn(
                            chipClass,
                            'border-brown text-ink hover:border-ink hover:bg-white',
                        )}
                    >
                        {category.label}
                        <span className="font-data text-[12px] text-ink/70">
                            {category.count}
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}
