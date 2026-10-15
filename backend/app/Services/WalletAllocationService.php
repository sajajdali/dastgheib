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
            Patient::query()->whereKey($patient->id)->lockForUpdate()->firstOrFail();
            $snapshot = $patient->walletLedgerSnapshot();
            $existing = WalletTransactionAllocation::query()->where('patient_id', $patient->id)->get()
                ->keyBy(fn ($row) => $row->credit_transaction_id.':'.$row->debit_transaction_id);
            $kept = [];
            foreach ($snapshot['allocations'] as $allocation) {
                $key = $allocation['credit_transaction_id'].':'.$allocation['debit_transaction_id'];
                $record = $existing->get($key) ?? new WalletTransactionAllocation(['patient_id' => $patient->id]);
                $record->fill($allocation);
                if (! $record->exists || $record->isDirty()) $record->save();
                $kept[] = $record->id;
            }
            WalletTransactionAllocation::query()->where('patient_id', $patient->id)->whereNotIn('id', $kept)->delete();
        });
    }
}
