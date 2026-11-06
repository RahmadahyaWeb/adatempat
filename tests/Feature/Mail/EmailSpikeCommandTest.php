<?php

namespace Tests\Feature\Mail;

use App\Mail\EmailSpike;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailSpikeCommandTest extends TestCase
{
    public function test_sends_email_through_the_configured_mailer(): void
    {
        Mail::fake();

        $this->artisan('mail:spike', ['to' => 'customer@example.com'])
            ->assertSuccessful();

        Mail::assertSent(EmailSpike::class, fn (EmailSpike $mail): bool => $mail->hasTo('customer@example.com'));
        Mail::assertSentCount(1);
    }

    public function test_rejects_invalid_recipient(): void
    {
        Mail::fake();

        $this->artisan('mail:spike', ['to' => 'not-an-email'])
            ->assertExitCode(1);

        Mail::assertSentCount(0);
    }
}
