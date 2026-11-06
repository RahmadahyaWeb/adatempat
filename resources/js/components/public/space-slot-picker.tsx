import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { AlertCircle, ChevronLeft, ChevronRight, Phone } from 'lucide-react';
import { Button } from '@/components/public/button';
import {
    addDays,
    daysDiff,
    formatDayNum,
    formatDayShort,
    formatFullIndonesianDate,
    formatMonthShort,
    formatRupiah,
    timezoneLabel,
} from '@/lib/catalog';
import { cn } from '@/lib/utils';
import type {
    SlotCell,
    SlotResponse,
    SpaceDetail,
    SpaceDetailVenue,
} from '@/types/catalog';

export type SpaceSlotPickerProps = {
    space: SpaceDetail;
    venue: SpaceDetailVenue;
    initialDate: string;
    initialSlots: SlotResponse;
    className?: string;
};

function phoneHref(phone: string): string {
    return `tel:${phone.replace(/[^\d+]/g, '')}`;
}

export function SpaceSlotPicker({
    space,
    venue,
    initialDate,
    initialSlots,
    className,
}: SpaceSlotPickerProps) {
    const { props } = usePage<{
        flash?: { error?: string | null; success?: string | null };
    }>();
    const flashError = props.flash?.error;

    const [selectedDate, setSelectedDate] = useState<string>(initialDate);
    const [windowStart, setWindowStart] = useState<string>(initialDate);
    const [slotsData, setSlotsData] = useState<SlotResponse>(initialSlots);
    const [isLoading, setIsLoading] = useState<boolean>(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [selectedSlots, setSelectedSlots] = useState<SlotCell[]>([]);
    const [conflictNotice, setConflictNotice] = useState<string | null>(
        () => flashError || null,
    );

    useEffect(() => {
        if (flashError) {
            setConflictNotice(flashError);
        }
    }, [flashError]);
    // 7 days in the current date strip window
    const dateStrip = useMemo(() => {
        return Array.from({ length: 7 }, (_, i) => addDays(windowStart, i));
    }, [windowStart]);

    // Horizon constraints: H+0 to H+30 from initialDate
    const canPrev = daysDiff(initialDate, windowStart) > 0;
    const canNext = daysDiff(initialDate, addDays(windowStart, 7)) <= 30;

    const handlePrevDays = () => {
        if (!canPrev) return;
        const newStart = addDays(windowStart, -7);
        // Do not go earlier than initialDate
        const clampedStart =
            daysDiff(initialDate, newStart) < 0 ? initialDate : newStart;
        setWindowStart(clampedStart);
    };

    const handleNextDays = () => {
        if (!canNext) return;
        const newStart = addDays(windowStart, 7);
        setWindowStart(newStart);
    };

    // Fetch slots for a date
    const fetchSlots = useCallback(
        async (targetDate: string, isBackground = false) => {
            if (!isBackground) {
                setIsLoading(true);
                setErrorMessage(null);
            }

            try {
                const response = await fetch(
                    `/spaces/${encodeURIComponent(space.slug)}/slots?date=${encodeURIComponent(targetDate)}`,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    throw new Error(err.message || 'Gagal memuat jadwal slot.');
                }

                const data: SlotResponse = await response.json();
                setSlotsData(data);

                // Re-evaluate selected slots against new availability
                setSelectedSlots((prevSelected) => {
                    const newlyUnavailable = prevSelected.filter((sel) => {
                        const updated = data.slots.find(
                            (s) =>
                                s.start_time === sel.start_time &&
                                s.end_time === sel.end_time,
                        );
                        return updated && updated.status !== 'available';
                    });

                    if (newlyUnavailable.length > 0) {
                        const times = newlyUnavailable
                            .map((s) => s.start_time)
                            .join(', ');
                        setConflictNotice(
                            `Slot ${times} baru saja dipesan oleh pengguna lain dan telah dilepas dari pilihan Anda.`,
                        );
                        return prevSelected.filter(
                            (sel) =>
                                !newlyUnavailable.some(
                                    (u) => u.start_time === sel.start_time,
                                ),
                        );
                    }

                    return prevSelected;
                });
            } catch (err: unknown) {
                if (!isBackground) {
                    const message =
                        err instanceof Error
                            ? err.message
                            : 'Gagal memuat jadwal slot.';
                    setErrorMessage(message);
                }
            } finally {
                if (!isBackground) {
                    setIsLoading(false);
                }
            }
        },
        [space.slug],
    );

    // Switch date
    const handleSelectDate = (date: string) => {
        if (date === selectedDate) return;
        setSelectedDate(date);
        setSelectedSlots([]);
        setConflictNotice(null);

        if (date === initialDate && initialSlots.date === initialDate) {
            setSlotsData(initialSlots);
            setIsLoading(false);
            setErrorMessage(null);
        } else {
            void fetchSlots(date);
        }
    };

    const handleDateKeyDown = (
        e: React.KeyboardEvent<HTMLButtonElement>,
        currentIndex: number,
    ) => {
        let targetIndex: number | null = null;
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
            e.preventDefault();
            for (let i = currentIndex + 1; i < dateStrip.length; i++) {
                const diff = daysDiff(initialDate, dateStrip[i]);
                if (diff >= 0 && diff <= 30) {
                    targetIndex = i;
                    break;
                }
            }
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
            e.preventDefault();
            for (let i = currentIndex - 1; i >= 0; i--) {
                const diff = daysDiff(initialDate, dateStrip[i]);
                if (diff >= 0 && diff <= 30) {
                    targetIndex = i;
                    break;
                }
            }
        }

        if (targetIndex !== null) {
            const nextDate = dateStrip[targetIndex];
            handleSelectDate(nextDate);
            const buttons =
                e.currentTarget.parentElement?.querySelectorAll<HTMLButtonElement>(
                    'button[role="radio"]',
                );
            buttons?.[targetIndex]?.focus();
        }
    };

    // Polling every 15s when tab is visible
    useEffect(() => {
        const interval = setInterval(() => {
            if (document.visibilityState === 'visible') {
                void fetchSlots(selectedDate, true);
            }
        }, 15000);

        return () => clearInterval(interval);
    }, [selectedDate, fetchSlots]);

    // Slot toggle
    const handleToggleSlot = (slot: SlotCell) => {
        if (slot.status !== 'available') return;
        setConflictNotice(null);

        setSelectedSlots((prev) => {
            const exists = prev.some(
                (s) =>
                    s.start_time === slot.start_time &&
                    s.end_time === slot.end_time,
            );
            if (exists) {
                return prev.filter((s) => s.start_time !== slot.start_time);
            } else {
                return [...prev, slot].sort((a, b) =>
                    a.start_time.localeCompare(b.start_time),
                );
            }
        });
    };

    // Calculations for selected slots
    const totalPrice = useMemo(() => {
        return selectedSlots.reduce((sum, slot) => sum + slot.price, 0);
    }, [selectedSlots]);

    const formattedSlotRange = useMemo(() => {
        if (selectedSlots.length === 0) return null;
        if (selectedSlots.length === 1) {
            return `${selectedSlots[0].start_time} – ${selectedSlots[0].end_time}`;
        }
        const first = selectedSlots[0];
        const last = selectedSlots[selectedSlots.length - 1];
        return `${first.start_time} – ${last.end_time} (${selectedSlots.length} slot)`;
    }, [selectedSlots]);

    const checkoutUrl = useMemo(() => {
        if (selectedSlots.length === 0) return '#';
        const slotTimes = selectedSlots.map((s) => s.start_time).join(',');
        return `/checkout?space=${encodeURIComponent(space.slug)}&date=${encodeURIComponent(selectedDate)}&slots=${encodeURIComponent(slotTimes)}`;
    }, [selectedSlots, selectedDate, space.slug]);

    return (
        <section
            aria-label="Pilih tanggal dan slot jadwal"
            className={cn('space-y-6', className)}
        >
            {/* Conflict Alert Notice (e.g. from checkout redirect or real-time conflict) */}
            {conflictNotice && (
                <div
                    role="alert"
                    className="flex items-start gap-3 border border-amber-600/40 bg-amber-500/10 p-4 text-ink"
                >
                    <AlertCircle className="mt-0.5 size-5 shrink-0 text-amber-700" />
                    <p className="font-body text-sm leading-[1.5] text-ink">
                        {conflictNotice}
                    </p>
                </div>
            )}

            {/* Date Selector Strip */}
            <div className="border border-ink/20 bg-cream p-4 sm:p-5">
                <div className="flex items-center justify-between border-b border-ink/15 pb-3">
                    <div>
                        <h2 className="font-data text-xs tracking-[0.18em] text-stone uppercase">
                            Pilih Tanggal
                        </h2>
                        <p className="mt-0.5 font-display text-sm font-bold text-ink">
                            {formatFullIndonesianDate(selectedDate)}
                        </p>
                    </div>

                    <div className="flex items-center gap-1.5">
                        <button
                            type="button"
                            onClick={handlePrevDays}
                            disabled={!canPrev}
                            aria-label="7 hari sebelumnya"
                            className="inline-flex size-9 items-center justify-center border border-ink/20 bg-cream text-ink hover:border-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt disabled:cursor-not-allowed disabled:opacity-30"
                        >
                            <ChevronLeft className="size-4 shrink-0" />
                        </button>
                        <button
                            type="button"
                            onClick={handleNextDays}
                            disabled={!canNext}
                            aria-label="7 hari berikutnya"
                            className="inline-flex size-9 items-center justify-center border border-ink/20 bg-cream text-ink hover:border-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt disabled:cursor-not-allowed disabled:opacity-30"
                        >
                            <ChevronRight className="size-4 shrink-0" />
                        </button>
                    </div>
                </div>

                {/* 7-day strip */}
                <div
                    role="radiogroup"
                    aria-label="Pilihan 7 hari ke depan"
                    className="mt-3 grid grid-cols-7 gap-1 sm:gap-2"
                >
                    {dateStrip.map((d, index) => {
                        const isSelected = d === selectedDate;
                        const isToday = d === initialDate;
                        const isBeyondHorizon =
                            daysDiff(initialDate, d) < 0 ||
                            daysDiff(initialDate, d) > 30;
                        const hasSelectedInStrip =
                            dateStrip.includes(selectedDate);
                        const firstValidIndex = dateStrip.findIndex((date) => {
                            const diff = daysDiff(initialDate, date);
                            return diff >= 0 && diff <= 30;
                        });
                        const isFocusable = hasSelectedInStrip
                            ? isSelected
                            : index ===
                              (firstValidIndex !== -1 ? firstValidIndex : 0);

                        return (
                            <button
                                key={d}
                                type="button"
                                role="radio"
                                aria-checked={isSelected}
                                tabIndex={isFocusable ? 0 : -1}
                                disabled={isBeyondHorizon}
                                onClick={() => handleSelectDate(d)}
                                onKeyDown={(e) => handleDateKeyDown(e, index)}
                                className={cn(
                                    'group flex min-h-[58px] flex-col items-center justify-center rounded-none border p-1 text-center transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt sm:min-h-[64px]',
                                    isSelected
                                        ? 'border-ink bg-ink text-cream shadow-xs'
                                        : 'border-ink/15 bg-sand/20 text-ink hover:border-ink hover:bg-sand/40',
                                    isBeyondHorizon &&
                                        'cursor-not-allowed opacity-30',
                                )}
                            >
                                <span
                                    className={cn(
                                        'font-data text-[10px] uppercase sm:text-xs',
                                        isSelected
                                            ? 'text-cream/80'
                                            : 'text-stone',
                                    )}
                                >
                                    {formatDayShort(d)}
                                </span>
                                <span className="font-display text-sm font-bold tabular-nums sm:text-base">
                                    {formatDayNum(d)}
                                </span>
                                <span
                                    className={cn(
                                        'font-data text-[9px] uppercase sm:text-[10px]',
                                        isSelected
                                            ? 'text-cream/70'
                                            : 'text-stone',
                                    )}
                                >
                                    {isToday ? 'Hari ini' : formatMonthShort(d)}
                                </span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* Slot Matrix Grid */}
            <div className="border border-ink/20 bg-cream p-4 sm:p-5">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-ink/15 pb-3">
                    <div>
                        <h2 className="font-data text-xs tracking-[0.18em] text-stone uppercase">
                            Jadwal Slot ({timezoneLabel(venue.timezone)})
                        </h2>
                        {slotsData.slot_duration_minutes && (
                            <p className="mt-0.5 font-body text-xs text-stone">
                                Durasi {slotsData.slot_duration_minutes} menit /
                                slot
                            </p>
                        )}
                    </div>

                    <div className="flex items-center gap-3">
                        <span className="inline-flex items-center gap-1.5 font-data text-xs text-stone">
                            <span
                                aria-hidden="true"
                                className="size-2 rounded-full bg-emerald-600 motion-safe:animate-pulse"
                            />
                            Ketersediaan langsung
                        </span>
                    </div>
                </div>

                {/* Legend */}
                <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 font-data text-xs text-stone">
                    <span className="flex items-center gap-1.5">
                        <span className="size-3.5 border border-ink/30 bg-cream" />
                        Tersedia
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="size-3.5 border border-cobalt bg-cobalt" />
                        Dipilih
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="size-3.5 border border-black/10 bg-sand/50" />
                        Dipesan / Lewat
                    </span>
                </div>

                {/* Grid Content */}
                <div className="mt-4">
                    {isLoading ? (
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            {Array.from({ length: 6 }).map((_, i) => (
                                <div
                                    key={i}
                                    className="h-14 animate-pulse border border-ink/10 bg-sand/30"
                                />
                            ))}
                        </div>
                    ) : errorMessage ? (
                        <div className="border border-red-500/30 bg-red-500/5 p-4 text-center">
                            <p className="font-body text-sm text-ink">
                                {errorMessage}
                            </p>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => fetchSlots(selectedDate)}
                                className="mt-2"
                            >
                                Coba lagi
                            </Button>
                        </div>
                    ) : slotsData.slots.length === 0 ? (
                        <div className="border border-dashed border-ink/20 bg-sand/20 p-8 text-center">
                            <p className="font-body text-sm font-medium text-ink">
                                Tidak ada jadwal yang tersedia pada tanggal ini.
                            </p>
                            <p className="mt-1 font-body text-xs text-stone">
                                Yuk pilih hari atau tanggal lain di atas.
                            </p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            {slotsData.slots.map((slot) => {
                                const isSelected = selectedSlots.some(
                                    (s) =>
                                        s.start_time === slot.start_time &&
                                        s.end_time === slot.end_time,
                                );
                                const isAvailable = slot.status === 'available';
                                const isBooked = slot.status === 'booked';
                                const isPast = slot.status === 'past';

                                return (
                                    <button
                                        key={`${slot.start_time}-${slot.end_time}`}
                                        type="button"
                                        aria-pressed={isSelected}
                                        disabled={!isAvailable}
                                        onClick={() => handleToggleSlot(slot)}
                                        className={cn(
                                            'group relative flex min-h-12 flex-col items-center justify-center border p-2 text-center transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt',
                                            isSelected
                                                ? 'border-cobalt bg-cobalt text-white shadow-xs'
                                                : isAvailable
                                                  ? 'border-ink/25 bg-cream text-ink hover:border-ink hover:bg-sand/30'
                                                  : 'cursor-not-allowed border-black/10 bg-sand/30 text-stone/50',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'font-display text-sm font-bold tracking-tight tabular-nums',
                                                isSelected
                                                    ? 'text-white'
                                                    : isAvailable
                                                      ? 'text-ink'
                                                      : 'text-stone/60 line-through',
                                            )}
                                        >
                                            {slot.start_time} – {slot.end_time}
                                        </span>
                                        <span
                                            className={cn(
                                                'font-data text-[11px] tabular-nums',
                                                isSelected
                                                    ? 'text-white/90'
                                                    : isAvailable
                                                      ? 'text-stone'
                                                      : 'text-stone/50',
                                            )}
                                        >
                                            {isBooked
                                                ? 'Dipesan'
                                                : isPast
                                                  ? 'Lewat'
                                                  : formatRupiah(slot.price)}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>

            {/* Summary & Reservation Rail */}
            <aside
                aria-labelledby="booking-aside-heading"
                className="border border-ink/20 bg-sand/30 p-5 md:p-6"
            >
                <div className="flex items-center justify-between border-b border-ink/15 pb-3">
                    <h2
                        id="booking-aside-heading"
                        className="font-data text-xs tracking-[0.18em] text-stone uppercase"
                    >
                        Ringkasan Booking
                    </h2>
                    <span className="font-data text-xs text-stone">
                        {timezoneLabel(venue.timezone)}
                    </span>
                </div>

                <div className="mt-4 space-y-2">
                    <div className="flex items-baseline justify-between text-sm">
                        <span className="font-body text-stone">Tanggal</span>
                        <span className="font-body font-medium text-ink">
                            {formatFullIndonesianDate(selectedDate)}
                        </span>
                    </div>

                    <div className="flex items-baseline justify-between text-sm">
                        <span className="font-body text-stone">Slot Waktu</span>
                        <span className="font-body font-medium text-ink tabular-nums">
                            {formattedSlotRange ?? 'Belum ada slot dipilih'}
                        </span>
                    </div>

                    <div className="flex items-baseline justify-between border-t border-ink/15 pt-3">
                        <span className="font-display text-base font-bold text-ink">
                            Total Biaya Sewa
                        </span>
                        <span className="font-display text-xl font-bold text-ink tabular-nums">
                            {formatRupiah(totalPrice)}
                        </span>
                    </div>
                </div>

                <div className="mt-6 border-t border-ink/15 pt-5">
                    {selectedSlots.length > 0 ? (
                        <Button
                            asChild
                            className="min-h-12 w-full bg-cobalt font-bold text-white hover:bg-cobalt/90"
                        >
                            <Link href={checkoutUrl}>
                                Booking Tempat Sekarang
                            </Link>
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            disabled
                            className="min-h-12 w-full font-medium disabled:bg-sand disabled:text-stone disabled:opacity-100"
                        >
                            Pilih jam sewa untuk lanjut booking
                        </Button>
                    )}

                    {venue.contact_phone && (
                        <div className="mt-4 flex justify-center border-t border-ink/10 pt-3">
                            <a
                                href={phoneHref(venue.contact_phone)}
                                className="inline-flex min-h-11 items-center gap-2 font-body text-sm font-medium text-ink underline decoration-1 underline-offset-4 hover:decoration-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt"
                            >
                                <Phone
                                    aria-hidden="true"
                                    strokeWidth={1.5}
                                    className="size-4 shrink-0"
                                />
                                Ada pertanyaan? Hubungi pengelola (
                                {venue.contact_phone})
                            </a>
                        </div>
                    )}
                </div>
            </aside>
        </section>
    );
}
