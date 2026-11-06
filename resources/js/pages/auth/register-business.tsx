import { Form, Head, Link } from '@inertiajs/react';
import { Building2, User } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { login, register } from '@/routes';
import { store } from '@/routes/register/business';

type Props = {
    passwordRules: string;
};

export default function RegisterBusiness({ passwordRules }: Props) {
    return (
        <>
            <Head title="Daftar Pemilik Venue" />

            {/* Role Header Banner */}
            <div className="mb-6 flex items-center justify-between rounded-control border border-cobalt/30 bg-cobalt/10 px-3.5 py-2.5">
                <div className="flex items-center gap-2">
                    <Building2 className="size-4 text-cobalt" />
                    <span className="font-display text-xs font-bold tracking-wider text-cobalt uppercase">
                        Portal Mitra Venue
                    </span>
                </div>
                <Link
                    href={register()}
                    className="font-body text-xs text-stone underline-offset-4 hover:text-ink hover:underline focus-visible:outline-2 focus-visible:outline-cobalt"
                >
                    Mau booking lapangan?
                </Link>
            </div>

            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        {/* Section 1: Data Pemilik */}
                        <div className="space-y-4">
                            <div className="flex items-center gap-2 border-b border-ink/10 pb-2">
                                <User className="size-3.5 text-stone" />
                                <h2 className="font-display text-xs font-bold tracking-wider text-ink uppercase">
                                    1. Informasi Pemilik
                                </h2>
                            </div>

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
                                    placeholder="Contoh: Hendra Setiawan"
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
                                    Alamat Email Bisnis
                                </label>
                                <input
                                    id="email"
                                    type="email"
                                    required
                                    autoComplete="email"
                                    name="email"
                                    placeholder="pemilik@venueanda.com"
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
                                    placeholder="Ulangi password"
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
                        </div>

                        {/* Section 2: Data Venue/Bisnis */}
                        <div className="space-y-4">
                            <div className="flex items-center gap-2 border-b border-ink/10 pb-2">
                                <Building2 className="size-3.5 text-stone" />
                                <h2 className="font-display text-xs font-bold tracking-wider text-ink uppercase">
                                    2. Profil Bisnis Venue
                                </h2>
                            </div>

                            {/* Nama Bisnis */}
                            <div className="space-y-1.5">
                                <label
                                    htmlFor="business_name"
                                    className="block font-body text-xs font-semibold tracking-wider text-ink uppercase"
                                >
                                    Nama Venue / Bisnis
                                </label>
                                <input
                                    id="business_name"
                                    type="text"
                                    required
                                    autoComplete="organization"
                                    name="business_name"
                                    placeholder="Contoh: Arena Futsal Sudirman"
                                    aria-invalid={!!errors.business_name}
                                    className={cn(
                                        'h-12 w-full rounded-control border border-ink/30 bg-cream/50 px-3.5 font-body text-base text-ink transition-colors placeholder:text-stone/70 hover:border-ink/60 focus:border-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt disabled:cursor-not-allowed disabled:opacity-50',
                                        errors.business_name &&
                                            'border-red-600 focus-visible:outline-red-600',
                                    )}
                                />
                                <InputError
                                    message={errors.business_name}
                                    className="mt-1"
                                />
                            </div>

                            {/* Nama Legal */}
                            <div className="space-y-1.5">
                                <label
                                    htmlFor="legal_name"
                                    className="block font-body text-xs font-semibold tracking-wider text-ink uppercase"
                                >
                                    Nama Badan Usaha Legal{' '}
                                    <span className="font-normal text-stone">
                                        (Opsional)
                                    </span>
                                </label>
                                <input
                                    id="legal_name"
                                    type="text"
                                    autoComplete="off"
                                    name="legal_name"
                                    placeholder="Contoh: PT Arena Sudirman Mandiri"
                                    aria-invalid={!!errors.legal_name}
                                    className={cn(
                                        'h-12 w-full rounded-control border border-ink/30 bg-cream/50 px-3.5 font-body text-base text-ink transition-colors placeholder:text-stone/70 hover:border-ink/60 focus:border-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt disabled:cursor-not-allowed disabled:opacity-50',
                                        errors.legal_name &&
                                            'border-red-600 focus-visible:outline-red-600',
                                    )}
                                />
                                <InputError
                                    message={errors.legal_name}
                                    className="mt-1"
                                />
                            </div>

                            {/* Submit Button */}
                            <button
                                type="submit"
                                disabled={processing}
                                data-test="register-business-button"
                                className="mt-2 flex min-h-12 w-full items-center justify-center gap-2 rounded-control bg-cobalt px-4 font-body text-sm font-semibold text-cream transition-colors hover:bg-cobalt-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt disabled:pointer-events-none disabled:opacity-50 motion-safe:active:scale-[0.96]"
                            >
                                {processing ? (
                                    <>
                                        <Spinner className="size-4" />
                                        <span>Mendaftarkan bisnis...</span>
                                    </>
                                ) : (
                                    <span>Daftarkan Bisnis Venue</span>
                                )}
                            </button>
                        </div>

                        {/* Footers */}
                        <div className="space-y-2 text-center font-body text-xs text-stone">
                            <div>
                                Sudah punya akun pemilik?{' '}
                                <Link
                                    href={login()}
                                    className="font-semibold text-ink underline underline-offset-4 hover:text-cobalt focus-visible:outline-2 focus-visible:outline-cobalt"
                                >
                                    Masuk ke akun kamu
                                </Link>
                            </div>
                            <div>
                                Hanya ingin booking tempat?{' '}
                                <Link
                                    href={register()}
                                    className="font-semibold text-ink underline underline-offset-4 hover:text-cobalt focus-visible:outline-2 focus-visible:outline-cobalt"
                                >
                                    Beralih ke akun pemesan
                                </Link>
                            </div>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

RegisterBusiness.layout = {
    title: 'Daftar Jadi Mitra Venue',
    description:
        'Kelola jadwal tempat dan ruanganmu, terima booking online otomatis, dan pantau pemasukan sewa kamu.',
};
