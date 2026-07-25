<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ۱. جدول بخش‌ها (پرونده‌ها، وقت‌دهی، پیگیری و...)
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('title')->unique(); // نام بخش به فارسی یا انگلیسی
            $table->timestamps();
        });

        // ۲. جدول واسط برای ذخیره دسترسی هر پرسنل به هر بخش
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->json('permissions'); // آرایه‌ای از دسترسی‌ها مثل: ["تشکیل پرونده", "جستجو"]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('modules');
    }
};
