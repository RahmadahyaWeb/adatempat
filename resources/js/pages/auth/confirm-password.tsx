import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/components/passkey-verify';

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Konfirmasi Akses Akun" />

            <PasskeyVerify
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                label="Konfirmasi dengan Passkey"
                loadingLabel="Memverifikasi Passkey..."
                separator="atau konfirmasi dengan password"
            />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="space-y-1.5">
                            <Label
                                htmlFor="password"
                                className="block font-body text-xs font-semibold tracking-wider text-ink uppercase"
                            >
                                Password
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder="Masukkan password Anda"
                                autoComplete="current-password"
                                autoFocus
                            />
                            <InputError
                                message={errors.password}
                                className="mt-1"
                            />
                        </div>

                        <div className="pt-2">
                            <Button
                                className="flex min-h-12 w-full items-center justify-center gap-2 rounded-control bg-cobalt px-4 font-body text-sm font-semibold text-cream transition-colors hover:bg-cobalt-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt disabled:pointer-events-none disabled:opacity-50 motion-safe:active:scale-[0.96]"
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing && <Spinner />}
                                {processing
                                    ? 'Mengonfirmasi...'
                                    : 'Konfirmasi Password'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

ConfirmPassword.layout = {
    title: 'Konfirmasi Akses Akun',
    description:
        'Untuk keamanan akun Anda, masukkan password sebelum melanjutkan ke area sensitif.',
};
