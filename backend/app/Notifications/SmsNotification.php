<?php

namespace App\Notifications;

use App\Broadcasting\LogChannel;
use App\Broadcasting\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SmsNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ?string $template,
        public array $params = [],
        public ?string $receptor = null
    ) {
    }

    public function via($notifiable): array
    {
        return config('shsms.sandbox')
            ? [LogChannel::class]
            : [SmsChannel::class];
    }

    public function toArray($notifiable): array
    {
        return [
            'template' => $this->template,
            'receptor' => $this->receptor ?? $notifiable->mobile ?? $notifiable->phone ?? null,
            'params' => $this->params,
        ];
    }

    public function toLog($notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
