import { router } from '@inertiajs/react';
import * as Dialog from '@radix-ui/react-dialog';
import { SlidersHorizontal, X } from 'lucide-react';
import { type FormEvent, useId, useState } from 'react';
import { formatRupiah } from '@/lib/catalog';
import { cn } from '@/lib/utils';
import { index as spacesIndex } from '@/routes/spaces';
import type { SpaceFilters, SpaceSort } from '@/types/catalog';
import { Button } from './button';
import { Input } from './input';
import { Select } from './select';

type SpaceFilterProps = {
    filters: SpaceFilters;
    cities: string[];
    priceRange: { min: number; max: number };
    sort: SpaceSort;
};

// Draft berbentuk string agar input kosong tidak dianggap angka 0.
export type Draft = Record<keyof SpaceFilters, string>;

export const DEFAULT_SORT: SpaceSort = 'price_asc';

export const labelStyles =
    'font-data text-xs tracking-[0.14em] text-stone uppercase';

const emptyDraft: Draft = {
    q: '',
    city: '',
    category: '',
    date: '',
    min_price: '',
    max_price: '',
};
export function countActiveFilters(filters: SpaceFilters): number {
    return Object.values(filters).filter(
        (value) => value !== null && value !== '',
    ).length;
}

export function draftFrom(filters: SpaceFilters): Draft {
    return {
        q: filters.q ?? '',
        city: filters.city ?? '',
        category: filters.category ?? '',
        date: filters.date ?? '',
        min_price: filters.min_price?.toString() ?? '',
        max_price: filters.max_price?.toString() ?? '',
    };
}

// Parameter kosong dan `page` tidak ikut: perubahan selalu mulai dari halaman 1.
// `sort` hanya ikut bila bukan default, supaya URL default tetap bersih.
export function browseSpaces(draft: Draft, sort: SpaceSort) {
    const query: Record<string, string> = Object.fromEntries(
        Object.entries(draft).filter(([, value]) => value !== ''),
    );

    if (sort !== DEFAULT_SORT) {
        query.sort = sort;
    }

    router.get(spacesIndex.url(), query, {
        preserveState: true,
        preserveScroll: true,
    });
}

export function clearSpaceFilters(sort: SpaceSort) {
    browseSpaces(emptyDraft, sort);
}

export function FilterLabel({ count }: { count: number }) {
    return (
        <>
            <SlidersHorizontal
                aria-hidden="true"
                strokeWidth={1.5}
                className="size-4"
            />
            Filter
            {count > 0 && ` (${count})`}
        </>
    );
}

export function SpaceFilterForm({
    filters,
    cities,
    priceRange,
    sort,
    onDone,
    className,
}: SpaceFilterProps & { onDone?: () => void; className?: string }) {
    const id = useId();
    const [draft, setDraft] = useState(() => draftFrom(filters));
    const hasFilters = countActiveFilters(filters) > 0;
    const priceHintId = priceRange.max > 0 ? `${id}-price-hint` : undefined;

    function set(key: keyof Draft, value: string) {
        setDraft((current) => ({ ...current, [key]: value }));
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        browseSpaces(draft, sort);
        onDone?.();
    }

    function clear() {
        clearSpaceFilters(sort);
        onDone?.();
    }

    return (
        <form
            onSubmit={submit}
            className={cn('flex flex-col gap-6', className)}
        >
            <div className="flex flex-col gap-2">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="flex min-w-0 flex-col gap-2">
                        <label htmlFor={`${id}-city`} className={labelStyles}>
                            Kota
                        </label>
                        <Select
                            id={`${id}-city`}
                            name="city"
                            value={draft.city}
                            onChange={(event) =>
                                set('city', event.target.value)
                            }
                        >
                            <option value="">Semua kota</option>
                            {cities.map((city) => (
                                <option key={city} value={city}>
                                    {city}
                                </option>
                            ))}
                        </Select>
                    </div>

                    <div className="flex min-w-0 flex-col gap-2">
                        <label htmlFor={`${id}-date`} className={labelStyles}>
                            Tanggal
                        </label>
                        <Input
                            id={`${id}-date`}
                            name="date"
                            type="date"
                            value={draft.date}
                            aria-describedby={`${id}-date-hint`}
                            onChange={(event) =>
                                set('date', event.target.value)
                            }
                        />
                    </div>

                    <div className="flex min-w-0 flex-col gap-2">
                        <label htmlFor={`${id}-min`} className={labelStyles}>
                            Harga minimum
                        </label>
                        <Input
                            id={`${id}-min`}
                            name="min_price"
                            type="number"
                            inputMode="numeric"
                            min={0}
                            aria-describedby={priceHintId}
                            value={draft.min_price}
                            onChange={(event) =>
                                set('min_price', event.target.value)
                            }
                        />
                    </div>

                    <div className="flex min-w-0 flex-col gap-2">
                        <label htmlFor={`${id}-max`} className={labelStyles}>
                            Harga maksimum
                        </label>
                        <Input
                            id={`${id}-max`}
                            name="max_price"
                            type="number"
                            inputMode="numeric"
                            min={draft.min_price || 0}
                            aria-describedby={priceHintId}
                            value={draft.max_price}
                            onChange={(event) =>
                                set('max_price', event.target.value)
                            }
                        />
                    </div>
                </div>

                <p className="flex flex-wrap gap-x-6 gap-y-1 font-data text-xs leading-[1.5] text-stone">
                    <span id={`${id}-date-hint`}>
                        Hanya menampilkan tempat yang buka pada tanggal ini.
                    </span>
                    {priceRange.max > 0 && (
                        <span id={priceHintId}>
                            Tarif di katalog: {formatRupiah(priceRange.min)} –{' '}
                            {formatRupiah(priceRange.max)} / jam.
                        </span>
                    )}
                </p>
            </div>

            <div
                className={cn(
                    'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end',
                    onDone && 'sticky bottom-0 bg-cream pt-4 pb-6',
                )}
            >
                {hasFilters && (
                    <Button
                        type="button"
                        variant="outline"
                        className="w-full sm:w-auto"
                        onClick={clear}
                    >
                        Reset filter
                    </Button>
                )}
                <Button type="submit" className="w-full sm:w-auto">
                    Terapkan
                </Button>
            </div>
        </form>
    );
}

export function SpaceFilterSheet(props: SpaceFilterProps) {
    const [open, setOpen] = useState(false);
    const activeCount = countActiveFilters(props.filters);

    return (
        <Dialog.Root open={open} onOpenChange={setOpen}>
            <Dialog.Trigger asChild>
                <Button variant="outline" size="sm">
                    <FilterLabel count={activeCount} />
                </Button>
            </Dialog.Trigger>

            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-50 bg-ink/40 transition-opacity duration-200 motion-reduce:transition-none starting:opacity-0" />
                <Dialog.Content
                    style={{ colorScheme: 'light' }}
                    className="fixed inset-x-0 bottom-0 z-50 flex max-h-[85dvh] scroll-pb-24 flex-col overflow-y-auto overscroll-contain border-t border-ink/15 bg-cream p-6 text-ink transition-transform duration-300 ease-out focus-visible:outline-none motion-reduce:transition-none starting:translate-y-full"
                >
                    <div className="flex items-center justify-between gap-4">
                        <Dialog.Title className="font-display text-2xl font-bold tracking-[-0.02em] text-ink [font-stretch:85%]">
                            Saring Pencarian
                        </Dialog.Title>
                        <Dialog.Close
                            type="button"
                            className="inline-flex size-11 items-center justify-center rounded-control border border-ink/20 text-ink transition-colors hover:bg-ink hover:text-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt"
                        >
                            <X
                                aria-hidden="true"
                                strokeWidth={1.5}
                                className="size-5"
                            />
                            <span className="sr-only">Tutup filter</span>
                        </Dialog.Close>
                    </div>
                    <Dialog.Description className="mt-2 font-body text-sm leading-[1.6] text-stone">
                        Pilih kota, tanggal sewa, atau batas tarif yang
                        diinginkan.
                    </Dialog.Description>

                    <SpaceFilterForm
                        {...props}
                        className="mt-8"
                        onDone={() => setOpen(false)}
                    />
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
