<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\WalletTransactionAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WalletAllocationService
{
    public function sync(Patient $patient): void
    {
        if (! Schema::hasTable('wallet_transaction_allocations')) return;

        DB::transaction(function () use ($patient) {
            $snapshot = $patient->walletLedgerSnapshot();
            WalletTransactionAllocation::query()->where('patient_id', $patient->id)->delete();
            foreach ($snapshot['allocations'] as $allocation) {
                WalletTransactionAllocation::create(['patient_id' => $patient->id, ...$allocation]);
            }
        });
    }
}
