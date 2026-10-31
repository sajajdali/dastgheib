<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;
use App\Services\AppointmentAutomaticSmsService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Appointment extends Model
{
    use HasFactory, Auditable;

    public function activitySection(): string { return 'نوبت‌دهی'; }
    public function activityLabel(): string { return trim((string) ($this->lastname ?? '')) ?: 'نوبت #'.$this->getKey(); }
    public function serviceBookings() { return $this->hasMany(AppointmentServiceBooking::class); }
    public function automaticSmsItems(): HasMany { return $this->hasMany(AppointmentAutomaticSms::class); }

    protected static function booted(): void
    {
        static::saved(function (Appointment $appointment): void {
            if (Schema::hasTable('appointment_automatic_sms')) {
                app(AppointmentAutomaticSmsService::class)->syncForAppointment($appointment);
            }
        });
    }

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
        ,'duration_minutes' => 'integer'
        ,'before_photo_taken_at' => 'datetime'
        ,'after_photo_taken_at' => 'datetime'
    ];
}
