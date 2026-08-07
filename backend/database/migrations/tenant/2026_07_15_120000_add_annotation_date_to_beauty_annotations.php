<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beauty_annotations', function (Blueprint $table) {
            $table->date('annotation_date')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('beauty_annotations', function (Blueprint $table) {
            $table->dropColumn('annotation_date');
        });
    }
};
