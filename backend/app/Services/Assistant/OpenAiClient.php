<?php

namespace App\Services\Assistant;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class OpenAiClient
{
    public function configured(): bool
    {
        return filled(config('assistant.api_key'));
    }

    public function respond(array $body): array
    {
        $this->ensureConfigured();
        try {
            $response = Http::withToken((string) config('assistant.api_key'))
                ->acceptJson()
                ->timeout(45)
                ->connectTimeout(8)
                ->post(rtrim(config('assistant.base_url'), '/').'/responses', $body);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('ارتباط با سرویس هوش مصنوعی برقرار نشد.', 0, $exception);
        }
        $this->assertSuccess($response->status(), $response->json());

        return $response->json();
    }

    public function transcribe(UploadedFile $file): string
    {
        $this->ensureConfigured();
        try {
            $stream = fopen($file->getRealPath(), 'rb');
            if ($stream === false) throw new RuntimeException('فایل صوتی قابل خواندن نیست.');
            try {
                $response = Http::withToken((string) config('assistant.api_key'))
                    ->acceptJson()
                    ->timeout(90)
                    ->connectTimeout(8)
                    ->attach('file', $stream, 'clinic-voice.'.$file->extension(), [
                        'Content-Type' => $file->getMimeType() ?: 'application/octet-stream',
                    ])
                    ->post(rtrim(config('assistant.base_url'), '/').'/audio/transcriptions', [
                        'model' => config('assistant.transcription_model'),
                        'prompt' => 'گفت‌وگوی فارسی درباره نوبت‌ها، پزشکان، پیگیری‌ها و گزارش‌های یک کلینیک.',
                    ]);
            } finally {
                fclose($stream);
            }
        } catch (ConnectionException $exception) {
            throw new RuntimeException('رونویسی صدا به‌دلیل قطع ارتباط انجام نشد.', 0, $exception);
        }
        $this->assertSuccess($response->status(), $response->json());
        $text = trim((string) $response->json('text', ''));
        if ($text === '') throw new RuntimeException('متنی از پیام صوتی تشخیص داده نشد.');

        return $text;
    }

    private function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('کلید OpenAI روی سرور تنظیم نشده است.');
        }
    }

    private function assertSuccess(int $status, mixed $payload): void
    {
        if ($status >= 200 && $status < 300) return;
        $code = (string) data_get($payload, 'error.code', '');
        $type = (string) data_get($payload, 'error.type', '');
        $message = match (true) {
            $status === 401 => 'کلید OpenAI معتبر نیست.',
            $type === 'insufficient_quota' || in_array($code, ['insufficient_quota', 'credit_balance_exhausted'], true) => 'اعتبار حساب OpenAI کافی نیست.',
            $status === 429 => 'سقف درخواست سرویس هوش مصنوعی پر شده است. کمی بعد دوباره تلاش کنید.',
            $status >= 500 => 'سرویس هوش مصنوعی موقتاً در دسترس نیست.',
            default => 'سرویس هوش مصنوعی درخواست را نپذیرفت.',
        };
        throw new RuntimeException($message);
    }
}
