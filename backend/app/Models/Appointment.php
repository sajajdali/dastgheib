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

    /** Only real bookings, not the empty calendar slots older versions persisted. */
    public function scopeMeaningful($query)
    {
        return $query->where(fn ($match) => self::constrainToMeaningful($match));
    }

    /** Adds OR conditions that match any row carrying real booking data; call inside a nested where. */
    public static function constrainToMeaningful($query): void
    {
        foreach ([
            'lastname', 'gender', 'phone', 'file_number', 'status',
            'doctor', 'consultant', 'source', 'description', 'doctor_note',
            'done', 'payment_method', 'payment_account', 'payment_link',
            'referrer_phone', 'appointment_sms', 'info_sms',
        ] as $column) {
            $query->orWhere(function ($query) use ($column) {
                $query->whereNotNull($column)->where($column, '<>', '');
            });
        }
        $query->orWhere('new_customer', true)
            ->orWhere('payment_link_sent_count', '>', 0)
            ->orWhere('amount', '>', 0)
            ->orWhere('debt', '>', 0)
            ->orWhere('discount', '>', 0)
            ->orWhere(function ($query) {
                $query->whereNotNull('services');
                if ($query->getConnection()->getDriverName() === 'mysql') {
                    $query->whereRaw('services REGEXP ?', ['"name"[[:space:]]*:[[:space:]]*"[^"]+"']);
                } else {
                    $query->where('services', 'like', '%"name":"_%');
                }
            })
            ->orWhere(function ($query) {
                $query->whereNotNull('service_types')->whereNotIn('service_types', ['', '[]', 'null']);
            });
    }

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment): void {
            if (! Schema::hasColumn('appointments', 'doctor_commission_enabled') || ! Schema::hasTable('channels')) return;
            foreach (Channel::commissionFlagsForSource($appointment->source) as $field => $enabled) {
                if ($appointment->getAttribute($field) === null) $appointment->setAttribute($field, $enabled);
            }
        });

        static::updating(function (Appointment $appointment): void {
            if (! $appointment->isDirty('source') || trim((string) $appointment->getRawOriginal('done')) === 'انجام شد') return;
            if (! Schema::hasColumn('appointments', 'doctor_commission_enabled') || ! Schema::hasTable('channels')) return;
            $appointment->forceFill(Channel::commissionFlagsForSource($appointment->source));
        });

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
        ,'doctor_commission_enabled' => 'boolean'
        ,'staff_commission_enabled' => 'boolean'
        ,'referral_reward_enabled' => 'boolean'
        ,'wallet_applied' => 'decimal:2'
        ,'referral_commission_value' => 'decimal:2'
        ,'duration_minutes' => 'integer'
        ,'before_photo_taken_at' => 'datetime'
        ,'after_photo_taken_at' => 'datetime'
    ];
}
