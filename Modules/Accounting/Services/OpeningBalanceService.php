<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\AccountNature;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Exceptions\AccountingException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;

/**
 * The opening-balance journal (guide §3.1 / §4.1): capital, fixed assets and
 * their accumulated depreciation, bank, inventory, and any receivables /
 * payables brought forward.
 *
 * Input is one natural-side balance per account (as the guide's opening
 * table lists them — a contra account such as 1121 is entered as a
 * positive credit balance). The set must foot (debit balances = credit
 * balances). Because the journal stores one debit/credit pair per row, each
 * balance is posted against 3020 (retained earnings) as the opening
 * clearing account; when the set foots, 3020 ends exactly at the retained
 * earnings figure supplied (0 if none).
 */
class OpeningBalanceService
{
    private const CLEARING = AccountCode::RETAINED_EARNINGS;

    public function __construct(private readonly JournalService $journalService) {}

    /**
     * @param  array<string, float|int|string>  $balances  account code => natural-side balance
     * @return array<int, array{code:string, name:string, debit:float, credit:float}> the opening lines
     *
     * @throws AccountingException
     */
    public function preview(string $date, array $balances): array
    {
        if (JournalEntry::where('source', JournalSource::OPENING_BALANCE->value)->whereNull('reversed_at')->exists()) {
            throw new AccountingException('القيد الافتتاحي مُسجّل بالفعل — اعكسه أولًا لو محتاج تعدّله.');
        }

        $firstEntryDate = JournalEntry::min('date');

        if ($firstEntryDate && $date > (string) $firstEntryDate) {
            throw new AccountingException("تاريخ القيد الافتتاحي ({$date}) لازم يكون قبل أو يساوي أول قيد في الدفاتر ({$firstEntryDate}).");
        }

        $lines = [];
        $debitTotal = 0.0;
        $creditTotal = 0.0;

        foreach ($balances as $code => $amount) {
            $code = (string) $code;
            $amount = round((float) $amount, 2);

            if ($amount == 0.0) {
                continue;
            }

            $account = Account::where('code', $code)->first();

            if (! $account) {
                throw new AccountingException("الحساب {$code} غير موجود في شجرة الحسابات.");
            }

            if (! $account->is_postable || ! $account->is_active || $account->children()->exists()) {
                throw new AccountingException("الحساب {$code} ({$account->name}) حساب إجمالي/مراقبة — أدخل الرصيد على حساباته التفصيلية.");
            }

            $isDebit = ($account->nature === AccountNature::Debit) === ($amount > 0);
            $lines[] = ['code' => $code, 'name' => $account->name, 'debit' => $isDebit ? abs($amount) : 0.0, 'credit' => $isDebit ? 0.0 : abs($amount)];
            $isDebit ? $debitTotal += abs($amount) : $creditTotal += abs($amount);
        }

        if ($lines === []) {
            throw new AccountingException('لا توجد أرصدة لإدخالها.');
        }

        if (abs(round($debitTotal - $creditTotal, 2)) >= 0.01) {
            throw new AccountingException(sprintf('الأرصدة الافتتاحية غير متوازنة: مدين %.2f ≠ دائن %.2f (الفرق %.2f).', $debitTotal, $creditTotal, $debitTotal - $creditTotal));
        }

        return $lines;
    }

    /**
     * @param  array<string, float|int|string>  $balances
     * @return array<int, array{code:string, name:string, debit:float, credit:float}>
     *
     * @throws AccountingException
     */
    public function post(string $date, array $balances): array
    {
        $lines = $this->preview($date, $balances);
        $clearingId = Account::where('code', self::CLEARING->value)->value('id');

        DB::transaction(function () use ($lines, $date, $clearingId) {
            foreach ($lines as $line) {
                if ($line['code'] === self::CLEARING->value) {
                    continue; // its balance is the residual of the other lines
                }

                $accountId = Account::where('code', $line['code'])->value('id');
                $isDebit = $line['debit'] > 0;

                $this->journalService->record([
                    'date' => $date,
                    'description' => "رصيد افتتاحي: {$line['code']} — {$line['name']}",
                    'debit_account_id' => $isDebit ? $accountId : $clearingId,
                    'credit_account_id' => $isDebit ? $clearingId : $accountId,
                    'amount' => $isDebit ? $line['debit'] : $line['credit'],
                    'source' => JournalSource::OPENING_BALANCE,
                    'reference' => 'OB-'.$date,
                    // Versioned so a reversed opening journal can be re-entered.
                    'idempotency_key' => "opening_balance:{$line['code']}:".JournalEntry::where('idempotency_key', 'like', "opening_balance:{$line['code']}:%")->count(),
                    'cost_center' => CostCenter::Admin,
                ]);
            }
        });

        return $lines;
    }
}
