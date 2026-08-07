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
            throw new \RuntimeException('قالب پیش‌فرض SHSMS تنظیم نشده است. مقدار SHSMS_TEXT_TEMPLATE را در env وارد کنید.');
        }

        $this->sendTemplate($recipient, $template, [$message]);
    }

    public function sendTemplate(string $recipient, string $template, array $params = []): void
    {
        if (! config('services.shsms.endpoint') || ! config('services.shsms.token')) {
            throw new \RuntimeException('اتصال SHSMS تنظیم نشده است. مقادیر SHSMS_ENDPOINT و SHSMS_API_TOKEN را در env وارد کنید.');
        }

        $query = [
            'receptor' => $recipient,
            'template' => $template,
        ];

        foreach ($params as $parameter) {
            $query['param'][] = $parameter;
        }

        if (config('services.shsms.sandbox') || config('shsms.sandbox')) {
            Log::info('SHSMS sandbox', $query);
            return;
        }

        Http::withToken(config('services.shsms.token'))
            ->acceptJson()
            ->get(config('services.shsms.endpoint'), $query)
            ->throw();
    }
}
