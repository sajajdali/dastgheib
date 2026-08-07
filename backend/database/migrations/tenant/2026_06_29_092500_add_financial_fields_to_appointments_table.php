<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'referrer_phone')) {
                $table->string('referrer_phone', 30)->nullable()->after('debt');
            }

            if (! Schema::hasColumn('appointments', 'referral_score')) {
                $table->decimal('referral_score', 15, 0)->default(0)->after('referrer_phone');
            }

            if (! Schema::hasColumn('appointments', 'discount')) {
                $table->decimal('discount', 15, 0)->default(0)->after('referral_score');
            }

            if (! Schema::hasColumn('appointments', 'original_amount')) {
                $table->decimal('original_amount', 15, 0)->default(0)->after('discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['referrer_phone', 'referral_score', 'discount', 'original_amount'],
                fn (string $column) => Schema::hasColumn('appointments', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
