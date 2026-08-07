<?php

namespace App\Broadcasting;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class LogChannel
{
    public function send($notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toLog')) {
            throw new \Exception('Method toLog not defined in notification');
        }

        Log::channel('sms')->info('Notification Log:', $notification->toLog($notifiable));
    }
}
