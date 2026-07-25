<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('patients', function(Blueprint $table){ $table->string('first_name')->nullable()->change(); $table->string('last_name')->nullable()->change(); $table->string('phone')->nullable()->change(); $table->string('file_number')->nullable()->change(); $table->string('gender')->nullable()->change(); }); }
    public function down(): void { Schema::table('patients', function(Blueprint $table){ $table->string('first_name')->nullable(false)->change(); $table->string('last_name')->nullable(false)->change(); $table->string('phone')->nullable(false)->change(); $table->string('file_number')->nullable(false)->change(); $table->string('gender')->nullable(false)->change(); }); }
};
