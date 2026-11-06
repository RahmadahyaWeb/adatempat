import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

export const eyebrowClass =
    'font-data text-[11px] leading-normal uppercase tracking-[0.16em] text-ink/70';

export function Eyebrow({ className, ...props }: ComponentProps<'p'>) {
    return <p className={cn(eyebrowClass, className)} {...props} />;
}
