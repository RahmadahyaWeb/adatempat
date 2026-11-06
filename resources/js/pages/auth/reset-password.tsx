import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    return (
        <>
            <Head title="Reset Password" />

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="space-y-1.5">
                            <Label
                                htmlFor="email"
                                className="block font-body text-xs font-semibold tracking-wider text-ink uppercase"
                            >
                                Alamat Email
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                value={email}
                                className="h-12 w-full rounded-control border border-ink/30 bg-sand/30 px-3.5 font-body text-base text-ink"
                                readOnly
                            />
                            <InputError
                                message={errors.email}
                                className="mt-1"
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label
                                htmlFor="password"
                                className="block font-body text-xs font-semibold tracking-wider text-ink uppercase"
                            >
                                Password Baru
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                autoFocus
                                placeholder="Minimal 8 karakter"
                                passwordrules={passwordRules}
                            />
                            <InputError
                                message={errors.password}
                                className="mt-1"
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label
                                htmlFor="password_confirmation"
                                className="block font-body text-xs font-semibold tracking-wider text-ink uppercase"
                            >
                                Konfirmasi Password Baru
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                placeholder="Ulangi password baru di atas"
                                passwordrules={passwordRules}
                            />
                            <InputError
                                message={errors.password_confirmation}
                                className="mt-1"
                            />
                        </div>

                        <Button
                            type="submit"
                            className="mt-2 flex min-h-12 w-full items-center justify-center gap-2 rounded-control bg-cobalt px-4 font-body text-sm font-semibold text-cream transition-colors hover:bg-cobalt-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt disabled:pointer-events-none disabled:opacity-50 motion-safe:active:scale-[0.96]"
                            disabled={processing}
                            data-test="reset-password-button"
                        >
                            {processing && <Spinner />}
                            {processing
                                ? 'Menyimpan password...'
                                : 'Simpan Password Baru'}
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

ResetPassword.layout = {
    title: 'Reset Password',
    description: 'Buat password baru untuk akun Anda.',
};
