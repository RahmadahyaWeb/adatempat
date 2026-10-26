<?php

namespace App\Contracts\Notifications;

use App\Models\Notification;

interface NotificationDeliveryContract
{
    /**
     * Send one non-database notification.
     *
     * @return array{provider:string,provider_message_id:?string,response_metadata:array}
     */
    public function send(Notification $notification): array;
}
