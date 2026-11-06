// Components
import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Verifikasi Alamat Email" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 rounded-control border border-emerald-600/30 bg-emerald-50 p-3 text-center font-body text-xs font-medium text-emerald-800">
                    Tautan verifikasi baru telah dikirimkan ke alamat email yang
                    Anda daftarkan.
                </div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button
                            disabled={processing}
                            className="flex min-h-12 w-full items-center justify-center gap-2 rounded-control bg-cobalt px-4 font-body text-sm font-semibold text-cream transition-colors hover:bg-cobalt-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt disabled:pointer-events-none disabled:opacity-50 motion-safe:active:scale-[0.96]"
                        >
                            {processing && <Spinner />}
                            {processing
                                ? 'Mengirim ulang...'
                                : 'Kirim Ulang Email Verifikasi'}
                        </Button>

                        <div className="text-center font-body text-xs text-stone">
                            <TextLink
                                href={logout()}
                                className="font-semibold text-ink underline underline-offset-4 hover:text-cobalt focus-visible:outline-2 focus-visible:outline-cobalt"
                            >
                                Keluar dari akun
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Verifikasi Alamat Email',
    description:
        'Buka tautan verifikasi yang telah kami kirimkan ke email Anda untuk mengaktifkan akun sepenuhnya.',
};
