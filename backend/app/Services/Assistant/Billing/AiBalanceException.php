<?php

namespace App\Services\Assistant\Billing;

use RuntimeException;

final class AiBalanceException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly int $balanceMicros,
        public readonly int $minimumMicros,
    ) {
        parent::__construct($reason === 'below_minimum'
            ? 'موجودی دستیار هوش مصنوعی کمتر از حد مجاز است.'
            : 'موجودی دستیار هوش مصنوعی برای انجام این درخواست کافی نیست.');
    }

    public function messageFor(bool $canManage): string
    {
        $balance = self::usd($this->balanceMicros);
        $minimum = self::usd($this->minimumMicros);
        if ($this->reason === 'below_minimum') {
            return $canManage
                ? "موجودی دستیار هوش مصنوعی شما هم‌اکنون {$balance} دلار است (کمتر از {$minimum} دلار) و امکان استفاده ندارید. لطفاً ابتدا موجودی را شارژ کنید."
                : "موجودی دستیار هوش مصنوعی کلینیک کمتر از {$minimum} دلار است و فعلاً امکان استفاده نیست. لطفاً به مدیر کلینیک اطلاع دهید.";
        }

        return $canManage
            ? "موجودی دستیار هوش مصنوعی ({$balance} دلار) برای تکمیل این درخواست کافی نیست. لطفاً موجودی را شارژ کنید."
            : 'موجودی دستیار هوش مصنوعی کلینیک برای تکمیل این درخواست کافی نیست. لطفاً به مدیر کلینیک اطلاع دهید.';
    }

    public function payload(bool $canManage): array
    {
        return [
            'message' => $this->messageFor($canManage),
            'code' => 'ai_balance_insufficient',
            'reason' => $this->reason,
            'balance_usd' => round($this->balanceMicros / 1000000, 6),
            'min_balance_usd' => round($this->minimumMicros / 1000000, 6),
        ];
    }

    public static function usd(int $micros): string
    {
        $value = number_format($micros / 1000000, 2, '.', '');

        return strtr($value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', '.' => '٫']);
    }
}
