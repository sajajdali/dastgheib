<?php

namespace App\Services\Assistant\Billing;

use App\Services\Assistant\OpenAiClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * همهٔ فراخوانی‌های پولی OpenAI از اینجا می‌گذرند: رزرو سقف هزینه، فراخوانی،
 * و ثبت usage واقعی با کسر از کیف پول. خطای OpenAI (بدون پاسخ موفق) هزینه‌ای ندارد و فقط رزرو آزاد می‌شود.
 */
final class AiMeter
{
    private const DEFAULT_MAX_OUTPUT = 1600;

    public function __construct(
        private readonly OpenAiClient $client,
        private readonly AiPriceBook $prices,
        private readonly AiWallet $wallet,
        private readonly AiBillingSettings $settings,
    ) {}

    public function respond(AiUsageContext $context, array $body): array
    {
        $model = (string) $body['model'];
        $price = $this->prices->require($model);
        $body['max_output_tokens'] = (int) ($body['max_output_tokens'] ?? self::DEFAULT_MAX_OUTPUT);
        $multiplier = $this->settings->multiplierFor($context->tenantId);
        // هر توکن دست‌کم یک بایت متن است؛ پس طول بایتی بدنه سقف قطعی توکن ورودی است.
        $inputBound = strlen((string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) + 256;
        $hold = $this->prices->withMarkup($this->prices->chatCost($price, $inputBound, 0, $body['max_output_tokens']), $multiplier);
        $holdId = $this->wallet->reserve($context->tenantId, $hold);
        try {
            $response = $this->client->respond($body);
        } catch (Throwable $exception) {
            $this->wallet->release($holdId);
            throw $exception;
        }

        $usage = $response['usage'] ?? null;
        $estimated = ! is_array($usage);
        $input = $estimated ? $inputBound : (int) ($usage['input_tokens'] ?? 0);
        $cached = $estimated ? 0 : (int) data_get($usage, 'input_tokens_details.cached_tokens', 0);
        $output = $estimated ? $body['max_output_tokens'] : (int) ($usage['output_tokens'] ?? 0);
        $cost = $this->prices->chatCost($price, $input, $cached, $output);
        $hasToolCall = collect((array) ($response['output'] ?? []))->contains(fn ($item) => ($item['type'] ?? '') === 'function_call');

        $this->wallet->settle($holdId, [
            ...$this->base($context, $model, $hasToolCall ? 'tool_round' : $context->type),
            'response_id' => isset($response['id']) ? mb_substr((string) $response['id'], 0, 120) : null,
            'input_tokens' => $input,
            'cached_input_tokens' => $cached,
            'output_tokens' => $output,
            'reasoning_tokens' => $estimated ? 0 : (int) data_get($usage, 'output_tokens_details.reasoning_tokens', 0),
            'price_id' => $price->id,
            'markup_multiplier' => $multiplier,
            'cost_micros' => $cost,
            'charge_micros' => $this->prices->withMarkup($cost, $multiplier),
            'estimated' => $estimated,
        ]);
        $context->round++;

        return $response;
    }

    public function transcribe(AiUsageContext $context, UploadedFile $file): string
    {
        $model = (string) config('assistant.transcription_model');
        $price = $this->prices->require($model);
        $multiplier = $this->settings->multiplierFor($context->tenantId);
        $bound = $this->transcriptionBound($file);
        $hold = $this->prices->withMarkup(max(
            $this->prices->transcriptionCost($price, $bound),
            $this->prices->transcriptionCost($price, [...$bound, 'audio_tokens' => 0, 'text_tokens' => 0, 'output_tokens' => 0]),
        ), $multiplier);
        $holdId = $this->wallet->reserve($context->tenantId, $hold);
        try {
            $response = $this->client->transcribeRaw($file);
        } catch (Throwable $exception) {
            $this->wallet->release($holdId);
            throw $exception;
        }

        $usage = $response['usage'] ?? null;
        $measured = [
            'audio_tokens' => (int) data_get($usage, 'input_token_details.audio_tokens', data_get($usage, 'input_tokens_details.audio_tokens', 0)),
            'text_tokens' => (int) data_get($usage, 'input_token_details.text_tokens', data_get($usage, 'input_tokens_details.text_tokens', 0)),
            'output_tokens' => (int) data_get($usage, 'output_tokens', 0),
            'seconds' => is_numeric(data_get($usage, 'seconds')) ? (float) data_get($usage, 'seconds') : null,
        ];
        // اگر فقط input_tokens کل آمده باشد، همه را صوتی حساب می‌کنیم (گران‌تر و امن‌تر).
        if ($measured['audio_tokens'] + $measured['text_tokens'] === 0 && (int) data_get($usage, 'input_tokens', 0) > 0) {
            $measured['audio_tokens'] = (int) data_get($usage, 'input_tokens');
        }
        $estimated = $measured['audio_tokens'] + $measured['text_tokens'] + $measured['output_tokens'] === 0 && $measured['seconds'] === null;
        if ($estimated) $measured = $bound;
        // نرخ فقط دقیقه‌ای است ولی پاسخ مدت صوت را نداده: با سقف مدت فایل حساب می‌شود تا هزینه صفر نشود.
        if (! $estimated && ! $this->prices->tokenPriced($price) && $measured['seconds'] === null) {
            $measured['seconds'] = $bound['seconds'];
            $estimated = true;
        }
        $cost = $this->prices->transcriptionCost($price, $measured);

        $this->wallet->settle($holdId, [
            ...$this->base($context, $model, 'transcription'),
            'input_tokens' => $measured['audio_tokens'] + $measured['text_tokens'],
            'audio_input_tokens' => $measured['audio_tokens'],
            'output_tokens' => $measured['output_tokens'],
            'audio_seconds' => $measured['seconds'],
            'price_id' => $price->id,
            'markup_multiplier' => $multiplier,
            'cost_micros' => $cost,
            'charge_micros' => $this->prices->withMarkup($cost, $multiplier),
            'estimated' => $estimated,
        ]);

        $text = trim((string) ($response['text'] ?? ''));
        if ($text === '') throw new RuntimeException('متنی از پیام صوتی تشخیص داده نشد.');

        return $text;
    }

    /** بعد از پایان پیام: شناسهٔ گفت‌وگوی تازه و وضعیت نهایی را روی ردیف‌های مصرف می‌نشاند. */
    public function finish(AiUsageContext $context, string $status = 'completed'): void
    {
        DB::connection(config('tenancy.database.central_connection'))->table('central_ai_usage')
            ->where('request_group', $context->requestGroup)
            ->update(['conversation_id' => $context->conversationId, 'status' => $status, 'updated_at' => now()]);
    }

    private function base(AiUsageContext $context, string $model, string $type): array
    {
        return [
            'tenant_id' => $context->tenantId,
            'user_id' => $context->userId,
            'user_name' => $context->userName !== null ? mb_substr($context->userName, 0, 255) : null,
            'request_group' => $context->requestGroup,
            'conversation_id' => $context->conversationId,
            'round' => min(255, $context->round),
            'type' => $type,
            'model' => $model,
        ];
    }

    /** سقف محافظه‌کارانهٔ مصرف یک فایل صوتی (حداقل بیت‌ریت ۱۶ کیلوبیت بر ثانیه). */
    private function transcriptionBound(UploadedFile $file): array
    {
        $seconds = max((int) config('assistant.max_audio_seconds', 90), (int) ceil(((int) $file->getSize()) / 2000));

        return [
            'audio_tokens' => $seconds * (int) config('assistant.audio_tokens_per_second_bound', 25),
            'text_tokens' => strlen($this->client->transcriptionPrompt()) + 64,
            'output_tokens' => $seconds * 10,
            'seconds' => (float) $seconds,
        ];
    }
}
