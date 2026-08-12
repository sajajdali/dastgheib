<?php

return [
    'groups' => [
        [
            'key' => 'patients',
            'label' => 'پرونده‌ها',
            'permissions' => [
                ['name' => 'patients.view', 'label' => 'مشاهده پرونده‌ها'],
                ['name' => 'patients.create', 'label' => 'تشکیل پرونده'],
                ['name' => 'patients.update', 'label' => 'ویرایش پرونده'],
                ['name' => 'patients.delete', 'label' => 'حذف پرونده'],
                ['name' => 'patients.wallet', 'label' => 'مدیریت کیف پول'],
                ['name' => 'patients.view_phone', 'label' => 'مشاهده شماره تماس بیمار'],
            ],
        ],
        [
            'key' => 'appointments',
            'label' => 'نوبت‌دهی',
            'permissions' => [
                ['name' => 'appointments.view', 'label' => 'مشاهده نوبت‌ها'],
                ['name' => 'appointments.create', 'label' => 'ثبت نوبت'],
                ['name' => 'appointments.update', 'label' => 'ویرایش نوبت'],
                ['name' => 'appointments.delete', 'label' => 'حذف نوبت'],
                ['name' => 'appointments.income', 'label' => 'مشاهده درآمد'],
                ['name' => 'appointments.sms', 'label' => 'ارسال پیامک نوبت'],
            ],
        ],
        [
            'key' => 'photos',
            'label' => 'عکس‌ها',
            'permissions' => [
                ['name' => 'photos.view', 'label' => 'مشاهده عکس‌ها و مقایسه قبل و بعد'],
            ],
        ],
        [
            'key' => 'followups',
            'label' => 'پیگیری',
            'permissions' => [
                ['name' => 'followups.view', 'label' => 'مشاهده پیگیری‌ها'],
                ['name' => 'followups.create', 'label' => 'ثبت پیگیری'],
                ['name' => 'followups.update', 'label' => 'ویرایش پیگیری'],
                ['name' => 'followups.campaigns', 'label' => 'مدیریت کمپین‌ها'],
                ['name' => 'followups.campaign_cost', 'label' => 'مشاهده هزینه کمپین و CPL'],
            ],
        ],
        [
            'key' => 'reports',
            'label' => 'گزارش‌ها',
            'permissions' => [
                ['name' => 'reports.view', 'label' => 'مشاهده گزارش‌ها'],
                ['name' => 'activity_logs.view', 'label' => 'مشاهده سوابق فعالیت'],
                ['name' => 'reports.financial', 'label' => 'گزارش مالی'],
                ['name' => 'reports.appointments', 'label' => 'گزارش نوبت‌دهی'],
                ['name' => 'reports.marketing', 'label' => 'گزارش تبلیغات'],
                ['name' => 'reports.staff', 'label' => 'گزارش پرسنل'],
                ['name' => 'reports.doctors', 'label' => 'گزارش پزشکان'],
                ['name' => 'reports.debtors', 'label' => 'گزارش بدهکاران'],
            ],
        ],
        [
            'key' => 'inventory',
            'label' => 'انبار',
            'permissions' => [
                ['name' => 'inventory.view', 'label' => 'مشاهده انبار'],
                ['name' => 'inventory.create', 'label' => 'افزودن کالا'],
                ['name' => 'inventory.update', 'label' => 'ویرایش موجودی و قیمت'],
                ['name' => 'inventory.delete', 'label' => 'حذف کالا'],
                ['name' => 'inventory.cost', 'label' => 'مشاهده هزینه‌ها'],
            ],
        ],
        [
            'key' => 'beauty',
            'label' => 'زیبایار',
            'permissions' => [
                ['name' => 'beauty.view', 'label' => 'مشاهده زیبایار'],
                ['name' => 'beauty.manage', 'label' => 'مدیریت برنامه زیبایی'],
            ],
        ],
        [
            'key' => 'resources',
            'label' => 'منابع',
            'permissions' => [
                ['name' => 'resources.view', 'label' => 'مشاهده منابع'],
                ['name' => 'resources.doctors', 'label' => 'مدیریت پزشکان'],
                ['name' => 'resources.staff', 'label' => 'مدیریت پرسنل'],
                ['name' => 'resources.channels', 'label' => 'مدیریت کانال‌ها'],
            ],
        ],
        [
            'key' => 'tickets',
            'label' => 'تیکت‌ها',
            'permissions' => [
                ['name' => 'tickets.view', 'label' => 'مشاهده تیکت‌ها'],
                ['name' => 'tickets.create', 'label' => 'ثبت تیکت'],
                ['name' => 'tickets.update', 'label' => 'ویرایش تیکت'],
                ['name' => 'tickets.close', 'label' => 'بستن تیکت'],
                ['name' => 'tickets.delete', 'label' => 'حذف تیکت'],
            ],
        ],
        [
            'key' => 'services',
            'label' => 'خدمات',
            'permissions' => [
                ['name' => 'services.view', 'label' => 'مشاهده خدمات'],
                ['name' => 'services.manage', 'label' => 'مدیریت خدمات'],
            ],
        ],
        [
            'key' => 'bills',
            'label' => 'هزینه‌ها',
            'permissions' => [
                ['name' => 'bills.view', 'label' => 'مشاهده هزینه‌ها'],
                ['name' => 'bills.manage', 'label' => 'مدیریت هزینه‌ها'],
            ],
        ],
        [
            'key' => 'attendance',
            'label' => 'حضور و غیاب',
            'permissions' => [
                ['name' => 'attendance.view', 'label' => 'مشاهده حضور و غیاب'],
                ['name' => 'attendance.clock', 'label' => 'ثبت ورود و خروج'],
                ['name' => 'attendance.manage', 'label' => 'مدیریت حضور و غیاب'],
            ],
        ],
        [
            'key' => 'payroll',
            'label' => 'حقوق و تسویه',
            'permissions' => [
                ['name' => 'payroll.view', 'label' => 'مشاهده حقوق و تسویه'],
            ],
        ],
        [
            'key' => 'settings',
            'label' => 'تنظیمات',
            'permissions' => [
                ['name' => 'settings.view', 'label' => 'مشاهده تنظیمات'],
                ['name' => 'settings.manage', 'label' => 'ویرایش تنظیمات'],
                ['name' => 'settings.users', 'label' => 'مدیریت کاربران'],
            ],
        ],
        [
            'key' => 'roles',
            'label' => 'نقش‌ها و دسترسی‌ها',
            'permissions' => [
                ['name' => 'roles.view', 'label' => 'مشاهده نقش‌ها'],
                ['name' => 'roles.manage', 'label' => 'مدیریت نقش‌ها'],
                ['name' => 'roles.assign', 'label' => 'تخصیص نقش به کاربران'],
            ],
        ],
    ],
];
