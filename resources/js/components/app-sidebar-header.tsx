import { Link, usePage } from '@inertiajs/react';
import { Building2, ExternalLink, QrCode, ShieldCheck } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { scanner as scannerRoute } from '@/routes/gate';
import { index as spacesIndex } from '@/routes/spaces';
import type { Auth, BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { auth } = usePage<{ auth?: Auth }>().props;
    const role = auth?.user?.role;
    const isSuperadmin = role === 'superadmin';
    const isOwner = role === 'owner';
    const businessName = (auth?.user as { business_name?: string })
        ?.business_name;

    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-4 border-b border-stone-200/80 bg-background/95 px-4 backdrop-blur-xs transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-6 dark:border-neutral-800">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1 text-stone-600 hover:text-stone-900 dark:text-stone-300 dark:hover:text-white" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className="flex shrink-0 items-center gap-2 sm:gap-3">
                {/* Role Pill Badge */}
                {isSuperadmin && (
                    <Badge
                        variant="secondary"
                        className="hidden items-center gap-1.5 border-cobalt/30 bg-cobalt/10 px-2.5 py-0.5 text-xs font-semibold text-cobalt sm:inline-flex dark:border-cobalt/40 dark:bg-cobalt/20 dark:text-blue-300"
                    >
                        <ShieldCheck className="size-3.5" />
                        <span>Superadmin</span>
                    </Badge>
                )}

                {isOwner && (
                    <Badge
                        variant="secondary"
                        className="hidden max-w-[200px] items-center gap-1.5 truncate border-stone-300 bg-stone-100 px-2.5 py-0.5 text-xs font-medium text-stone-700 sm:inline-flex dark:border-neutral-700 dark:bg-neutral-800 dark:text-stone-300"
                    >
                        <Building2 className="size-3.5 shrink-0 text-cobalt" />
                        <span className="truncate">
                            {businessName || 'Mitra Pemilik'}
                        </span>
                    </Badge>
                )}

                {/* Quick Scanner Shortcut */}
                {(isOwner || isSuperadmin) && (
                    <Button
                        variant="ghost"
                        size="sm"
                        asChild
                        className="h-8 gap-1.5 px-2.5 text-xs font-medium text-stone-600 hover:text-stone-900 dark:text-stone-300 dark:hover:text-white"
                    >
                        <Link href={scannerRoute()} title="Buka Scanner Tiket">
                            <QrCode className="size-3.5 text-stone-500" />
                            <span className="hidden md:inline">Scanner</span>
                        </Link>
                    </Button>
                )}

                {/* Marketplace Link */}
                <Button
                    variant="outline"
                    size="sm"
                    asChild
                    className="h-8 gap-1.5 border-stone-200 px-2.5 text-xs font-medium text-stone-700 hover:bg-stone-100 sm:px-3 dark:border-neutral-700 dark:text-stone-300 dark:hover:bg-neutral-800"
                >
                    <Link href={spacesIndex()} prefetch>
                        <span className="hidden sm:inline">
                            Lihat Marketplace
                        </span>
                        <span className="sm:hidden">Katalog</span>
                        <ExternalLink className="size-3 text-stone-400" />
                    </Link>
                </Button>
            </div>
        </header>
    );
}
