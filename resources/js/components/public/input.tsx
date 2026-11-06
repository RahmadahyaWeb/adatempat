import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

export function Input({ className, ...props }: ComponentProps<'input'>) {
    return (
        <input
            className={cn(
                'h-12 w-full rounded-control border border-ink bg-cream px-3 font-body text-base text-ink placeholder:text-stone focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
}
