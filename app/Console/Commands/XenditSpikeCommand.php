<?php

namespace App\Console\Commands;

use App\Services\Xendit\XenditClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class XenditSpikeCommand extends Command
{
    protected $signature = 'xendit:spike
        {--amount=10000 : Amount in IDR}
        {--sub-account= : XenPlatform sub-account business ID}
        {--create-account= : Create a test sub-account with this email}
        {--name=AdaTempat Venue Test : Sub-account name}
        {--country=ID : Sub-account country of incorporation}
        {--simulate : Simulate payment after creating the QRIS}
        {--refund= : Refund this payment_id instead of creating QRIS}';

    protected $description = 'Probe Xendit sandbox: QRIS payment request, simulate, refund, sub-account.';

    public function handle(XenditClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->error('XENDIT_SECRET_KEY belum diisi di .env.');
            $this->line('Isi XENDIT_SECRET_KEY dan XENDIT_CALLBACK_TOKEN, lalu jalankan ulang.');

            return self::FAILURE;
        }

        $subAccountId = $this->option('sub-account') ?: null;

        if ($email = $this->option('create-account')) {
            $account = $client->createTestSubAccount(
                (string) $this->option('name'),
                (string) $email,
                (string) $this->option('country'),
            );

            $subAccountId = $account['id'] ?? $subAccountId;
            $this->info('Sub-account: '.($account['id'] ?? '-').' status='.($account['status'] ?? '-'));
        }

        if ($paymentId = $this->option('refund')) {
            $refund = $client->createRefund(
                (string) $paymentId,
                'ADT-REFUND-'.now()->format('YmdHis'),
                (int) $this->option('amount'),
                $subAccountId,
            );

            $this->info('Refund: '.json_encode($refund, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $referenceId = 'ADT-SPIKE-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2)));

        $paymentRequest = $client->createQris(
            $referenceId,
            (int) $this->option('amount'),
            CarbonImmutable::now()->addMinutes(10),
            'AdaTempat spike',
            $subAccountId,
        );

        $this->info('reference_id: '.$referenceId);
        $this->info('payment_request_id: '.($paymentRequest['payment_request_id'] ?? '-'));
        $this->info('status: '.($paymentRequest['status'] ?? '-'));

        foreach ($paymentRequest['actions'] ?? [] as $action) {
            if (($action['descriptor'] ?? null) === 'QR_STRING') {
                $this->line('QR: '.($action['value'] ?? '-'));
            }
        }

        if ($this->option('simulate')) {
            $paymentRequestId = (string) ($paymentRequest['payment_request_id'] ?? '');

            if ($paymentRequestId === '') {
                $this->error('payment_request_id tidak ada; tidak bisa simulate.');

                return self::FAILURE;
            }

            $client->simulatePayment($paymentRequestId, (int) $this->option('amount'));
            sleep(2);

            $status = $client->getPaymentRequest($paymentRequestId);
            $this->info('after simulate: '.($status['status'] ?? '-'));
        }

        return self::SUCCESS;
    }
}
