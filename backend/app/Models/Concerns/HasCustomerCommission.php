<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Schema;

/**
 * درصد پورسانت پزشک/پرسنل به تفکیک مشتری جدید و قدیمی.
 * اگر برای یک نوع مشتری درصدی تعریف نشده باشد (null)، برای آن نوع پورسانتی محاسبه نمی‌شود.
 *
 * فیلدهای قدیمی (درصد پایه و «پورسانت برای») همیشه با این دو مقدار هم‌گام می‌مانند تا
 * گزارش‌ها، seederها و نسخه‌های قبلی صفحه همان نتیجه را بگیرند.
 */
trait HasCustomerCommission
{
    private static array $customerCommissionColumns = [];

    public static function bootHasCustomerCommission(): void
    {
        static::saving(function (self $model) {
            if (! $model->hasCustomerCommissionColumns()) {
                return;
            }

            if ($model->isDirty(['new_customer_bonus', 'existing_customer_bonus'])) {
                $model->forceFill(static::legacyCommissionFields(
                    $model->percentOrNull($model->new_customer_bonus),
                    $model->percentOrNull($model->existing_customer_bonus),
                ));

                return;
            }

            // رکوردی که فقط با فیلدهای قدیمی ساخته یا ویرایش شده است.
            if (! $model->exists || $model->isDirty(['bonus', 'commission_customer_scope'])) {
                $model->forceFill(static::customerFieldsFromLegacy(
                    $model->bonus,
                    $model->commission_customer_scope,
                ));
            }
        });
    }

    public function receivesCommissionFor(bool $newCustomer): bool
    {
        return $this->customerCommissionPercent($newCustomer) !== null;
    }

    public function customerCommissionPercent(bool $newCustomer): ?float
    {
        $field = $newCustomer ? 'new_customer_bonus' : 'existing_customer_bonus';
        if (! array_key_exists($field, $this->getAttributes())) {
            return static::customerFieldsFromLegacy($this->bonus, $this->commission_customer_scope)[$field];
        }

        return $this->percentOrNull($this->getAttribute($field));
    }

    public static function legacyCommissionFields(?float $newCustomerBonus, ?float $existingCustomerBonus): array
    {
        $scope = match (true) {
            $newCustomerBonus !== null && $existingCustomerBonus !== null => 'both',
            $newCustomerBonus !== null => 'new',
            $existingCustomerBonus !== null => 'existing',
            default => 'none',
        };

        return [
            'commission_customer_scope' => $scope,
            'bonus' => max($newCustomerBonus ?? 0, $existingCustomerBonus ?? 0),
        ];
    }

    public static function customerFieldsFromLegacy(mixed $bonus, ?string $scope): array
    {
        $bonus = (float) ($bonus ?? 0);
        $scope = $scope ?: 'both';

        return [
            'new_customer_bonus' => in_array($scope, ['new', 'both'], true) ? $bonus : null,
            'existing_customer_bonus' => in_array($scope, ['existing', 'both'], true) ? $bonus : null,
        ];
    }

    private function percentOrNull(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private function hasCustomerCommissionColumns(): bool
    {
        $database = $this->getConnection()->getDatabaseName();
        $check = fn () => Schema::connection($this->getConnectionName())->hasColumn($this->getTable(), 'new_customer_bonus');
        // پایگاه دادهٔ موقت تست‌ها در هر تست جدول متفاوتی دارد؛ نتیجه را نگه نمی‌داریم.
        if ($database === ':memory:') {
            return $check();
        }

        return self::$customerCommissionColumns[$database.'.'.$this->getTable()] ??= $check();
    }
}
