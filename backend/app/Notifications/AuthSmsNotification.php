<?php

namespace App\Notifications;

use App\Broadcasting\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AuthSmsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $code,
        public string $device = 'android'
    ) {
    }

    public function via($notifiable): array
    {
        return [SmsChannel::class];
    }

    public function toArray($notifiable): array
    {
        return [
            'template' => $this->device === 'android'
                ? config('shsms.templates.login_android')
                : config('shsms.templates.login_webapp'),
            'receptor' => $notifiable->mobile ?? $notifiable->phone ?? null,
            'params' => [$this->code],
        ];
    }
}
