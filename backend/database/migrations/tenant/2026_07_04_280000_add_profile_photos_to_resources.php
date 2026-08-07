<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['doctors', 'staff', 'users'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $afterColumn = $tableName === 'users' ? 'mobile' : 'salary';

                if (! Schema::hasColumn($tableName, 'profile_photo_path')) {
                    $table->string('profile_photo_path')->nullable()->after($afterColumn);
                }

                if (! Schema::hasColumn($tableName, 'profile_thumbnail_path')) {
                    $table->string('profile_thumbnail_path')->nullable()->after('profile_photo_path');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['doctors', 'staff', 'users'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $columns = array_values(array_filter(
                    ['profile_thumbnail_path', 'profile_photo_path'],
                    fn (string $column) => Schema::hasColumn($tableName, $column)
                ));

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
