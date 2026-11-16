<?php

return [
    'default_columns' => [
        'date', 'service', 'quantity', 'net_amount', 'commission_rate', 'commission_amount',
    ],

    // اطلاعات تماس و کد ملی عمداً در این فهرست نیستند؛ گزارش شخصی نباید
    // راهی برای دور زدن مجوزهای حریم خصوصی پرونده باشد.
    'columns' => [
        'date' => 'تاریخ خدمت',
        'service' => 'خدمت / آیتم',
        'patient_name' => 'نام مراجعه‌کننده',
        'file_number' => 'شماره پرونده',
        'quantity' => 'تعداد / سی‌سی',
        'gross_amount' => 'مبلغ ناخالص',
        'discount_amount' => 'تخفیف',
        'net_amount' => 'مبلغ خالص',
        'commission_base' => 'مبنای پورسانت',
        'commission_rate' => 'نرخ پورسانت',
        'commission_amount' => 'پورسانت نهایی',
        'customer_type' => 'نوع مشتری',
        'role' => 'نقش در خدمت',
        'description' => 'توضیحات محاسبه',
    ],
];
