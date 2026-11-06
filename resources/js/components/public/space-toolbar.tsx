import * as Collapsible from '@radix-ui/react-collapsible';
import { Search, X } from 'lucide-react';
import { type FormEvent, useEffect, useId, useState } from 'react';
import { formatRupiah } from '@/lib/catalog';
import type { SpaceFilters, SpaceSort } from '@/types/catalog';
import { Button } from './button';
import { Chip } from './chip';
import { Select } from './select';
import {
    browseSpaces,
    clearSpaceFilters,
    countActiveFilters,
    DEFAULT_SORT,
    draftFrom,
    FilterLabel,
    labelStyles,
    SpaceFilterForm,
    SpaceFilterSheet,
} from './space-filters';

type SpaceToolbarProps = {
    filters: SpaceFilters;
    cities: string[];
    categories: { value: string; label: string }[];
    priceRange: { min: number; max: number };
    sort: SpaceSort;
    total: number;
};

export function SpaceToolbar({
    filters,
    cities,
    categories,
    priceRange,
    sort,
    total,
}: SpaceToolbarProps) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState(filters.q ?? '');

    // Sinkronkan state pencarian jika URL filter berubah
    useEffect(() => {
        setSearchQuery(filters.q ?? '');
    }, [filters.q]);

    const applied = draftFrom(filters);
    const filterKey = JSON.stringify(filters);
    const filterProps = { filters, cities, priceRange, sort };
    const activeCount = countActiveFilters(filters);

    function handleSearchSubmit(e: FormEvent) {
        e.preventDefault();
        browseSpaces({ ...applied, q: searchQuery.trim() }, sort);
    }

    function clearSearch() {
        setSearchQuery('');
        browseSpaces({ ...applied, q: '' }, sort);
    }

    return (
        <Collapsible.Root open={open} onOpenChange={setOpen}>
            <div className="flex flex-col gap-5">
                {/* Baris Chip Kategori - Scroll horizontal halus di mobile, wrap rapi di desktop */}
                <div
                    role="group"
                    aria-label="Kategori tempat dan ruang"
                    className="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0 sm:pb-0 lg:gap-1.5"
                >
                    <Chip
                        active={!filters.category}
                        className="shrink-0 px-3.5"
                        onClick={() =>
                            browseSpaces({ ...applied, category: '' }, sort)
                        }
                    >
                        Semua
                    </Chip>
                    {categories.map((category) => (
                        <Chip
                            key={category.value}
                            active={filters.category === category.value}
                            className="shrink-0 px-3.5"
                            onClick={() =>
                                browseSpaces(
                                    { ...applied, category: category.value },
                                    sort,
                                )
                            }
                        >
                            {category.label}
                        </Chip>
                    ))}
                </div>

                {/* Baris Status & Kontrol Terpadu */}
                <div className="flex flex-col gap-4 border-y border-ink/15 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <p
                        role="status"
                        className="font-data text-xs font-medium text-stone"
                    >
                        <span className="text-ink">
                            {total.toLocaleString('id-ID')}
                        </span>{' '}
                        tempat tersedia
                    </p>

                    <div className="flex flex-wrap items-center gap-3">
                        {/* Search Input Cepat */}
                        <form
                            onSubmit={handleSearchSubmit}
                            className="relative flex w-full items-center sm:w-auto"
                        >
                            <label htmlFor={`${id}-search`} className="sr-only">
                                Cari nama tempat, ruang, atau venue
                            </label>
                            <Search
                                aria-hidden="true"
                                className="pointer-events-none absolute left-3 size-4 text-stone"
                            />
                            <input
                                id={`${id}-search`}
                                type="search"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Cari tempat, studio, ruang meeting..."
                                className="h-10 w-full rounded-control border border-ink/20 bg-cream pr-8 pl-9 font-body text-xs text-ink placeholder:text-stone/70 focus:border-ink focus:outline-2 focus:outline-cobalt sm:w-56 lg:w-64"
                            />
                            {searchQuery && (
                                <button
                                    type="button"
                                    onClick={clearSearch}
                                    className="absolute right-2.5 inline-flex size-5 items-center justify-center text-stone hover:text-ink focus-visible:outline-2 focus-visible:outline-cobalt"
                                >
                                    <X
                                        aria-hidden="true"
                                        className="size-3.5"
                                    />
                                    <span className="sr-only">
                                        Hapus kata kunci pencarian
                                    </span>
                                </button>
                            )}
                        </form>

                        {/* Pengurutan */}
                        <div className="flex items-center gap-2">
                            <label
                                htmlFor={`${id}-sort`}
                                className={labelStyles}
                            >
                                Urutkan
                            </label>
                            <Select
                                id={`${id}-sort`}
                                name="sort"
                                className="h-10 w-auto text-xs"
                                value={sort}
                                onChange={(event) =>
                                    browseSpaces(
                                        applied,
                                        event.target.value === 'price_desc'
                                            ? 'price_desc'
                                            : DEFAULT_SORT,
                                    )
                                }
                            >
                                <option value="price_asc">
                                    Harga terendah
                                </option>
                                <option value="price_desc">
                                    Harga tertinggi
                                </option>
                            </Select>
                        </div>

                        {/* Tombol Filter Desktop */}
                        <Collapsible.Trigger asChild>
                            <Button
                                variant="outline"
                                size="sm"
                                className="hidden lg:inline-flex"
                            >
                                <FilterLabel count={activeCount} />
                            </Button>
                        </Collapsible.Trigger>

                        {/* Tombol Filter Mobile Sheet */}
                        <div className="lg:hidden">
                            <SpaceFilterSheet {...filterProps} />
                        </div>
                    </div>
                </div>

                {/* Baris Tag Filter Aktif (jika ada filter yang diterapkan) */}
                {activeCount > 0 && (
                    <div className="flex flex-wrap items-center gap-2 pt-1 font-data text-xs text-stone">
                        <span className="font-medium text-ink">
                            Filter aktif:
                        </span>

                        {filters.q && (
                            <button
                                type="button"
                                onClick={() =>
                                    browseSpaces({ ...applied, q: '' }, sort)
                                }
                                className="inline-flex items-center gap-1.5 rounded-control border border-ink/20 bg-sand/60 px-2.5 py-1 text-ink transition-colors hover:border-ink hover:bg-sand focus-visible:outline-2 focus-visible:outline-cobalt"
                            >
                                <span>Kata kunci: "{filters.q}"</span>
                                <X aria-hidden="true" className="size-3" />
                                <span className="sr-only">
                                    Hapus kata kunci
                                </span>
                            </button>
                        )}

                        {filters.city && (
                            <button
                                type="button"
                                onClick={() =>
                                    browseSpaces({ ...applied, city: '' }, sort)
                                }
                                className="inline-flex items-center gap-1.5 rounded-control border border-ink/20 bg-sand/60 px-2.5 py-1 text-ink transition-colors hover:border-ink hover:bg-sand focus-visible:outline-2 focus-visible:outline-cobalt"
                            >
                                <span>Kota: {filters.city}</span>
                                <X aria-hidden="true" className="size-3" />
                                <span className="sr-only">
                                    Hapus filter kota
                                </span>
                            </button>
                        )}

                        {filters.date && (
                            <button
                                type="button"
                                onClick={() =>
                                    browseSpaces({ ...applied, date: '' }, sort)
                                }
                                className="inline-flex items-center gap-1.5 rounded-control border border-ink/20 bg-sand/60 px-2.5 py-1 text-ink transition-colors hover:border-ink hover:bg-sand focus-visible:outline-2 focus-visible:outline-cobalt"
                            >
                                <span>Tanggal: {filters.date}</span>
                                <X aria-hidden="true" className="size-3" />
                                <span className="sr-only">
                                    Hapus filter tanggal
                                </span>
                            </button>
                        )}

                        {(filters.min_price || filters.max_price) && (
                            <button
                                type="button"
                                onClick={() =>
                                    browseSpaces(
                                        {
                                            ...applied,
                                            min_price: '',
                                            max_price: '',
                                        },
                                        sort,
                                    )
                                }
                                className="inline-flex items-center gap-1.5 rounded-control border border-ink/20 bg-sand/60 px-2.5 py-1 text-ink transition-colors hover:border-ink hover:bg-sand focus-visible:outline-2 focus-visible:outline-cobalt"
                            >
                                <span>
                                    Harga:{' '}
                                    {filters.min_price
                                        ? formatRupiah(filters.min_price)
                                        : '0'}{' '}
                                    -{' '}
                                    {filters.max_price
                                        ? formatRupiah(filters.max_price)
                                        : 'Max'}
                                </span>
                                <X aria-hidden="true" className="size-3" />
                                <span className="sr-only">
                                    Hapus filter harga
                                </span>
                            </button>
                        )}

                        <button
                            type="button"
                            onClick={() => clearSpaceFilters(sort)}
                            className="font-body text-xs text-stone underline underline-offset-4 transition-colors hover:text-ink focus-visible:outline-2 focus-visible:outline-cobalt"
                        >
                            Hapus semua filter
                        </button>
                    </div>
                )}
            </div>

            {/* Desktop Collapsible Panel - Boxed arsitektural */}
            <Collapsible.Content className="mt-6 hidden lg:block">
                <div className="border border-ink/15 bg-sand/30 p-6">
                    <div className="mb-4 flex items-center justify-between border-b border-ink/10 pb-3">
                        <p className="font-display text-sm font-bold tracking-tight text-ink">
                            Saring Pencarian
                        </p>
                        <p className="font-data text-xs text-stone">
                            Atur kota, tanggal sewa, atau batas tarif per jam
                        </p>
                    </div>
                    <SpaceFilterForm key={filterKey} {...filterProps} />
                </div>
            </Collapsible.Content>
        </Collapsible.Root>
    );
}
