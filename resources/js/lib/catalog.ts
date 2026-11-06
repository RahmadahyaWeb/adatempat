const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export function formatRupiah(amount: number): string {
    return rupiah.format(amount);
}

const categoryPhotos: Record<string, string> = {
    futsal: '/images/venues/hero-futsal.jpg',
    badminton: '/images/venues/badminton.jpg',
    basketball: '/images/venues/basketball.jpg',
    tennis: '/images/venues/futsal.jpg',
    meeting: '/images/venues/meeting.jpg',
    studio: '/images/venues/meeting-2.jpg',
    coworking: '/images/venues/meeting-2.jpg',
    event: '/images/venues/meeting-2.jpg',
    other: '/images/venues/meeting-2.jpg',
};

const categoryPhotoAlts: Record<string, string> = {
    futsal: 'Bola futsal di atas lapangan rumput sintetis',
    badminton: 'Pemain badminton melompat di lapangan',
    basketball: 'Bola basket melewati ring',
    tennis: 'Bola di atas lapangan rumput sintetis',
    meeting: 'Ruang meeting dengan meja kayu dan jendela besar',
    studio: 'Koridor dengan ruang berpintu kaca',
    coworking: 'Koridor dengan ruang berpintu kaca',
    event: 'Koridor dengan ruang berpintu kaca',
    other: 'Koridor dengan ruang berpintu kaca',
};

// shortcut: fallback memakai foto lokal per kategori sampai upload cover tersedia
export function categoryImage(category?: string | null): string {
    return (
        (category && categoryPhotos[category]) ?? '/images/venues/meeting.jpg'
    );
}

export function categoryImageAlt(category?: string | null): string {
    return (
        (category && categoryPhotoAlts[category]) ??
        'Ruang meeting dengan meja kayu dan jendela besar'
    );
}

const roomCategories = new Set(['meeting', 'studio', 'coworking', 'event']);

export function isRoomCategory(category?: string | null): boolean {
    return !!category && roomCategories.has(category);
}

type VenueCoverSource = {
    cover_photo_path: string | null;
    spaces: Array<{
        category: string | null | undefined;
        category_label: string;
    }>;
};

export function venueCoverSrc(venue: VenueCoverSource): string {
    return venue.cover_photo_path
        ? `/storage/${venue.cover_photo_path}`
        : categoryImage(venue.spaces[0]?.category);
}

export function venueCategoryLabels(venue: VenueCoverSource): string {
    const labels = [
        ...new Set(venue.spaces.map((space) => space.category_label)),
    ];

    return labels.slice(0, 2).join(', ');
}

const timezoneLabels: Record<string, string> = {
    'Asia/Jakarta': 'WIB',
    'Asia/Makassar': 'WITA',
    'Asia/Jayapura': 'WIT',
};

// shortcut: zona waktu Indonesia umum; yang tidak dikenal dikembalikan apa adanya
export function timezoneLabel(timezone: string): string {
    return timezoneLabels[timezone] ?? timezone;
}

const dayNames = [
    'Minggu',
    'Senin',
    'Selasa',
    'Rabu',
    'Kamis',
    'Jumat',
    'Sabtu',
];

export function dayLabel(dayOfWeek: number): string {
    return dayNames[dayOfWeek] ?? '';
}

// '08:00:00' -> '08:00'
export function formatTime(time: string): string {
    return time.slice(0, 5);
}

export function parseDate(dateStr: string): Date {
    const [y, m, d] = dateStr.split('-').map(Number);
    return new Date(y, m - 1, d);
}

export function toDateString(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

export function addDays(dateStr: string, count: number): string {
    const d = parseDate(dateStr);
    d.setDate(d.getDate() + count);
    return toDateString(d);
}

export function daysDiff(fromStr: string, toStr: string): number {
    const d1 = parseDate(fromStr).getTime();
    const d2 = parseDate(toStr).getTime();
    return Math.round((d2 - d1) / (1000 * 60 * 60 * 24));
}

const shortDayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
const shortMonthNames = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
];
const fullMonthNames = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

export function formatDayShort(dateStr: string): string {
    const d = parseDate(dateStr);
    return shortDayNames[d.getDay()] ?? '';
}

export function formatDayNum(dateStr: string): string {
    const d = parseDate(dateStr);
    return String(d.getDate());
}

export function formatMonthShort(dateStr: string): string {
    const d = parseDate(dateStr);
    return shortMonthNames[d.getMonth()] ?? '';
}

export function formatFullIndonesianDate(dateStr: string): string {
    const d = parseDate(dateStr);
    const dayName = dayNames[d.getDay()] ?? '';
    const dateNum = d.getDate();
    const monthName = fullMonthNames[d.getMonth()] ?? '';
    const year = d.getFullYear();
    return `${dayName}, ${dateNum} ${monthName} ${year}`;
}
