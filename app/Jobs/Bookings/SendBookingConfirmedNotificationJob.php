<?php

namespace App\Jobs\Bookings;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\NotificationLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendBookingConfirmedNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public Booking $booking,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        $this->booking->loadMissing(['venue', 'space', 'slots', 'addOns']);

        /** @var NotificationLog $log */
        $log = NotificationLog::create([
            'booking_id' => $this->booking->id,
            'channel' => NotificationChannel::Email,
            'recipient' => $this->booking->customer_email,
            'template' => 'booking_confirmed',
            'status' => NotificationStatus::Queued,
            'retry_count' => max(0, $this->attempts() - 1),
        ]);

        try {
            $signedTicketUrl = $this->booking->getSignedTicketUrl();

            Mail::to($this->booking->customer_email)->send(
                new BookingConfirmedMail($this->booking, $signedTicketUrl)
            );

            $log->update([
                'status' => NotificationStatus::Sent,
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            $log->update([
                'status' => NotificationStatus::Failed,
                'error_message' => $e->getMessage(),
                'retry_count' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        NotificationLog::query()
            ->where('booking_id', $this->booking->id)
            ->where('template', 'booking_confirmed')
            ->latest('id')
            ->first()?->update([
                'status' => NotificationStatus::Failed,
                'error_message' => $exception?->getMessage() ?? 'Gagal mengirim email konfirmasi pemesanan setelah percobaan maksimal.',
                'retry_count' => $this->tries,
            ]);
    }
}
