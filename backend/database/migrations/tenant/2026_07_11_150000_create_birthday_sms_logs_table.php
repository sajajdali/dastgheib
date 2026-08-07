<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('birthday_sms_logs', function(Blueprint $table){ $table->id(); $table->foreignId('patient_id')->constrained()->cascadeOnDelete(); $table->unsignedSmallInteger('birthday_year'); $table->string('recipient',30); $table->timestamp('sent_at'); $table->timestamps(); $table->unique(['patient_id','birthday_year']); }); }
    public function down(): void { Schema::dropIfExists('birthday_sms_logs'); }
};
