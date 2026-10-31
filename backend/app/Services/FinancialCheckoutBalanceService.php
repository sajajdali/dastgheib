<?php

namespace App\Services;

use Symfony\Component\HttpKernel\Exception\HttpException;

class FinancialCheckoutBalanceService
{
    public function reconcile(
        int $payable,
        int $receivedTotal,
        int $submittedDebt,
        bool $confirmDebtOverage = false,
        bool $confirmOverpayment = false,
    ): array {
        $payable = max(0, $payable);
        $receivedTotal = max(0, $receivedTotal);
        $submittedDebt = max(0, $submittedDebt);
        $originalPayable = $payable;
        $calculatedDebt = max(0, $payable - $receivedTotal);

        if ($submittedDebt < $calculatedDebt) {
            $difference = $calculatedDebt - $submittedDebt;
            throw new HttpException(422, sprintf(
                'جمع بدهی خدمات %s تومان است، اما مانده قابل پرداخت %s تومان است؛ مبلغ بدهی خدمات باید %s تومان افزایش یابد.',
                number_format($submittedDebt), number_format($calculatedDebt), number_format($difference)
            ));
        }

        $confirmedDebtOverage = 0;
        if ($submittedDebt > $calculatedDebt) {
            $confirmedDebtOverage = $submittedDebt - $calculatedDebt;
            if (! $confirmDebtOverage) {
                throw new HttpException(422, sprintf(
                    'جمع بدهی خدمات %s تومان است که %s تومان بیشتر از مانده قابل پرداخت %s تومان است؛ برای ثبت مبلغ بیشتر، تأیید کاربر لازم است.',
                    number_format($submittedDebt), number_format($confirmedDebtOverage), number_format($calculatedDebt)
                ));
            }

            $payable = $receivedTotal + $submittedDebt;
        }

        $overpayment = max(0, $receivedTotal - $payable);
        if ($overpayment > 0 && ! $confirmOverpayment) {
            throw new HttpException(422, sprintf(
                'جمع پرداخت‌ها %s تومان است که %s تومان بیشتر از مبلغ قابل پرداخت %s تومان است؛ برای ثبت اضافه‌پرداخت، تأیید کاربر لازم است.',
                number_format($receivedTotal), number_format($overpayment), number_format($payable)
            ));
        }

        return [
            'payable' => $payable,
            'calculated_debt' => $calculatedDebt,
            'total_debt' => $submittedDebt,
            'confirmed_debt_overage' => $confirmedDebtOverage,
            'payable_increase' => max(0, $payable - $originalPayable),
            'overpayment' => $overpayment,
            'balance' => $overpayment > 0 ? -$overpayment : $submittedDebt,
        ];
    }
}
