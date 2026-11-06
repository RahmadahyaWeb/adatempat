import { Head, Link, router } from '@inertiajs/react';
import { Activity, History, Search } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import { index as auditIndex } from '@/routes/superadmin/audit';

type LogItem = {
    id: number;
    action: string;
    auditable_type: string | null;
    auditable_id: number | null;
    payload: Record<string, any> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
    actor?: {
        id: number;
        name: string;
        email: string;
    } | null;
};

type PaginatedLogs = {
    data: LogItem[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    logs: PaginatedLogs;
    filters: {
        category: 'all' | 'venue' | 'security';
        search: string;
    };
};

function formatDateTime(iso: string): string {
    try {
        const d = new Date(iso);
        return d.toLocaleString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    } catch {
        return iso;
    }
}

function getActionBadge(action: string) {
    if (action.startsWith('venue.approved')) {
        return (
            <Badge className="border-emerald-500/30 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">
                Persetujuan Venue
            </Badge>
        );
    }
    if (action.startsWith('venue.rejected')) {
        return (
            <Badge className="border-rose-500/30 bg-rose-500/15 text-rose-700 dark:text-rose-400">
                Penolakan Venue
            </Badge>
        );
    }
    if (action.startsWith('venue.unpublished')) {
        return (
            <Badge className="border-amber-500/30 bg-amber-500/15 text-amber-700 dark:text-amber-400">
                Pencabutan Publikasi
            </Badge>
        );
    }
    if (action === 'user.2fa_enabled') {
        return (
            <Badge className="border-blue-500/30 bg-blue-500/15 text-blue-700 dark:text-blue-400">
                2FA Diaktifkan
            </Badge>
        );
    }
    if (action === 'user.2fa_disabled') {
        return (
            <Badge className="border-purple-500/30 bg-purple-500/15 text-purple-700 dark:text-purple-400">
                2FA Dinonaktifkan
            </Badge>
        );
    }

    return (
        <Badge variant="outline" className="text-xs">
            {action}
        </Badge>
    );
}

function formatTarget(type: string | null, id: number | null): string {
    if (!type || !id) return '-';
    const cleanType = type.split('\\').pop() ?? type;
    return `${cleanType} #${id}`;
}

export default function SuperadminAuditIndex({ logs, filters }: Props) {
    const [searchValue, setSearchValue] = useState(filters.search || '');

    const handleCategoryChange = (category: 'all' | 'venue' | 'security') => {
        router.get(
            auditIndex(),
            {
                category,
                ...(searchValue ? { search: searchValue } : {}),
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            auditIndex(),
            {
                category: filters.category,
                ...(searchValue ? { search: searchValue } : {}),
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head title="Log Audit & Riwayat Forensik" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-8">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-stone-900/10 px-2.5 py-0.5 text-xs font-bold tracking-wider text-stone-800 uppercase dark:bg-stone-100/10 dark:text-stone-200">
                                <History className="size-3.5" />
                                Platform Audit Trail
                            </span>
                            <span className="text-xs text-stone-500">
                                Append-Only Forensic Ledger
                            </span>
                        </div>
                        <h1 className="mt-1.5 font-display text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">
                            Log Audit Platform
                        </h1>
                        <p className="mt-1 text-sm text-stone-600 dark:text-stone-400">
                            Catatan kronologis immutable untuk seluruh aksi
                            administratif, perubahan kurasi tempat, dan
                            aktivitas keamanan akun.
                        </p>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={() => handleCategoryChange('all')}
                            className={`rounded-xl px-3.5 py-2 text-xs font-semibold transition-colors ${
                                filters.category === 'all'
                                    ? 'bg-stone-900 text-stone-50 shadow-sm dark:bg-stone-100 dark:text-stone-900'
                                    : 'border border-stone-200/80 bg-white text-stone-600 hover:bg-stone-100 dark:border-neutral-800 dark:bg-neutral-900 dark:text-stone-300'
                            }`}
                        >
                            Semua Aksi
                        </button>
                        <button
                            type="button"
                            onClick={() => handleCategoryChange('venue')}
                            className={`rounded-xl px-3.5 py-2 text-xs font-semibold transition-colors ${
                                filters.category === 'venue'
                                    ? 'bg-stone-900 text-stone-50 shadow-sm dark:bg-stone-100 dark:text-stone-900'
                                    : 'border border-stone-200/80 bg-white text-stone-600 hover:bg-stone-100 dark:border-neutral-800 dark:bg-neutral-900 dark:text-stone-300'
                            }`}
                        >
                            Kurasi Venue
                        </button>
                        <button
                            type="button"
                            onClick={() => handleCategoryChange('security')}
                            className={`rounded-xl px-3.5 py-2 text-xs font-semibold transition-colors ${
                                filters.category === 'security'
                                    ? 'bg-stone-900 text-stone-50 shadow-sm dark:bg-stone-100 dark:text-stone-900'
                                    : 'border border-stone-200/80 bg-white text-stone-600 hover:bg-stone-100 dark:border-neutral-800 dark:bg-neutral-900 dark:text-stone-300'
                            }`}
                        >
                            Keamanan & 2FA
                        </button>
                    </div>

                    <form
                        onSubmit={handleSearch}
                        className="relative w-full sm:w-80"
                    >
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-400" />
                        <Input
                            type="text"
                            placeholder="Cari aksi, alasan, IP, aktor..."
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                            className="h-9 border-stone-200/80 bg-white pr-3 pl-9 text-xs dark:border-neutral-800 dark:bg-neutral-900"
                        />
                    </form>
                </div>

                {/* Audit Table or Empty State */}
                {logs.data.length === 0 ? (
                    <Card className="flex flex-col items-center justify-center border-dashed border-stone-300 p-12 text-center dark:border-neutral-700">
                        <div className="rounded-2xl bg-stone-100 p-4 dark:bg-neutral-800">
                            <Activity className="size-10 text-stone-400" />
                        </div>
                        <h3 className="mt-4 font-display text-lg font-bold text-stone-900 dark:text-stone-100">
                            Tidak Ada Log Ditemukan
                        </h3>
                        <p className="mt-1 max-w-sm text-sm text-stone-500">
                            Tidak ada aktivitas yang sesuai dengan filter
                            kategori atau kata kunci pencarian Anda.
                        </p>
                    </Card>
                ) : (
                    <div className="overflow-hidden rounded-xl border border-stone-200/80 bg-white shadow-xs dark:border-neutral-800 dark:bg-neutral-900">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="border-b border-stone-200/80 bg-stone-50/75 text-[11px] font-bold tracking-wider text-stone-500 uppercase dark:border-neutral-800 dark:bg-neutral-950/40">
                                    <tr>
                                        <th className="px-4 py-3">Waktu</th>
                                        <th className="px-4 py-3">Aksi</th>
                                        <th className="px-4 py-3">Aktor</th>
                                        <th className="px-4 py-3">Target</th>
                                        <th className="px-4 py-3">
                                            Payload / Detail
                                        </th>
                                        <th className="px-4 py-3">
                                            IP & Klien
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-stone-100 dark:divide-neutral-800">
                                    {logs.data.map((log) => (
                                        <tr
                                            key={log.id}
                                            className="transition-colors hover:bg-stone-50/50 dark:hover:bg-neutral-800/50"
                                        >
                                            <td className="px-4 py-3.5 whitespace-nowrap text-stone-600 dark:text-stone-400">
                                                {formatDateTime(log.created_at)}
                                            </td>
                                            <td className="px-4 py-3.5 whitespace-nowrap">
                                                <div className="flex flex-col items-start gap-1">
                                                    {getActionBadge(log.action)}
                                                    <span className="font-mono text-[10px] text-stone-400">
                                                        {log.action}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5 whitespace-nowrap">
                                                {log.actor ? (
                                                    <div>
                                                        <span className="block font-semibold text-foreground">
                                                            {log.actor.name}
                                                        </span>
                                                        <span className="text-[11px] text-stone-400">
                                                            {log.actor.email}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-stone-400 italic">
                                                        Sistem / Tamu
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 font-mono text-[11px] whitespace-nowrap text-stone-600 dark:text-stone-300">
                                                {formatTarget(
                                                    log.auditable_type,
                                                    log.auditable_id,
                                                )}
                                            </td>
                                            <td className="max-w-xs px-4 py-3.5 text-stone-700 dark:text-stone-300">
                                                {log.payload ? (
                                                    <div className="space-y-1">
                                                        {log.payload
                                                            .venue_name && (
                                                            <div>
                                                                <span className="font-semibold">
                                                                    Venue:
                                                                </span>{' '}
                                                                {
                                                                    log.payload
                                                                        .venue_name
                                                                }
                                                            </div>
                                                        )}
                                                        {log.payload
                                                            .business && (
                                                            <div className="text-[11px] text-stone-500">
                                                                <span>
                                                                    Mitra:
                                                                </span>{' '}
                                                                {
                                                                    log.payload
                                                                        .business
                                                                }
                                                            </div>
                                                        )}
                                                        {log.payload.reason && (
                                                            <div className="rounded bg-stone-100 p-1.5 text-[11px] text-stone-800 dark:bg-neutral-800 dark:text-stone-200">
                                                                <span className="font-semibold">
                                                                    Alasan:
                                                                </span>{' '}
                                                                {
                                                                    log.payload
                                                                        .reason
                                                                }
                                                            </div>
                                                        )}
                                                        {log.payload.email && (
                                                            <div className="text-[11px] text-stone-500">
                                                                <span>
                                                                    Email:
                                                                </span>{' '}
                                                                {
                                                                    log.payload
                                                                        .email
                                                                }
                                                            </div>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-stone-400">
                                                        -
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 text-[11px] whitespace-nowrap text-stone-500">
                                                <div className="font-mono">
                                                    {log.ip_address ||
                                                        '127.0.0.1'}
                                                </div>
                                                {log.user_agent && (
                                                    <div
                                                        className="max-w-[160px] truncate text-[10px] text-stone-400"
                                                        title={log.user_agent}
                                                    >
                                                        {log.user_agent}
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Pagination */}
                {logs.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-stone-200/80 pt-4 dark:border-neutral-800">
                        <span className="text-xs text-stone-500">
                            Menampilkan halaman {logs.current_page} dari{' '}
                            {logs.last_page} ({logs.total} total)
                        </span>
                        <div className="flex items-center gap-2">
                            {logs.prev_page_url ? (
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="h-8 text-xs"
                                >
                                    <Link
                                        href={logs.prev_page_url}
                                        preserveScroll
                                    >
                                        Sebelumnya
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled
                                    className="h-8 text-xs opacity-50"
                                >
                                    Sebelumnya
                                </Button>
                            )}

                            {logs.next_page_url ? (
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="h-8 text-xs"
                                >
                                    <Link
                                        href={logs.next_page_url}
                                        preserveScroll
                                    >
                                        Berikutnya
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled
                                    className="h-8 text-xs opacity-50"
                                >
                                    Berikutnya
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

SuperadminAuditIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Log Audit',
            href: auditIndex(),
        },
    ],
};
