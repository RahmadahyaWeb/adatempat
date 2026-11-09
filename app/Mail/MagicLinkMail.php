<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $url,
        public readonly User $user
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Login Link - adatempat',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.magic-link',
        );
    }
}
