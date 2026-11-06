import { home, login } from '@/routes';
import { business as registerBusiness } from '@/routes/register';
import { index as spacesIndex } from '@/routes/spaces';

export type NavItem = {
    label: string;
    href: string;
    native?: boolean;
};

export const NAV_LINKS: NavItem[] = [
    { label: 'Cari tempat', href: spacesIndex().url },
    { label: 'Kategori', href: `${home.url()}#kategori`, native: true },
    { label: 'Cara kerja', href: `${home.url()}#cara-kerja`, native: true },
    { label: 'Untuk pemilik tempat', href: registerBusiness().url },
];

export const ACCOUNT_LINKS: NavItem[] = [
    { label: 'Masuk', href: login().url },
    { label: 'Daftarkan venue kamu', href: registerBusiness().url },
];
