<?php

namespace Modules\Accounting\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Accounting\Models\TreasuryEntry;

class ReverseTreasuryEntryAction
{
    /**
     * Reverse a manual treasury entry by posting an offsetting entry (opposite
     * type, same amount) rather than deleting/mutating the original row, so
     * the movement history stays intact. Idempotent — no-ops if the entry is
     * already reversed.
     */
    public function execute(TreasuryEntry $entry): ?TreasuryEntry
    {
        if ($entry->reversed_at !== null) {
            return null;
        }

        return DB::transaction(function () use ($entry) {
            $fresh = TreasuryEntry::whereKey($entry->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->reversed_at !== null) {
                return null;
            }

            $reversal = TreasuryEntry::create([
                'type' => $fresh->type === TreasuryType::In ? TreasuryType::Out : TreasuryType::In,
                'description' => "عكس قيد: {$fresh->description}",
                'amount' => $fresh->amount,
                'date' => today()->toDateString(),
                'account_id' => $fresh->account_id,
                'source' => JournalSource::REVERSAL->value,
                'reversal_of_id' => $fresh->id,
                'created_by' => auth()->id(),
            ]);

            $fresh->update(['reversed_at' => now()]);

            return $reversal;
        });
    }
}
