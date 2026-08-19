<?php

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (AppSetting::query()->where('key', 'project_owner_user_id')->exists()) {
            return;
        }

        $owner = User::role('مدیر سیستم')->orderBy('id')->first() ?? User::query()->orderBy('id')->first();

        if ($owner) {
            $owner->assignRole('مدیر سیستم');
            AppSetting::create(['key' => 'project_owner_user_id', 'value' => (string) $owner->id]);
        }
    }

    public function down(): void
    {
        AppSetting::query()->where('key', 'project_owner_user_id')->delete();
    }
};
