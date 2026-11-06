// Components
import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Lupa Password" />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <div className="space-y-6">
                <Form {...email.form()}>
                    {({ processing, errors }) => (
                        <>
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
                                    autoComplete="off"
                                    autoFocus
                                    placeholder="nama@contoh.com"
                                    className="h-12 w-full rounded-control border border-ink/30 bg-cream/50 px-3.5 font-body text-base text-ink transition-colors placeholder:text-stone/70 hover:border-ink/60 focus:border-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="pt-2">
                                <Button
                                    className="flex min-h-12 w-full items-center justify-center gap-2 rounded-control bg-cobalt px-4 font-body text-sm font-semibold text-cream transition-colors hover:bg-cobalt-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt disabled:pointer-events-none disabled:opacity-50 motion-safe:active:scale-[0.96]"
                                    disabled={processing}
                                    data-test="email-password-reset-link-button"
                                >
                                    {processing && (
                                        <LoaderCircle className="h-4 w-4 animate-spin" />
                                    )}
                                    {processing
                                        ? 'Mengirim tautan...'
                                        : 'Kirim Tautan Reset Password'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <div className="text-center font-body text-xs text-stone">
                    <span>Sudah ingat password? </span>
                    <TextLink
                        href={login()}
                        className="font-semibold text-ink underline underline-offset-4 hover:text-cobalt focus-visible:outline-2 focus-visible:outline-cobalt"
                    >
                        Kembali ke halaman masuk
                    </TextLink>
                </div>
            </div>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Lupa Password',
    description:
        'Masukkan alamat email Anda untuk menerima tautan pemulihan password.',
};
