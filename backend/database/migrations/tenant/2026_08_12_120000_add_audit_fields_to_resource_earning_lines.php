<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_earning_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('resource_earning_lines', 'status')) {
                $table->string('status', 20)->default('active')->after('amount')->index();
            }
            if (! Schema::hasColumn('resource_earning_lines', 'manually_edited')) {
                $table->boolean('manually_edited')->default(false)->after('status');
            }
            if (! Schema::hasColumn('resource_earning_lines', 'edited_by_user_id')) {
                $table->unsignedBigInteger('edited_by_user_id')->nullable()->after('manually_edited');
            }
            if (! Schema::hasColumn('resource_earning_lines', 'edited_by_name')) {
                $table->string('edited_by_name')->nullable()->after('edited_by_user_id');
            }
            if (! Schema::hasColumn('resource_earning_lines', 'edited_at')) {
                $table->dateTime('edited_at')->nullable()->after('edited_by_name');
            }
            if (! Schema::hasColumn('resource_earning_lines', 'deleted_by_user_id')) {
                $table->unsignedBigInteger('deleted_by_user_id')->nullable()->after('edited_at');
            }
            if (! Schema::hasColumn('resource_earning_lines', 'deleted_by_name')) {
                $table->string('deleted_by_name')->nullable()->after('deleted_by_user_id');
            }
            if (! Schema::hasColumn('resource_earning_lines', 'deleted_at')) {
                $table->dateTime('deleted_at')->nullable()->after('deleted_by_name');
            }
            if (! Schema::hasColumn('resource_earning_lines', 'audit_events')) {
                $table->json('audit_events')->nullable()->after('calculation_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('resource_earning_lines', function (Blueprint $table) {
            foreach ([
                'audit_events',
                'deleted_at',
                'deleted_by_name',
                'deleted_by_user_id',
                'edited_at',
                'edited_by_name',
                'edited_by_user_id',
                'manually_edited',
                'status',
            ] as $column) {
                if (Schema::hasColumn('resource_earning_lines', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
