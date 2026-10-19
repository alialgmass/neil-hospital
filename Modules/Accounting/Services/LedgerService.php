<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\AccountNature;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Enums\Subledger;
use Modules\Accounting\Models\Account;
use Modules\Inventory\Models\ItemSalesInvoice;
use Modules\Inventory\Models\PurchaseInvoice;

class LedgerService
{
    /**
     * Trial balance: all accounts with their debit/credit totals and running balance.
     *
     * A parent account (e.g. the control accounts 2010 / 2020 / 2030, or 1030)
     * is never posted to directly; its row shows the roll-up of its
     * children and is flagged `is_group` so totals are footed on leaf rows
     * only. Sub-ledger party accounts with no movement in the period are
     * omitted to keep the report readable.
     *
     * @return array<int, array{code:string, name:string, group:mixed, nature:mixed, debits:float, credits:float, balance:float, is_group:bool, parent_code:?string}>
     */
    public function trialBalance(?string $from = null, ?string $to = null): array
    {
        $accounts = Account::where('is_active', true)
            ->moduleEnabled()
            ->orderBy('code')
            ->get()
            ->keyBy('id');

        $debitTotals = $this->sumsBySide('debit_account_id', $from, $to);
        $creditTotals = $this->sumsBySide('credit_account_id', $from, $to);

        $childrenOf = $accounts->groupBy('parent_id');
        $controlIds = $accounts->filter(fn (Account $account) => Subledger::forMasterCode($account->code) !== null)->keys()->all();

        $rollup = function (string $id) use (&$rollup, $childrenOf, $debitTotals, $creditTotals): array {
            $debits = (float) ($debitTotals[$id] ?? 0);
            $credits = (float) ($creditTotals[$id] ?? 0);

            foreach ($childrenOf->get($id, collect()) as $child) {
                [$childDebits, $childCredits] = $rollup($child->id);
                $debits += $childDebits;
                $credits += $childCredits;
            }

            return [$debits, $credits];
        };

        return $accounts->map(function (Account $account) use ($rollup, $childrenOf, $controlIds, $accounts) {
            [$debits, $credits] = $rollup($account->id);
            $isGroup = $childrenOf->has($account->id);

            if (! $isGroup && in_array($account->parent_id, $controlIds, true) && $debits == 0.0 && $credits == 0.0) {
                return null;
            }

            return [
                'code' => $account->code,
                'name' => $account->name,
                'group' => $account->group,
                'nature' => $account->nature,
                'debits' => round($debits, 2),
                'credits' => round($credits, 2),
                'balance' => round($account->nature === AccountNature::Debit ? $debits - $credits : $credits - $debits, 2),
                'is_group' => $isGroup,
                'parent_code' => $account->parent_id ? $accounts->get($account->parent_id)?->code : null,
            ];
        })->filter()->values()->toArray();
    }

    /** @return array<int, string> */
    private function selfAndDescendantIds(string $accountId): array
    {
        $ids = [$accountId];
        $frontier = [$accountId];

        while ($frontier !== []) {
            $frontier = Account::whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }

    /** @return Collection<string, float> account id => summed amount */
    private function sumsBySide(string $column, ?string $from, ?string $to): Collection
    {
        return DB::table('journal_entries')
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->groupBy($column)
            ->selectRaw("{$column} as account_id, SUM(amount) as total")
            ->pluck('total', 'account_id');
    }

    /**
     * Account statement: all journal movements for a single account.
     */
    public function accountStatement(string $accountId, ?string $from = null, ?string $to = null): array
    {
        $account = Account::findOrFail($accountId);

        // A parent / control account's statement is the movement of all its sub-accounts.
        $accountIds = $this->selfAndDescendantIds($accountId);

        $debitRows = DB::table('journal_entries')
            ->whereIn('debit_account_id', $accountIds)
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->select('date', 'description', 'amount as debit', DB::raw('0 as credit'), 'reference', 'source', 'created_at')
            ->get();

        $creditRows = DB::table('journal_entries')
            ->whereIn('credit_account_id', $accountIds)
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->select('date', 'description', DB::raw('0 as debit'), 'amount as credit', 'reference', 'source', 'created_at')
            ->get();

        $rows = $debitRows->concat($creditRows)
            ->sortBy(['date', 'created_at'])
            ->values();

        $invoiceLines = $this->invoiceLines($rows);

        $runningBalance = 0.0;
        $statement = $rows->map(function ($row) use (&$runningBalance, $account, $invoiceLines) {
            $debit = (float) $row->debit;
            $credit = (float) $row->credit;

            if ($account->nature === AccountNature::Debit) {
                $runningBalance += $debit - $credit;
            } else {
                $runningBalance += $credit - $debit;
            }

            return [
                'date' => $row->date,
                'description' => $row->description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $runningBalance,
                'reference' => $row->reference,
                'lines' => $this->isInvoiceHeaderRow($row) ? ($invoiceLines[$row->source][$row->reference] ?? []) : [],
            ];
        });

        return [
            'account' => ['code' => $account->code, 'name' => $account->name],
            'statement' => $statement->toArray(),
        ];
    }

    /**
     * Purchase / item-sale postings that carry an invoice number; the per-line
     * cost entries of a sale are excluded since their بيان already names the item.
     */
    private function isInvoiceHeaderRow(object $row): bool
    {
        return in_array($row->source, [JournalSource::PURCHASE->value, JournalSource::ITEM_SALE->value], true)
            && filled($row->reference)
            && ! str_starts_with($row->description, 'تكلفة بضاعة مباعة');
    }

    /**
     * Item lines of the invoices referenced by the statement rows.
     *
     * @param  Collection<int, object>  $rows
     * @return array<string, array<string, array<int, array{name:string, qty:float, unit_price:float, total:float}>>>
     */
    private function invoiceLines(Collection $rows): array
    {
        $referencesFor = fn (JournalSource $source) => $rows
            ->filter(fn ($row) => $this->isInvoiceHeaderRow($row) && $row->source === $source->value)
            ->pluck('reference')
            ->unique()
            ->values();

        $lines = [];

        $purchaseNumbers = $referencesFor(JournalSource::PURCHASE);
        if ($purchaseNumbers->isNotEmpty()) {
            $lines[JournalSource::PURCHASE->value] = PurchaseInvoice::whereIn('invoice_no', $purchaseNumbers)
                ->with('items')
                ->get()
                ->mapWithKeys(fn (PurchaseInvoice $invoice) => [
                    $invoice->invoice_no => $invoice->items->map(fn ($item) => [
                        'name' => $item->item_name,
                        'qty' => (float) $item->qty,
                        'unit_price' => (float) $item->unit_cost,
                        'total' => (float) $item->total,
                    ])->all(),
                ])->all();
        }

        $saleNumbers = $referencesFor(JournalSource::ITEM_SALE);
        if ($saleNumbers->isNotEmpty()) {
            $lines[JournalSource::ITEM_SALE->value] = ItemSalesInvoice::whereIn('invoice_no', $saleNumbers)
                ->with('items')
                ->get()
                ->mapWithKeys(fn (ItemSalesInvoice $invoice) => [
                    $invoice->invoice_no => $invoice->items->map(fn ($item) => [
                        'name' => $item->item_name,
                        'qty' => (float) $item->qty,
                        'unit_price' => (float) $item->unit_price,
                        'total' => (float) $item->line_total,
                    ])->all(),
                ])->all();
        }

        return $lines;
    }
}
