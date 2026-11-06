import type { ReactNode } from 'react';
import { SiteFooter } from '@/components/public/site-footer';
import { SiteHeader } from '@/components/public/site-header';

export default function PublicLayout({ children }: { children: ReactNode }) {
    return (
        <div
            className="min-h-dvh bg-cream font-body text-ink selection:bg-cobalt selection:text-cream"
            style={{ colorScheme: 'light' }}
        >
            <a
                href="#konten"
                className="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:inline-flex focus:min-h-11 focus:items-center focus:bg-ink focus:px-4 focus:font-body focus:text-sm focus:text-cream focus:outline-2 focus:outline-offset-2 focus:outline-cobalt"
            >
                Langsung ke konten
            </a>

            <SiteHeader />

            <main id="konten" tabIndex={-1}>
                {children}
            </main>

            <SiteFooter />
        </div>
    );
}
