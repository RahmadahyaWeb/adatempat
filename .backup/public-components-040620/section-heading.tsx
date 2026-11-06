import type { ReactNode } from 'react';

import { Eyebrow } from '@/components/public/eyebrow';

export function SectionHeading({
    id,
    eyebrow,
    title,
    description,
    action,
}: {
    id: string;
    eyebrow: string;
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-6 md:flex-row md:items-end md:justify-between md:gap-10">
            <div className="max-w-2xl">
                <Eyebrow>{eyebrow}</Eyebrow>
                <h2
                    id={id}
                    className="mt-4 font-display text-[36px] leading-[1.02] font-bold tracking-[-0.02em] font-stretch-[87.5%] sm:text-[48px]"
                >
                    {title}
                </h2>
                {description ? (
                    <p className="mt-4 max-w-[34rem] text-[16px] leading-normal text-ink/80 md:text-[17px]">
                        {description}
                    </p>
                ) : null}
            </div>
            {action}
        </div>
    );
}
