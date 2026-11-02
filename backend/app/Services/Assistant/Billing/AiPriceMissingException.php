<?php

namespace App\Services\Assistant\Billing;

use RuntimeException;

final class AiPriceMissingException extends RuntimeException
{
    public function __construct(public readonly string $model)
    {
        parent::__construct("نرخ مدل {$model} هنوز توسط مدیریت مرکزی تعریف نشده است و فعلاً قابل استفاده نیست.");
    }
}
