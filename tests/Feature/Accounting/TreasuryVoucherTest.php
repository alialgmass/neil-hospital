<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Accounting\Models\TreasuryEntry;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TreasuryVoucherTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    private User $writer;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'treasury.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'treasury.write', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'treasury_viewer', 'guard_name' => 'web']);
        $role->givePermissionTo('treasury.view');
        $this->viewer = User::factory()->create();
        $this->viewer->assignRole($role);

        $writerRole = Role::firstOrCreate(['name' => 'treasury_writer', 'guard_name' => 'web']);
        $writerRole->givePermissionTo(['treasury.view', 'treasury.write']);
        $this->writer = User::factory()->create();
        $this->writer->assignRole($writerRole);
    }

    private function makeEntry(string $type): TreasuryEntry
    {
        return TreasuryEntry::create([
            'type' => $type,
            'description' => 'بيان تجريبي',
            'amount' => 750,
            'date' => '2026-10-05',
            'beneficiary' => 'أحمد محمد',
            'reference_no' => 'REF-1',
            'source' => 'manual',
        ]);
    }

    public function test_inflow_renders_a_receipt_voucher(): void
    {
        $entry = $this->makeEntry('in');

        $this->actingAs($this->viewer)
            ->get("/treasury/{$entry->id}/voucher")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('treasury/Voucher')
                ->where('voucher.title', 'إيصال استلام')
                ->where('voucher.party_label', 'استلمنا من السيد/ة')
                ->where('voucher.party', 'أحمد محمد')
                ->where('voucher.amount', 750)
                ->where('voucher.is_reversed', false)
                ->where('voucher.voucher_no', fn (string $no) => str_starts_with($no, 'RCV-20261005-')));
    }

    public function test_outflow_renders_a_payment_voucher(): void
    {
        $entry = $this->makeEntry('out');

        $this->actingAs($this->viewer)
            ->get("/treasury/{$entry->id}/voucher")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('voucher.title', 'إيصال صرف')
                ->where('voucher.party_label', 'صرفنا إلى السيد/ة')
                ->where('voucher.voucher_no', fn (string $no) => str_starts_with($no, 'PAY-20261005-')));
    }

    public function test_voucher_requires_treasury_view_permission(): void
    {
        $entry = $this->makeEntry('in');

        $this->actingAs(User::factory()->create())
            ->get("/treasury/{$entry->id}/voucher")
            ->assertForbidden();
    }

    public function test_unknown_entry_returns_not_found(): void
    {
        $this->actingAs($this->viewer)
            ->get('/treasury/01JZZZZZZZZZZZZZZZZZZZZZZZ/voucher')
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function voucherPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'in',
            'beneficiary' => 'سعد إبراهيم',
            'description' => 'سداد جزء من حساب',
            'amount' => 1200,
            'date' => '2026-10-06',
            'reference_no' => 'R-77',
        ], $overrides);
    }

    public function test_issuing_a_receipt_voucher_records_an_inflow_and_opens_the_voucher(): void
    {
        $response = $this->actingAs($this->writer)->post('/treasury/vouchers', $this->voucherPayload());

        $entry = TreasuryEntry::firstOrFail();
        $response->assertRedirect("/treasury/{$entry->id}/voucher");

        $this->assertSame('in', $entry->type->value);
        $this->assertSame('سعد إبراهيم', $entry->beneficiary);
        $this->assertSame('manual', $entry->source);
        $this->assertEquals(1200.0, (float) $entry->amount);
    }

    public function test_issuing_a_payment_voucher_records_an_outflow(): void
    {
        $this->actingAs($this->writer)->post('/treasury/vouchers', $this->voucherPayload(['type' => 'out']));

        $this->assertSame('out', TreasuryEntry::firstOrFail()->type->value);
    }

    public function test_voucher_requires_party_amount_and_description(): void
    {
        $this->actingAs($this->writer)
            ->post('/treasury/vouchers', $this->voucherPayload(['beneficiary' => '', 'amount' => 0, 'description' => '']))
            ->assertSessionHasErrors(['beneficiary', 'amount', 'description']);

        $this->assertSame(0, TreasuryEntry::count());
    }

    public function test_issuing_a_voucher_requires_treasury_write_permission(): void
    {
        $this->actingAs($this->viewer)
            ->post('/treasury/vouchers', $this->voucherPayload())
            ->assertForbidden();

        $this->assertSame(0, TreasuryEntry::count());
    }
}
