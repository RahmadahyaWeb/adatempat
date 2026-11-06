<?php

namespace App\Console\Commands;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Bookings\BookingService;
use Illuminate\Console\Command;

class FakePaymentCommand extends Command
{
    protected $signature = 'booking:fake-pay {code : Booking code ADT-BK-...}';

    protected $description = 'Simulate payment success for a pending booking in non-production environments.';

    public function handle(
        BookingService $bookingService,
        PaymentGatewayInterface $paymentGateway,
    ): int {
        if (app()->isProduction()) {
            $this->error('Simulasi pembayaran hanya tersedia di lingkungan non-produksi.');

            return self::FAILURE;
        }

        /** @var string $code */
        $code = (string) $this->argument('code');

        /** @var Booking|null $booking */
        $booking = Booking::query()->where('code', $code)->first();

        if (! $booking) {
            $this->error("Booking dengan kode '{$code}' tidak ditemukan.");

            return self::FAILURE;
        }

        if ($booking->status === BookingStatus::Confirmed) {
            $this->warn("Booking '{$code}' sudah terkonfirmasi sebelumnya.");

            return self::SUCCESS;
        }

        if ($booking->status !== BookingStatus::PendingPayment) {
            $this->error("Booking '{$code}' tidak dapat dibayar karena berstatus: {$booking->status->value}.");

            return self::FAILURE;
        }

        $paymentGateway->simulatePaymentSuccess($booking->code);
        $confirmed = $bookingService->confirmBooking($booking);

        if (! $confirmed) {
            $this->error("Gagal mengonfirmasi booking '{$code}'. Status: {$booking->status->value}.");

            return self::FAILURE;
        }

        $this->info("Booking '{$code}' berhasil dibayar dan dikonfirmasi.");

        return self::SUCCESS;
    }
}
