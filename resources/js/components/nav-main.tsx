import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

type NavMainProps = {
    items: NavItem[];
    label?: string;
};

export function NavMain({ items, label = 'Menu' }: NavMainProps) {
    const { isCurrentUrl } = useCurrentUrl();

    if (!items.length) {
        return null;
    }

    return (
        <SidebarGroup className="px-2 py-1.5">
            {label && (
                <SidebarGroupLabel className="text-[11px] font-semibold tracking-wider text-stone-500 uppercase dark:text-stone-400">
                    {label}
                </SidebarGroupLabel>
            )}
            <SidebarMenu className="gap-1">
                {items.map((item) => {
                    const active = isCurrentUrl(item.href);

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={active}
                                tooltip={{ children: item.title }}
                                className={`rounded-md transition-colors ${
                                    active
                                        ? 'bg-cobalt/10 font-semibold text-cobalt dark:bg-cobalt/25 dark:text-blue-300'
                                        : 'text-stone-700 hover:bg-stone-100 hover:text-stone-900 dark:text-stone-300 dark:hover:bg-neutral-800 dark:hover:text-white'
                                }`}
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && (
                                        <item.icon
                                            className={`size-4.5 shrink-0 ${
                                                active
                                                    ? 'text-cobalt dark:text-blue-400'
                                                    : 'text-stone-500 dark:text-stone-400'
                                            }`}
                                        />
                                    )}
                                    <span className="truncate">
                                        {item.title}
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
