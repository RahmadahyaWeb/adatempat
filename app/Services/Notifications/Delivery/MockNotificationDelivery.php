<?php

namespace App\Services\Notifications\Delivery;

use App\Contracts\Notifications\NotificationDeliveryContract;
use App\Models\Notification;
use Illuminate\Support\Str;
use RuntimeException;

class MockNotificationDelivery implements NotificationDeliveryContract
{
    public function send(Notification $notification): array
    {
        if (app()->environment('production')) {
            throw new RuntimeException('No production email or WhatsApp delivery provider is configured.');
        }

        if ($notification->channel === Notification::CHANNEL_EMAIL) {
            $recipient = $notification->recipient;

            if (! $recipient || ! filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Notification recipient email is unavailable.');
            }
        } elseif ($notification->channel === Notification::CHANNEL_WHATSAPP) {
            $recipient = $notification->recipient;

            if (! $recipient || ! filled($recipient->phone)) {
                throw new RuntimeException('Notification recipient phone is unavailable.');
            }
        } else {
            throw new RuntimeException('The mock delivery adapter only handles email and WhatsApp channels.');
        }

        return [
            'provider' => 'mock',
            'provider_message_id' => 'MOCK-NOTIF-' . Str::upper(Str::random(16)),
            'response_metadata' => ['accepted' => true, 'mode' => 'simulation'],
        ];
    }
}
