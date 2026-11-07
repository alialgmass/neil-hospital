<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Accounting\Enums\Subledger;
use Modules\Accounting\Exceptions\AccountingException;

/**
 * Resolves the sub-ledger account a doctor / supplier / employee posts to,
 * under the non-postable control accounts 2010 / 2020 / 2030.
 *
 * Resolution order for a party with no account yet:
 *   1. the guide's pre-numbered account for the same name (e.g. 2213
 *      "دائنون — دكتور شريف صابر"), matched on a normalized name, if no other
 *      party has claimed it;
 *   2. otherwise a new account on the next free code of the party's range.
 * The link is persisted on the party row (`payable_account_id`), so a party
 * keeps its account for life even if it is renamed.
 */
class SubledgerAccountResolver
{
    /** Titles stripped before comparing names: "دكتور احمد" ≡ "د. احمد" ≡ "احمد". */
    private const TITLES = ['دكتوره', 'دكتورة', 'دكتور', 'الدكتوره', 'الدكتورة', 'الدكتور', 'د.', 'د/', 'د', 'dr.', 'dr'];

    public function forDoctor(string $doctorId): string
    {
        return $this->resolve(Subledger::Doctor, $doctorId);
    }

    public function forSupplier(string $supplierId): string
    {
        return $this->resolve(Subledger::Supplier, $supplierId);
    }

    public function forEmployee(string $employeeId): string
    {
        return $this->resolve(Subledger::Employee, $employeeId);
    }

    public function resolve(Subledger $subledger, string $partyId): string
    {
        $table = $subledger->table();
        $existing = DB::table($table)->where('id', $partyId)->value('payable_account_id');

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($subledger, $table, $partyId) {
            $party = DB::table($table)->where('id', $partyId)->lockForUpdate()->first(['id', 'name', 'payable_account_id']);

            if (! $party) {
                throw new AccountingException("لا يمكن تحديد الحساب التفصيلي: {$table} #{$partyId} غير موجود.");
            }

            if ($party->payable_account_id) {
                return $party->payable_account_id;
            }

            $accountId = $this->matchGuideAccount($subledger, (string) $party->name)
                ?? $this->createAccount($subledger, (string) $party->name);

            DB::table($table)->where('id', $partyId)->update(['payable_account_id' => $accountId]);

            return $accountId;
        });
    }

    /**
     * The "unidentified — for review" bucket (2299 / 2399 / 2499) that
     * historical lines with no traceable owner are parked on.
     */
    public function unassignedAccountId(Subledger $subledger): string
    {
        $code = (string) $subledger->unassignedCode();
        $id = DB::table('accounts')->where('code', $code)->value('id');

        return $id ?? $this->insertAccount($subledger, $code, $subledger->unassignedName());
    }

    public static function normalizeName(string $name): string
    {
        foreach (Subledger::cases() as $subledger) {
            if (str_starts_with($name, $subledger->namePrefix())) {
                $name = mb_substr($name, mb_strlen($subledger->namePrefix()));
            }
        }

        $name = mb_strtolower(trim($name));
        $name = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $name); // tashkeel + tatweel
        $name = strtr($name, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي']);
        $name = preg_replace('/\s+/u', ' ', $name);

        foreach (self::TITLES as $title) {
            $normalizedTitle = strtr($title, ['ة' => 'ه']);

            if (str_starts_with($name, $normalizedTitle.' ') || str_starts_with($name, $normalizedTitle.'.')) {
                $name = trim(mb_substr($name, mb_strlen($normalizedTitle)), " .\t");

                break;
            }
        }

        // "عبد الله" ≡ "عبدالله"
        return str_replace('عبد ', 'عبد', trim($name));
    }

    private function matchGuideAccount(Subledger $subledger, string $partyName): ?string
    {
        $target = self::normalizeName($partyName);

        if ($target === '') {
            return null;
        }

        $masterId = $this->masterId($subledger);
        $claimed = DB::table($subledger->table())->whereNotNull('payable_account_id')->pluck('payable_account_id')->all();

        $candidates = DB::table('accounts')
            ->where('parent_id', $masterId)
            ->where('code', '!=', (string) $subledger->unassignedCode())
            ->whereNotIn('id', $claimed ?: ['__none__'])
            ->orderBy('code')
            ->get(['id', 'name']);

        foreach ($candidates as $candidate) {
            if (self::normalizeName((string) $candidate->name) === $target) {
                return $candidate->id;
            }
        }

        return null;
    }

    private function createAccount(Subledger $subledger, string $partyName): string
    {
        $used = DB::table('accounts')
            ->whereBetween('code', [(string) $subledger->firstCode(), (string) $subledger->lastCode()])
            ->pluck('code')
            ->map(fn ($code) => (int) $code)
            ->all();

        for ($code = $subledger->firstCode(); $code <= $subledger->lastCode(); $code++) {
            if (! in_array($code, $used, true)) {
                return $this->insertAccount($subledger, (string) $code, $subledger->namePrefix().trim($partyName));
            }
        }

        throw new AccountingException("نطاق الحسابات التفصيلية تحت {$subledger->masterCode()->value} ممتلئ — أضف نطاقًا جديدًا في الدليل.");
    }

    private function insertAccount(Subledger $subledger, string $code, string $name): string
    {
        $id = (string) Str::ulid();

        DB::table('accounts')->insert([
            'id' => $id,
            'code' => $code,
            'name' => mb_substr($name, 0, 150),
            'group' => 'liabilities',
            'nature' => 'credit',
            'parent_id' => $this->masterId($subledger),
            'balance' => 0,
            'is_active' => true,
            'is_postable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function masterId(Subledger $subledger): string
    {
        $id = DB::table('accounts')->where('code', $subledger->masterCode()->value)->value('id');

        if (! $id) {
            throw new AccountingException("حساب المراقبة {$subledger->masterCode()->value} غير موجود في شجرة الحسابات.");
        }

        return $id;
    }
}
