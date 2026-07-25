<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $guarded = []; // اجازه ذخیره تمام فیلدها

    // تبدیل خودکار فیلد services از JSON دیتابیس به آرایه در لاراول
    protected $casts = [
        'services' => 'array',
        'service_types' => 'array',
        'completion_sms_statuses' => 'array',
        'new_customer' => 'boolean'
        ,'wallet_applied' => 'decimal:2'
        ,'referral_commission_value' => 'decimal:2'
    ];
}
