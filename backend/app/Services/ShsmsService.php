<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
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

        if (! $this->hasCredentials()) {
            throw new \RuntimeException('ارسال پیامک در حال حاضر ممکن نیست.');
        }

        Http::withToken($this->apiToken())
            ->acceptJson()
            ->get($this->endpoint(), $query)
            ->throw();
    }

    /** Each clinic must use the SHSMS account saved in its own tenant settings. */
    public function hasCredentials(): bool
    {
        return filled($this->endpoint()) && filled($this->apiToken());
    }

    private function endpoint(): ?string
    {
        return config('services.shsms.endpoint') ?: config('shsms.endpoint');
    }

    private function apiToken(): ?string
    {
        $encryptedToken = AppSetting::getByKey('shsms_api_token');

        if (filled($encryptedToken)) {
            try {
                return Crypt::decryptString($encryptedToken);
            } catch (DecryptException) {
                Log::warning('The clinic SHSMS token could not be decrypted.');
            }
        }

        return null;
    }

    private function isSandbox(): bool
    {
        return (bool) config('services.shsms.sandbox') || (bool) config('shsms.sandbox');
    }
}
