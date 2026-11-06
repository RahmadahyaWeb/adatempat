import type { ReactNode } from 'react';

import { SiteFooter } from '@/components/public/site-footer';
import { SiteHeader } from '@/components/public/site-header';

export default function PublicLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-screen flex-col bg-cream font-body text-ink antialiased">
            <a
                href="#konten"
                className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-md focus:bg-ink focus:px-4 focus:py-3 focus:text-[15px] focus:text-white"
            >
                Lewati ke konten
            </a>
            <SiteHeader />
            <main id="konten" className="flex-1">
                {children}
            </main>
            <SiteFooter />
        </div>
    );
}
