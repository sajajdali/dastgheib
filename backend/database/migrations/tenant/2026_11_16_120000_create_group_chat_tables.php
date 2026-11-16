<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('group_chat_messages')) {
            Schema::create('group_chat_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                // نام فرستنده در لحظهٔ ارسال نگه داشته می‌شود تا با حذف کاربر، تاریخچه بی‌نام نشود.
                $table->string('sender_name')->nullable();
                $table->string('type', 10)->default('text');
                $table->text('body')->nullable();
                $table->string('attachment_path')->nullable();
                $table->string('attachment_mime', 120)->nullable();
                $table->unsignedInteger('attachment_size')->nullable();
                $table->unsignedSmallInteger('voice_duration')->nullable();
                // متن‌های قبلی پیش از هر ویرایش برای جلوگیری از سوءاستفاده نگه داشته می‌شوند.
                $table->json('edit_history')->nullable();
                $table->timestamp('edited_at')->nullable();
                // حذف نرم: محتوا در پایگاه داده می‌ماند ولی در خروجی API نمایش داده نمی‌شود.
                $table->timestamp('deleted_at')->nullable();
                $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('group_chat_reads')) {
            Schema::create('group_chat_reads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('last_read_message_id')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('group_chat_reads');
        Schema::dropIfExists('group_chat_messages');
    }
};
