import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { store } from '@/routes/two-factor/login';

export default function TwoFactorChallenge() {
    const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
    const [code, setCode] = useState<string>('');

    const authConfigContent = useMemo<{
        title: string;
        description: string;
        toggleText: string;
    }>(() => {
        if (showRecoveryInput) {
            return {
                title: 'Kode Pemulihan Darurat',
                description:
                    'Masukkan salah satu kode pemulihan darurat untuk mengakses akun Anda.',
                toggleText: 'masuk menggunakan kode autentikator',
            };
        }

        return {
            title: 'Kode Autentikasi',
            description:
                'Masukkan 6 digit kode dari aplikasi autentikator Anda.',
            toggleText: 'masuk menggunakan kode pemulihan darurat',
        };
    }, [showRecoveryInput]);

    setLayoutProps({
        title: authConfigContent.title,
        description: authConfigContent.description,
    });

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    return (
        <>
            <Head title="Autentikasi Dua Faktor" />

            <div className="space-y-6">
                <Form
                    {...store.form()}
                    className="space-y-4"
                    resetOnError
                    resetOnSuccess={!showRecoveryInput}
                >
                    {({ errors, processing, clearErrors }) => (
                        <>
                            {showRecoveryInput ? (
                                <>
                                    <div className="space-y-1.5">
                                        <Input
                                            name="recovery_code"
                                            type="text"
                                            placeholder="Contoh: abcde-12345"
                                            autoFocus={showRecoveryInput}
                                            className="h-12 w-full rounded-control border border-ink/30 bg-cream/50 px-3.5 font-body text-base text-ink transition-colors placeholder:text-stone/70 hover:border-ink/60 focus:border-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt"
                                            required
                                        />
                                        <InputError
                                            message={errors.recovery_code}
                                        />
                                    </div>
                                </>
                            ) : (
                                <div className="flex flex-col items-center justify-center space-y-3 text-center">
                                    <div className="flex w-full items-center justify-center">
                                        <InputOTP
                                            name="code"
                                            maxLength={OTP_MAX_LENGTH}
                                            value={code}
                                            onChange={(value) => setCode(value)}
                                            disabled={processing}
                                            pattern={REGEXP_ONLY_DIGITS}
                                            autoFocus
                                        >
                                            <InputOTPGroup>
                                                {Array.from(
                                                    { length: OTP_MAX_LENGTH },
                                                    (_, index) => (
                                                        <InputOTPSlot
                                                            key={index}
                                                            index={index}
                                                        />
                                                    ),
                                                )}
                                            </InputOTPGroup>
                                        </InputOTP>
                                    </div>
                                    <InputError message={errors.code} />
                                </div>
                            )}

                            <Button
                                type="submit"
                                className="flex min-h-12 w-full items-center justify-center gap-2 rounded-control bg-cobalt px-4 font-body text-sm font-semibold text-cream transition-colors hover:bg-cobalt-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobalt disabled:pointer-events-none disabled:opacity-50 motion-safe:active:scale-[0.96]"
                                disabled={processing}
                            >
                                {processing
                                    ? 'Memverifikasi...'
                                    : 'Verifikasi dan Lanjutkan'}
                            </Button>

                            <div className="text-center font-body text-xs text-stone">
                                <span>atau Anda dapat </span>
                                <button
                                    type="button"
                                    className="cursor-pointer font-semibold text-ink underline underline-offset-4 hover:text-cobalt focus-visible:outline-2 focus-visible:outline-cobalt"
                                    onClick={() =>
                                        toggleRecoveryMode(clearErrors)
                                    }
                                >
                                    {authConfigContent.toggleText}
                                </button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
