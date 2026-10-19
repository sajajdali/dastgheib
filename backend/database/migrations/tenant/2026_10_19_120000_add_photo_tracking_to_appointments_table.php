<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('before_photo_taken_at')->nullable()->after('completed_at');
            $table->unsignedBigInteger('before_photo_taken_by_id')->nullable()->after('before_photo_taken_at');
            $table->string('before_photo_taken_by_name')->nullable()->after('before_photo_taken_by_id');
            $table->timestamp('after_photo_taken_at')->nullable()->after('before_photo_taken_by_name');
            $table->unsignedBigInteger('after_photo_taken_by_id')->nullable()->after('after_photo_taken_at');
            $table->string('after_photo_taken_by_name')->nullable()->after('after_photo_taken_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'before_photo_taken_at', 'before_photo_taken_by_id', 'before_photo_taken_by_name',
                'after_photo_taken_at', 'after_photo_taken_by_id', 'after_photo_taken_by_name',
            ]);
        });
    }
};
