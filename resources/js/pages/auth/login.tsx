import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { business } from '@/routes/register';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Masuk ke Akun" />

            {/* Quick Switcher Tabs */}
            <div className="mb-6 grid grid-cols-2 rounded-control border border-ink/20 bg-sand/30 p-1">
                <span className="flex items-center justify-center rounded-control bg-cream py-2 font-display text-sm font-bold text-ink shadow-xs">
                    Masuk
                </span>
                <Link
                    href={register()}
                    className="flex items-center justify-center rounded-control py-2 font-display text-sm font-semibold text-stone transition-colors hover:text-ink focus-visible:outline-2 focus-visible:outline-cobalt"
                >
                    Daftar Akun
                </Link>
            </div>

            <PasskeyVerify
                label="Masuk dengan Passkey"
                loadingLabel="Memverifikasi Passkey..."
                separator="atau lanjutkan dengan email"
            />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="space-y-4">
                            {/* Email Field */}
                            <div className="space-y-1.5">
                                <label
                                    htmlFor="email"
                                    className="block font-body text-xs font-semibold tracking-wider text-ink uppercase"
                                >
                                    Alamat Email
                                </label>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    autoComplete="email"
                                    placeholder="nama@contoh.com"
                                    aria-invalid={!!errors.email}
                                    className={cn(
                                        'h-12 w-full rounded-control border border-ink/30 bg-cream/50 px-3.5 font-body text-base text-ink transition-colors placeholder:text-stone/70 hover:border-ink/60 focus:border-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt disabled:cursor-not-allowed disabled:opacity-50',
                                        errors.email &&
                                            'border-red-600 focus-visible:outline-red-600',
                                    )}
                                />
                                <InputError
                                    message={errors.email}
                                    className="mt-1"
                                />
                            </div>

                            {/* Password Field */}
                            <div className="space-y-1.5">
                                <div className="flex items-center justify-between">
                                    <label
                                        htmlFor="password"
                                        className="block font-body text-xs font-semibold tracking-wider text-ink uppercase"
                                    >
                                        Password
                                    </label>
                                    {canResetPassword && (
                                        <Link
                                            href={request()}
                                            className="font-body text-xs text-cobalt underline-offset-4 hover:text-cobalt-deep hover:underline focus-visible:outline-2 focus-visible:outline-cobalt"
                                        >
                                            Lupa password?
                                        </Link>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoComplete="current-password"
                                    placeholder="Masukkan password"
                                    aria-invalid={!!errors.password}
                                />
                                <InputError
                                    message={errors.password}
                                    className="mt-1"
                                />
                            </div>

                            {/* Remember Me */}
                            <div className="flex items-center gap-2.5 pt-0.5">
                                <input
                                    type="checkbox"
                                    id="remember"
                                    name="remember"
                                    className="size-4 cursor-pointer rounded-control border border-ink/40 bg-cream accent-cobalt focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt"
                                />
                                <label
                                    htmlFor="remember"
                                    className="cursor-pointer font-body text-xs text-stone select-none hover:text-ink"
                                >
                                    Ingat saya di perangkat ini
                                </label>
                            </div>

                            {/* Submit Button */}
                            <button
                                type="submit"
                                disabled={processing}
                                data-test="login-button"
                                className="flex min-h-12 w-full items-center justify-center gap-2 rounded-control bg-cobalt px-4 font-body text-sm font-semibold text-cream transition-colors hover:bg-cobalt-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt disabled:pointer-events-none disabled:opacity-50 motion-safe:active:scale-[0.96]"
                            >
                                {processing ? (
                                    <>
                                        <Spinner className="size-4" />
                                        <span>Memproses masuk...</span>
                                    </>
                                ) : (
                                    <span>Masuk ke Akun</span>
                                )}
                            </button>
                        </div>

                        {/* Sign up link */}
                        <div className="text-center font-body text-xs text-stone">
                            Belum punya akun?{' '}
                            <Link
                                href={register()}
                                className="font-semibold text-ink underline underline-offset-4 hover:text-cobalt focus-visible:outline-2 focus-visible:outline-cobalt"
                            >
                                Yuk daftar akun baru
                            </Link>
                        </div>

                        {/* Venue Owner Callout */}
                        <div className="rounded-control border border-ink/15 bg-sand/20 p-4 text-center">
                            <p className="font-body text-xs text-stone">
                                Punya lapangan, studio, atau ruang meeting?{' '}
                                <Link
                                    href={business()}
                                    className="font-semibold text-ink underline underline-offset-4 hover:text-cobalt focus-visible:outline-2 focus-visible:outline-cobalt"
                                >
                                    Daftar jadi mitra venue
                                </Link>
                            </p>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mt-4 rounded-control border border-emerald-600/30 bg-emerald-50 p-3 text-center font-body text-xs font-medium text-emerald-800">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Masuk buat Lanjut Booking',
    description:
        'Masukkan email dan password kamu untuk lanjut memesan tempat.',
};
