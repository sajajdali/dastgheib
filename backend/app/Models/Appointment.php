<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Appointment extends Model
{
    use HasFactory, Auditable;

    public function activitySection(): string { return 'نوبت‌دهی'; }
    public function activityLabel(): string { return trim((string) ($this->lastname ?? '')) ?: 'نوبت #'.$this->getKey(); }

    protected $guarded = []; // اجازه ذخیره تمام فیلدها

    // تبدیل خودکار فیلد services از JSON دیتابیس به آرایه در لاراول
    protected $casts = [
        'services' => 'array',
        'service_types' => 'array',
        'completion_sms_statuses' => 'array',
        'payment_details' => 'array',
        'new_customer' => 'boolean'
        ,'wallet_applied' => 'decimal:2'
        ,'referral_commission_value' => 'decimal:2'
    ];
}
