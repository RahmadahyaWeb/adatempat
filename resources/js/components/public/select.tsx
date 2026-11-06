import { ChevronDown } from 'lucide-react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

export function Select({
    className,
    children,
    ...props
}: ComponentProps<'select'>) {
    return (
        <div className="relative">
            <select
                className={cn(
                    'h-12 w-full appearance-none rounded-control border border-ink bg-cream px-3 pe-10 font-body text-base text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt disabled:cursor-not-allowed disabled:opacity-50',
                    className,
                )}
                {...props}
            >
                {children}
            </select>
            <ChevronDown
                aria-hidden="true"
                strokeWidth={1.5}
                className="pointer-events-none absolute end-3 top-1/2 size-4 -translate-y-1/2 text-ink"
            />
        </div>
    );
}
