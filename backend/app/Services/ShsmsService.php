<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShsmsService
{
    public function send(string $recipient, string $message): void
    {
        $template = config('shsms.text_template') ?: config('services.shsms.text_template');

        if (! $template) {
            if ($this->isSandbox()) {
                Log::info('SHSMS sandbox text message', [
                    'receptor' => $recipient,
                    'message' => $message,
                ]);

                return;
            }

            throw new \RuntimeException('ارسال پیامک در حال حاضر ممکن نیست.');
        }

        $this->sendTemplate($recipient, $template, [$message]);
    }

    public function sendTemplate(string $recipient, string $template, array $params = []): void
    {
        $query = [
            'receptor' => $recipient,
            'template' => $template,
        ];

        foreach ($params as $parameter) {
            $query['param'][] = $parameter;
        }

        // در محیط آزمایشی هیچ اتصال بیرونی لازم نیست؛ پیام فقط در log ثبت می‌شود.
        if ($this->isSandbox()) {
            Log::info('SHSMS sandbox template message', $query);
            return;
        }

        if (! config('services.shsms.endpoint') || ! config('services.shsms.token')) {
            throw new \RuntimeException('ارسال پیامک در حال حاضر ممکن نیست.');
        }

        Http::withToken(config('services.shsms.token'))
            ->acceptJson()
            ->get(config('services.shsms.endpoint'), $query)
            ->throw();
    }

    private function isSandbox(): bool
    {
        return (bool) config('services.shsms.sandbox') || (bool) config('shsms.sandbox');
    }
}
