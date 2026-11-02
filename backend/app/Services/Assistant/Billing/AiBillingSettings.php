<?php

namespace App\Services\Assistant\Billing;

use Illuminate\Support\Facades\DB;

final class AiBillingSettings
{
    public function current(): object
    {
        $row = $this->central()->table('central_ai_settings')->orderBy('id')->first();
        if ($row) return $row;
        $this->central()->table('central_ai_settings')->insert(['created_at' => now(), 'updated_at' => now()]);

        return $this->central()->table('central_ai_settings')->orderBy('id')->first();
    }

    public function save(array $data): object
    {
        $current = $this->current();
        $this->central()->table('central_ai_settings')->where('id', $current->id)->update([...$data, 'updated_at' => now()]);

        return $this->current();
    }

    /** ضریب سود هر tenant؛ اگر برای آن tenant جدا تعریف نشده باشد، ضریب سراسری. */
    public function multiplierFor(string $tenantId): string
    {
        $override = $this->central()->table('central_ai_wallets')->where('tenant_id', $tenantId)->value('markup_multiplier');

        return (string) ($override ?? $this->current()->markup_multiplier);
    }

    private function central()
    {
        return DB::connection(config('tenancy.database.central_connection'));
    }
}
