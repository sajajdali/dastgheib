<?php

namespace App\Services\Assistant\Billing;

use Illuminate\Support\Facades\DB;

/** نرخ‌ها میکرودلار به ازای هر ۱ میلیون توکن (و برای رونویسی دقیقه‌ای، به ازای هر دقیقه) هستند. */
final class AiPriceBook
{
    public function current(string $model): ?object
    {
        return $this->central()->table('central_ai_model_prices')
            ->where('model', $model)
            ->where('effective_from', '<=', now())
            ->orderByDesc('effective_from')->orderByDesc('id')
            ->first();
    }

    public function require(string $model): object
    {
        return $this->current($model) ?? throw new AiPriceMissingException($model);
    }

    public function chatCost(object $price, int $input, int $cached, int $output): int
    {
        $cached = min($cached, $input);
        $cachedRate = (int) ($price->cached_input_per_m ?? $price->input_per_m);

        return $this->perMillion(($input - $cached) * (int) $price->input_per_m + $cached * $cachedRate + $output * (int) $price->output_per_m);
    }

    /** @param array{audio_tokens:int,text_tokens:int,output_tokens:int,seconds:float|null} $usage */
    public function transcriptionCost(object $price, array $usage): int
    {
        $hasTokens = $usage['audio_tokens'] + $usage['text_tokens'] + $usage['output_tokens'] > 0;
        if ($hasTokens && ($this->tokenPriced($price) || ! $price->per_minute)) {
            $audioRate = (int) ($price->audio_input_per_m ?? $price->input_per_m);
            return $this->perMillion($usage['audio_tokens'] * $audioRate + $usage['text_tokens'] * (int) $price->input_per_m
                + $usage['output_tokens'] * (int) $price->output_per_m);
        }

        return (int) ceil(((float) ($usage['seconds'] ?? 0)) * (int) ($price->per_minute ?? 0) / 60);
    }

    public function tokenPriced(object $price): bool
    {
        return (int) $price->input_per_m + (int) ($price->audio_input_per_m ?? 0) + (int) $price->output_per_m > 0;
    }

    public function withMarkup(int $cost, string $multiplier): int
    {
        $basisPoints = (int) round(((float) $multiplier) * 10000);

        return intdiv($cost * $basisPoints + 9999, 10000);
    }

    private function perMillion(int $value): int
    {
        return intdiv($value + 999999, 1000000);
    }

    private function central()
    {
        return DB::connection(config('tenancy.database.central_connection'));
    }
}
