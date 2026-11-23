<?php

return [
    /*
     * «ورود سریع با مدیر کل» فقط برای این IPها نمایش داده و انجام می‌شود (با کاما جدا کنید).
     * علاوه بر این، اجرای محلی (localhost یا *.localhost از روی همین سیستم) هم مجاز است.
     */
    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('QUICK_LOGIN_IPS', '93.118.157.142'))
    ))),
];
