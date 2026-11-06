<?php

namespace App\Console\Commands;

use App\Mail\EmailSpike;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EmailSpikeCommand extends Command
{
    protected $signature = 'mail:spike
        {to : Recipient email address}
        {--subject=AdaTempat email spike : Email subject}';

    protected $description = 'Send a transactional email through the configured mailer.';

    public function handle(): int
    {
        $to = (string) $this->argument('to');

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Alamat email tidak valid.');

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');

        Mail::to($to)->send(new EmailSpike(
            now()->toIso8601String(),
            (string) $this->option('subject'),
        ));

        $this->info("Email dikirim via [{$mailer}] ke {$to}.");

        if ($mailer === 'log') {
            $this->warn('Mailer log: cek storage/logs/laravel.log (email tidak keluar dari server).');
        }

        return self::SUCCESS;
    }
}
