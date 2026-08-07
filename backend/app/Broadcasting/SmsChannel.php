<?php

namespace App\Broadcasting;

use App\Services\ShsmsService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class SmsChannel
{
    public function __construct(private ShsmsService $sms)
    {
    }

    public function send($notifiable, Notification $notification): void
    {
        $data = $notification->toArray($notifiable);

        if (
            empty($data['receptor']) ||
            empty($data['template']) ||
            empty($data['params']) ||
            ! is_array($data['params'])
        ) {
            Log::warning('SMS was not sent because required data is missing.', $data);
            return;
        }

        $this->sms->sendTemplate($data['receptor'], $data['template'], $data['params']);
    }
}
