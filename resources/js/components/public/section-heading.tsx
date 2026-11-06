import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type SectionHeadingProps = {
    eyebrow: string;
    title: string;
    titleId?: string;
    headingLevel?: 'h1' | 'h2';
    description?: string;
    action?: ReactNode;
    className?: string;
};

export function SectionHeading({
    eyebrow,
    title,
    titleId,
    headingLevel: Heading = 'h2',
    description,
    action,
    className,
}: SectionHeadingProps) {
    return (
        <div
            className={cn(
                'flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between lg:gap-12',
                className,
            )}
        >
            <div className="lg:max-w-[34rem]">
                <p className="flex items-center gap-2 font-data text-xs tracking-[0.18em] text-stone uppercase">
                    <span aria-hidden="true" className="size-1.5 bg-orange" />
                    {eyebrow}
                </p>
                <Heading
                    id={titleId}
                    className="mt-4 font-display text-heading font-extrabold text-balance text-ink [font-stretch:82%]"
                >
                    {title}
                </Heading>
            </div>
            {(description || action) && (
                <div className="lg:max-w-[40ch] lg:shrink-0 lg:pb-1">
                    {description && (
                        <p className="text-base leading-[1.6] text-pretty text-stone">
                            {description}
                        </p>
                    )}
                    {action && <div className="mt-4">{action}</div>}
                </div>
            )}
        </div>
    );
}
