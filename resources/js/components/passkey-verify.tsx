import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { KeyRound } from 'lucide-react';
import InputError from '@/components/input-error';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    label?: string;
    loadingLabel?: string;
    separator?: string;
};

export default function PasskeyVerify({
    routes,
    label,
    loadingLabel,
    separator,
}: Props = {}) {
    const { verify, isLoading, error, isSupported } = usePasskeyVerify({
        ...(routes && {
            routes: {
                options: routes.options.url,
                submit: routes.submit.url,
            },
        }),
        onSuccess: (response) => {
            router.visit(response.redirect ?? '/dashboard');
        },
    });

    if (!isSupported) {
        return null;
    }

    return (
        <div className="mb-6">
            <button
                type="button"
                onClick={verify}
                disabled={isLoading}
                className={cn(
                    'flex min-h-12 w-full items-center justify-center gap-2.5 rounded-control border border-ink/40 bg-cream/50 px-4 font-body text-sm font-medium text-ink transition-colors hover:border-ink hover:bg-ink hover:text-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt disabled:pointer-events-none disabled:opacity-50 motion-safe:active:scale-[0.96]',
                )}
            >
                {isLoading ? (
                    <Spinner className="size-4" />
                ) : (
                    <KeyRound className="size-4 text-cobalt group-hover:text-cream" />
                )}
                <span>
                    {isLoading
                        ? (loadingLabel ?? 'Memverifikasi Passkey...')
                        : (label ?? 'Masuk dengan Passkey')}
                </span>
            </button>
            {error && (
                <InputError message={error} className="mt-2 text-center" />
            )}

            <div className="relative my-6">
                <div className="absolute inset-0 flex items-center">
                    <div className="w-full border-t border-ink/15" />
                </div>
                <div className="relative flex justify-center text-xs">
                    <span className="bg-cream/90 px-3 font-data text-xs tracking-wider text-stone uppercase">
                        {separator ?? 'atau gunakan email'}
                    </span>
                </div>
            </div>
        </div>
    );
}
