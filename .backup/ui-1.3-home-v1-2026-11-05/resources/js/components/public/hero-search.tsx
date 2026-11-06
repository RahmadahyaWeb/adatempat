import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { useId } from 'react';

import { eyebrowClass } from '@/components/public/eyebrow';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { index as spacesIndex } from '@/routes/spaces';
import type { CategoryOption } from '@/types/catalog';

const fieldClass =
    'h-12 w-full rounded-md border border-ink bg-transparent px-3 text-[15px] text-ink outline-none focus-visible:ring-[3px] focus-visible:ring-cobalt/50 motion-reduce:transition-none';

function Field({
    id,
    label,
    children,
}: {
    id: string;
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex min-w-0 flex-col gap-2">
            <Label htmlFor={id} className={cn(eyebrowClass, 'font-normal')}>
                {label}
            </Label>
            {children}
        </div>
    );
}

export function HeroSearch({
    cities,
    categories,
    className,
}: {
    cities: string[];
    categories: CategoryOption[];
    className?: string;
}) {
    const id = useId();

    return (
        <form
            role="search"
            aria-label="Cari venue"
            action={spacesIndex.url()}
            method="get"
            className={cn(
                'grid gap-3 md:grid-cols-3 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end',
                className,
            )}
        >
            <Field id={`${id}-city`} label="Kota">
                <div className="relative">
                    <select
                        id={`${id}-city`}
                        name="city"
                        defaultValue=""
                        className={cn(fieldClass, 'appearance-none pr-10')}
                    >
                        <option value="">Semua kota</option>
                        {cities.map((city) => (
                            <option key={city} value={city}>
                                {city}
                            </option>
                        ))}
                    </select>
                    <ChevronDown
                        aria-hidden
                        className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-ink"
                    />
                </div>
            </Field>

            <Field id={`${id}-category`} label="Kategori">
                <div className="relative">
                    <select
                        id={`${id}-category`}
                        name="category"
                        defaultValue=""
                        className={cn(fieldClass, 'appearance-none pr-10')}
                    >
                        <option value="">Semua kategori</option>
                        {categories.map((category) => (
                            <option key={category.value} value={category.value}>
                                {category.label}
                            </option>
                        ))}
                    </select>
                    <ChevronDown
                        aria-hidden
                        className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-ink"
                    />
                </div>
            </Field>

            <Field id={`${id}-date`} label="Tanggal">
                <input
                    id={`${id}-date`}
                    name="date"
                    type="date"
                    className={cn(fieldClass, 'min-w-0')}
                />
            </Field>

            <Button
                type="submit"
                className="h-12 rounded-md bg-ink px-6 text-[15px] font-medium text-white shadow-none hover:bg-ink/90 focus-visible:ring-cobalt/50 md:col-span-3 lg:col-span-1"
            >
                Cari
            </Button>
        </form>
    );
}
