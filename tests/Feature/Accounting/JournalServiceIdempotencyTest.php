<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Exceptions\AccountingException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\JournalService;
use Tests\TestCase;

class JournalServiceIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccount(array $attrs = []): Account
    {
        return Account::create(array_merge([
            'code' => uniqid('ACC'),
            'name' => 'Test Account',
            'group' => 'assets',
            'nature' => 'debit',
        ], $attrs));
    }

    public function test_record_with_same_idempotency_key_is_a_no_op(): void
    {
        $this->actingAs(User::factory()->create());

        $debit = $this->makeAccount(['nature' => 'debit', 'group' => 'assets']);
        $credit = $this->makeAccount(['nature' => 'credit', 'group' => 'liabilities']);

        $service = app(JournalService::class);

        $data = [
            'date' => '2026-05-20',
            'description' => 'Test',
            'debit_account_id' => $debit->id,
            'credit_account_id' => $credit->id,
            'amount' => 500,
            'source' => 'manual',
            'idempotency_key' => 'dup-key-1',
        ];

        $first = $service->record($data);
        $second = $service->record($data);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, JournalEntry::count());
        // Balance only adjusted once
        $this->assertEquals(500.00, (float) $debit->fresh()->balance);
    }

    public function test_record_rejects_non_postable_account(): void
    {
        $this->actingAs(User::factory()->create());

        $parent = $this->makeAccount(['is_postable' => false]);
        $credit = $this->makeAccount(['nature' => 'credit', 'group' => 'liabilities']);

        $this->expectException(AccountingException::class);

        app(JournalService::class)->record([
            'date' => '2026-05-20',
            'description' => 'Test',
            'debit_account_id' => $parent->id,
            'credit_account_id' => $credit->id,
            'amount' => 500,
            'source' => 'manual',
        ]);
    }

    public function test_record_rejects_same_account_on_both_sides(): void
    {
        $this->actingAs(User::factory()->create());

        $account = $this->makeAccount();

        $this->expectException(AccountingException::class);

        app(JournalService::class)->record([
            'date' => '2026-05-20',
            'description' => 'Test',
            'debit_account_id' => $account->id,
            'credit_account_id' => $account->id,
            'amount' => 500,
            'source' => 'manual',
        ]);
    }

    public function test_record_rejects_zero_amount(): void
    {
        $this->actingAs(User::factory()->create());

        $debit = $this->makeAccount(['nature' => 'debit']);
        $credit = $this->makeAccount(['nature' => 'credit', 'group' => 'liabilities']);

        $this->expectException(AccountingException::class);

        app(JournalService::class)->record([
            'date' => '2026-05-20',
            'description' => 'Test',
            'debit_account_id' => $debit->id,
            'credit_account_id' => $credit->id,
            'amount' => 0,
            'source' => 'manual',
        ]);
    }

    public function test_reverse_uses_original_amount_and_swapped_accounts(): void
    {
        $this->actingAs(User::factory()->create());

        $debit = $this->makeAccount(['nature' => 'debit', 'group' => 'assets']);
        $credit = $this->makeAccount(['nature' => 'credit', 'group' => 'revenues']);

        $service = app(JournalService::class);

        $original = $service->record([
            'date' => '2026-05-20',
            'description' => 'Original',
            'debit_account_id' => $debit->id,
            'credit_account_id' => $credit->id,
            'amount' => 750.00,
            'source' => JournalSource::BOOKING,
            'reference' => 'FILE-1',
        ]);

        $reversal = $service->reverse($original, JournalSource::REVERSAL, 'REV-FILE-1');

        $this->assertNotNull($reversal);
        $this->assertEquals(750.00, (float) $reversal->amount);
        $this->assertSame($credit->id, $reversal->debit_account_id);
        $this->assertSame($debit->id, $reversal->credit_account_id);
        $this->assertNotNull($original->fresh()->reversed_at);
        $this->assertSame($original->id, $reversal->reversal_of_id);

        // Balances net back to zero
        $this->assertEquals(0.00, (float) $debit->fresh()->balance);
        $this->assertEquals(0.00, (float) $credit->fresh()->balance);
    }

    public function test_reverse_is_idempotent_and_prevents_double_reversal(): void
    {
        $this->actingAs(User::factory()->create());

        $debit = $this->makeAccount(['nature' => 'debit', 'group' => 'assets']);
        $credit = $this->makeAccount(['nature' => 'credit', 'group' => 'revenues']);

        $service = app(JournalService::class);

        $original = $service->record([
            'date' => '2026-05-20',
            'description' => 'Original',
            'debit_account_id' => $debit->id,
            'credit_account_id' => $credit->id,
            'amount' => 400.00,
            'source' => JournalSource::BOOKING,
            'reference' => 'FILE-2',
        ]);

        $first = $service->reverse($original, JournalSource::REVERSAL, 'REV-FILE-2');
        $second = $service->reverse($original->fresh(), JournalSource::REVERSAL, 'REV-FILE-2');

        $this->assertNotNull($first);
        $this->assertNull($second, 'A second reversal attempt on an already-reversed entry must no-op');
        $this->assertSame(2, JournalEntry::count(), 'Only the original + one reversal should exist');
    }
}
