<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailSpike extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $sentAt,
        public string $emailSubject = 'AdaTempat email spike',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Ini email uji AdaTempat.</p><p>Waktu: '.e($this->sentAt).'</p>',
        );
    }
}
