<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->json('owners')->nullable()->after('owner');
        });

        DB::table('tickets')
            ->select(['id', 'owner'])
            ->whereNotNull('owner')
            ->where('owner', '<>', '')
            ->orderBy('id')
            ->chunkById(200, function ($tickets): void {
                foreach ($tickets as $ticket) {
                    DB::table('tickets')
                        ->where('id', $ticket->id)
                        ->update(['owners' => json_encode([$ticket->owner], JSON_UNESCAPED_UNICODE)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('owners');
        });
    }
};
