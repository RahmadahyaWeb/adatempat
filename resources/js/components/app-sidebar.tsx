import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    Compass,
    ExternalLink,
    LayoutDashboard,
    PlusCircle,
    QrCode,
    Store,
    History,
    ShieldCheck,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { scanner as scannerRoute } from '@/routes/gate';
import {
    create as venueCreate,
    index as venuesIndex,
} from '@/routes/owner/venues';
import { index as spacesIndex } from '@/routes/spaces';
import { index as superadminAuditIndex } from '@/routes/superadmin/audit';
import { index as superadminVenuesIndex } from '@/routes/superadmin/venues';
import type { Auth, NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const role = auth?.user?.role;
    const isSuperadmin = role === 'superadmin';
    const isOwner = role === 'owner';
    const isOwnerOrAdmin = isOwner || isSuperadmin;

    const coreNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutDashboard,
        },
    ];
    const superadminNavItems: NavItem[] = isSuperadmin
        ? [
              {
                  title: 'Antrean Kurasi',
                  href: superadminVenuesIndex(),
                  icon: ShieldCheck,
              },
              {
                  title: 'Log Audit',
                  href: superadminAuditIndex(),
                  icon: History,
              },
          ]
        : [];

    const managementNavItems: NavItem[] = isOwnerOrAdmin
        ? [
              {
                  title: isSuperadmin ? 'Semua Venue' : 'Kelola Venue',
                  href: venuesIndex(),
                  icon: Building2,
              },
              {
                  title: 'Tambah Venue',
                  href: venueCreate(),
                  icon: PlusCircle,
              },
              {
                  title: 'Gate Scanner',
                  href: scannerRoute(),
                  icon: QrCode,
              },
          ]
        : [];

    const marketplaceNavItems: NavItem[] = [
        {
            title: 'Katalog Ruang & Tempat',
            href: spacesIndex(),
            icon: Compass,
        },
        {
            title: 'Halaman Depan',
            href: '/',
            icon: Store,
        },
    ];

    const footerNavItems: NavItem[] = isOwner
        ? [
              {
                  title: 'Lihat Marketplace',
                  href: spacesIndex(),
                  icon: ExternalLink,
              },
          ]
        : !isOwnerOrAdmin
          ? [
                {
                    title: 'Daftarkan Venue Kamu',
                    href: '/register/business',
                    icon: Building2,
                },
            ]
          : [];

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            className="border-r border-stone-200/80 bg-stone-50/50 dark:border-neutral-800 dark:bg-neutral-950/40"
        >
            <SidebarHeader className="border-b border-stone-200/60 pb-3 dark:border-neutral-800">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="hover:bg-transparent"
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-2 py-2">
                <NavMain items={coreNavItems} label="Ringkasan" />
                {isSuperadmin && (
                    <NavMain items={superadminNavItems} label="Superadmin" />
                )}

                {isOwnerOrAdmin && (
                    <NavMain
                        items={managementNavItems}
                        label={isSuperadmin ? 'Panel Admin' : 'Kelola Tempat'}
                    />
                )}

                <NavMain items={marketplaceNavItems} label="Marketplace" />
            </SidebarContent>

            <SidebarFooter className="border-t border-stone-200/60 pt-2 dark:border-neutral-800">
                {footerNavItems.length > 0 && (
                    <NavFooter items={footerNavItems} className="mb-1" />
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
