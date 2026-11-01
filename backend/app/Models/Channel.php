<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Channel extends Model
{
    use Auditable;

    public function activitySection(): string { return 'کانال‌ها'; }

    // تعیین اینکه ستون 'name' قابلیت پر شدن گروهی (mass assignment) را داشته باشد
    protected $fillable = [
        'name',
        'icon',
        'icon_image_path',
        'exclude_doctor_commission',
        'exclude_staff_commission',
        'exclude_referral_reward',
    ];

    protected $casts = [
        'exclude_doctor_commission' => 'boolean',
        'exclude_staff_commission' => 'boolean',
        'exclude_referral_reward' => 'boolean',
    ];

    protected $appends = ['icon_image_url'];

    public static function commissionFlagsForSource(?string $source): array
    {
        $key = mb_strtolower(trim((string) $source));
        $matches = $key === '' ? collect() : self::query()
            ->get(['name', 'exclude_doctor_commission', 'exclude_staff_commission', 'exclude_referral_reward'])
            ->filter(fn (Channel $channel) => mb_strtolower(trim($channel->name)) === $key);

        return [
            'doctor_commission_enabled' => ! $matches->contains(fn (Channel $channel) => $channel->exclude_doctor_commission),
            'staff_commission_enabled' => ! $matches->contains(fn (Channel $channel) => $channel->exclude_staff_commission),
            'referral_reward_enabled' => ! $matches->contains(fn (Channel $channel) => $channel->exclude_referral_reward),
        ];
    }

    public function getIconImageUrlAttribute(): ?string
    {
        return $this->icon_image_path
            ? Storage::disk('public')->url($this->icon_image_path)
            : null;
    }
}
