<?php

namespace Modules\Admin\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;

class AccountingImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $date = $row['التاريخ'] ?? $row['date'] ?? null;
            $description = trim((string) ($row['الوصف'] ?? $row['description'] ?? ''));
            $amount = (float) ($row['المبلغ'] ?? $row['amount'] ?? 0);

            if (empty($description) || $amount <= 0) {
                $this->skipped++;

                continue;
            }

            $debitCode = trim((string) ($row['حساب مدين'] ?? $row['debit_account'] ?? ''));
            $creditCode = trim((string) ($row['حساب دائن'] ?? $row['credit_account'] ?? ''));

            $debitAccount = $debitCode ? Account::where('code', $debitCode)->first() : null;
            $creditAccount = $creditCode ? Account::where('code', $creditCode)->first() : null;

            if (! $debitAccount || ! $creditAccount) {
                $this->skipped++;

                continue;
            }

            JournalEntry::create([
                'date' => $date ?? now()->toDateString(),
                'description' => $description,
                'debit_account_id' => $debitAccount->id,
                'credit_account_id' => $creditAccount->id,
                'amount' => $amount,
                'reference' => $row['المرجع'] ?? $row['reference'] ?? null,
                'source' => 'manual',
            ]);

            $this->created++;
        }
    }
}
