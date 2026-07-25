<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ShsmsService
{
    public function send(string $recipient, string $message): void
    {
        if (! config('services.shsms.endpoint') || ! config('services.shsms.token')) {
            throw new \RuntimeException('اتصال SHSMS تنظیم نشده است.');
        }

        Http::withToken(config('services.shsms.token'))->acceptJson()->post(config('services.shsms.endpoint'), [
            'recipient' => $recipient,
            'message' => $message,
            'sender' => config('services.shsms.sender'),
        ])->throw();
    }
}
