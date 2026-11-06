import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

export function Container({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn(
                'mx-auto w-full max-w-[1200px] px-4 md:px-6',
                className,
            )}
            {...props}
        />
    );
}
