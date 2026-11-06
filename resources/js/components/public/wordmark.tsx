import { cn } from '@/lib/utils';

export function Wordmark({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'font-display text-2xl font-extrabold tracking-[-0.04em] [font-stretch:82%]',
                className,
            )}
        >
            AdaTempat
        </span>
    );
}
