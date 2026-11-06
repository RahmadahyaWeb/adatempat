import { Link } from '@inertiajs/react';
import type { ComponentProps, ReactNode } from 'react';
import { cn } from '@/lib/utils';

type ChipBaseProps = {
    active?: boolean;
    children: ReactNode;
    className?: string;
};

// Tanpa href: tombol toggle (aria-pressed), misalnya filter kategori di /spaces.
type ChipProps =
    | (ChipBaseProps & { href: ComponentProps<typeof Link>['href'] })
    | (ChipBaseProps &
          Omit<ComponentProps<'button'>, 'children' | 'className'> & {
              href?: undefined;
          });

export function Chip({
    active = false,
    children,
    className,
    ...props
}: ChipProps) {
    const styles = cn(
        'inline-flex min-h-11 items-center justify-center rounded-control border border-ink px-4 font-body text-sm transition-[background-color,color] duration-150 ease-out focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt',
        active ? 'bg-ink text-cream' : 'text-ink hover:bg-ink hover:text-cream',
        className,
    );

    if (props.href !== undefined) {
        return (
            <Link
                href={props.href}
                aria-current={active ? 'page' : undefined}
                className={styles}
            >
                {children}
            </Link>
        );
    }

    return (
        <button
            type="button"
            aria-pressed={active}
            className={styles}
            {...props}
        >
            {children}
        </button>
    );
}
