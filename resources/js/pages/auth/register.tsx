import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { login } from '@/routes';
import { business, store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title="Daftar Akun" />

            {/* Quick Switcher Tabs */}
            <div className="mb-6 grid grid-cols-2 rounded-control border border-ink/20 bg-sand/30 p-1">
                <Link
                    href={login()}
                    className="flex items-center justify-center rounded-control py-2 font-display text-sm font-semibold text-stone transition-colors hover:text-ink focus-visible:outline-2 focus-visible:outline-cobalt"
                >
                    Masuk
                </Link>
                <span className="flex items-center justify-center rounded-control bg-cream py-2 font-display text-sm font-bold text-ink shadow-xs">
                    Daftar Akun
                </span>
            </div>

            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="space-y-4">
                            {/* Nama Lengkap */}
                            <div className="space-y-1.5">
                                <label
                                    htmlFor="name"
                                    className="block font-body text-xs font-semibold tracking-wider text-ink uppercase"
                                >
                                    Nama Lengkap
                                </label>
                                <input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Contoh: Budi Santoso"
                                    aria-invalid={!!errors.name}
                                    className={cn(
                                        'h-12 w-full rounded-control border border-ink/30 bg-cream/50 px-3.5 font-body text-base text-ink transition-colors placeholder:text-stone/70 hover:border-ink/60 focus:border-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt disabled:cursor-not-allowed disabled:opacity-50',
                                        errors.name &&
                                            'border-red-600 focus-visible:outline-red-600',
                                    )}
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-1"
                                />
                            </div>

                            {/* Alamat Email */}
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
                                    required
                                    autoComplete="email"
                                    name="email"
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

                            {/* Password */}
                            <div className="space-y-1.5">
                                <label
                                    htmlFor="password"
                                    className="block font-body text-xs font-semibold tracking-wider text-ink uppercase"
                                >
                                    Password
                                </label>
                                <PasswordInput
                                    id="password"
                                    required
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Minimal 8 karakter"
                                    passwordrules={passwordRules}
                                    aria-invalid={!!errors.password}
                                />
                                <InputError
                                    message={errors.password}
                                    className="mt-1"
                                />
                            </div>

                            {/* Konfirmasi Password */}
                            <div className="space-y-1.5">
                                <label
                                    htmlFor="password_confirmation"
                                    className="block font-body text-xs font-semibold tracking-wider text-ink uppercase"
                                >
                                    Konfirmasi Password
                                </label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Ulangi password di atas"
                                    passwordrules={passwordRules}
                                    aria-invalid={
                                        !!errors.password_confirmation
                                    }
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                    className="mt-1"
                                />
                            </div>

                            {/* Submit Button */}
                            <button
                                type="submit"
                                disabled={processing}
                                data-test="register-user-button"
                                className="mt-2 flex min-h-12 w-full items-center justify-center gap-2 rounded-control bg-cobalt px-4 font-body text-sm font-semibold text-cream transition-colors hover:bg-cobalt-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt disabled:pointer-events-none disabled:opacity-50 motion-safe:active:scale-[0.96]"
                            >
                                {processing ? (
                                    <>
                                        <Spinner className="size-4" />
                                        <span>Membuat akun...</span>
                                    </>
                                ) : (
                                    <span>Buat Akun</span>
                                )}
                            </button>
                        </div>

                        {/* Sign in link */}
                        <div className="text-center font-body text-xs text-stone">
                            Sudah punya akun?{' '}
                            <Link
                                href={login()}
                                className="font-semibold text-ink underline underline-offset-4 hover:text-cobalt focus-visible:outline-2 focus-visible:outline-cobalt"
                            >
                                Masuk ke akun kamu
                            </Link>
                        </div>

                        {/* Venue Owner Callout */}
                        <div className="rounded-control border border-ink/15 bg-sand/20 p-4 text-center">
                            <p className="font-body text-xs text-stone">
                                Mau daftarin venue atau kelola jadwal ruanganmu?{' '}
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
        </>
    );
}

Register.layout = {
    title: 'Bikin Akun Baru',
    description:
        'Daftar cuma 1 menit buat mulai booking lapangan, studio, atau ruangan favorit kamu.',
};
