<?php

namespace App\Services\Assistant;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\User;

/**
 * دکمهٔ «پرونده» برای هر شخص (کاربر، پزشک، پرسنل): اگر پزشک یا پرسنل است پروندهٔ او در بخش منابع
 * (ردیفش برجسته می‌شود)، وگرنه پروندهٔ بیمارِ هم‌موبایل یا هم‌نام.
 */
final class AssistantPersonLinks
{
    public function forUser(User $viewer, int $userId): ?array
    {
        $person = User::query()->find($userId, ['id', 'name', 'mobile']);
        if (! $person) return null;

        return $this->resource($viewer, 'doctor', Doctor::query()->where('user_id', $person->id)->value('id'))
            ?? $this->resource($viewer, 'staff', Staff::query()->where('user_id', $person->id)->value('id'))
            ?? $this->patient($viewer, (string) $person->mobile, (string) $person->name);
    }

    public function forResource(User $viewer, string $type, int $id): ?array
    {
        $model = $type === 'staff' ? Staff::class : Doctor::class;
        $resource = $model::query()->find($id, ['id', 'name', 'user_id']);
        if (! $resource) return null;
        $mobile = $resource->user_id ? (string) User::query()->whereKey($resource->user_id)->value('mobile') : '';

        return $this->resource($viewer, $type, $resource->id) ?? $this->patient($viewer, $mobile, (string) $resource->name);
    }

    public function forPatient(User $viewer, ?string $fileNumber): ?array
    {
        if (! $viewer->can('patients.view') || blank($fileNumber)) return null;

        return ['page' => 'patient_profile', 'file_number' => (string) $fileNumber, 'label' => 'باز کردن پرونده'];
    }

    private function patient(User $viewer, string $mobile, string $name): ?array
    {
        if (! $viewer->can('patients.view')) return null;
        $digits = substr(preg_replace('/\D+/', '', strtr($mobile, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9'])), -10);
        $file = null;
        if (strlen($digits) === 10) {
            $file = Patient::query()->where('phone', 'like', '%'.$digits)->whereNotNull('file_number')->where('file_number', '<>', '')->value('file_number');
        }
        $tokens = array_values(array_filter(explode(' ', preg_replace('/\s+/u', ' ', trim($name)))));
        if (! $file && count($tokens) >= 2) {
            $matches = Patient::query()->whereNotNull('file_number')->where('file_number', '<>', '')
                ->where('first_name', 'like', addcslashes($tokens[0], '%_\\').'%')
                ->where('last_name', 'like', '%'.addcslashes(end($tokens), '%_\\'))
                ->limit(20)->get(['first_name', 'last_name', 'file_number'])
                ->filter(fn (Patient $patient) => trim($patient->first_name.' '.$patient->last_name) === trim($name));
            if ($matches->count() === 1) $file = $matches->first()->file_number;
        }

        return $file ? ['page' => 'patient_profile', 'file_number' => (string) $file, 'label' => 'باز کردن پرونده'] : null;
    }

    private function resource(User $viewer, string $type, mixed $id): ?array
    {
        $page = $type === 'staff' ? 'settings_staff' : 'settings_doctors';
        if (! $id || ! (new AssistantNavigationCatalog)->destination($viewer, $page)) return null;

        return ['page' => $page, 'resource_type' => $type, 'resource_id' => (int) $id,
            'label' => $type === 'staff' ? 'باز کردن پروندهٔ پرسنل' : 'باز کردن پروندهٔ پزشک'];
    }
}
