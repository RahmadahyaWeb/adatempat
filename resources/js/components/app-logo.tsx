import { usePage } from '@inertiajs/react';
import { Building2, ShieldCheck } from 'lucide-react';
import type { Auth } from '@/types';

export default function AppLogo() {
    const { auth } = usePage<{ auth?: Auth }>().props;
    const role = auth?.user?.role;
    const businessName = (auth?.user as { business_name?: string })
        ?.business_name;

    const roleLabel =
        role === 'superadmin'
            ? 'Superadmin'
            : role === 'owner'
              ? businessName
                  ? businessName
                  : 'Portal Pemilik'
              : 'Akun Pengguna';

    return (
        <div className="flex items-center gap-2.5 overflow-hidden">
            <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-cobalt text-white shadow-xs">
                {role === 'superadmin' ? (
                    <ShieldCheck className="size-5 stroke-[2.2]" />
                ) : (
                    <Building2 className="size-5 stroke-[2.2]" />
                )}
            </div>
            <div className="flex min-w-0 flex-1 flex-col text-left">
                <span className="truncate font-display text-base font-extrabold tracking-[-0.03em] text-foreground">
                    AdaTempat
                </span>
                <span className="truncate text-[11px] font-medium tracking-wide text-stone-500 uppercase dark:text-stone-400">
                    {roleLabel}
                </span>
            </div>
        </div>
    );
}
