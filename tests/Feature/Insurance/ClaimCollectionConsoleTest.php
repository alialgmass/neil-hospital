<?php

namespace Tests\Feature\Insurance;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Admin\Models\ActivityLog;
use Modules\Booking\Models\Booking;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceCompany;
use Modules\Insurance\Models\PendingDeposit;
use Modules\Inventory\Models\Service;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClaimCollectionConsoleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private InsuranceCompany $company;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(AccountsSeeder::class);

        $this->user = User::factory()->create();
        foreach (['insurance.view', 'insurance.collect'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $this->user->givePermissionTo($name);
        }
        $this->actingAs($this->user);

        $this->company = InsuranceCompany::create(['name' => 'جلوب ميد', 'status' => 'active']);
        $this->service = Service::create([
            'name' => 'عملية مياه بيضاء', 'dept' => 'surgery', 'price' => 15000,
            'center_type' => 'pct', 'center_val' => 40, 'center_share' => 6000, 'dr_share' => 9000,
        ]);
    }

    private function makeBooking(float $price = 1000): Booking
    {
        return Booking::create([
            'file_no' => 'MRN-'.uniqid(), 'patient_name' => 'مريض', 'dept' => 'surgery',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'ins_company_id' => $this->company->id,
            'visit_date' => now()->toDateString(), 'price' => $price, 'paid_amount' => 0,
            'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ]);
    }

    private function makeClaim(string $status = 'draft', float $amount = 1000, array $overrides = []): InsuranceClaim
    {
        $booking = $this->makeBooking($amount);

        return InsuranceClaim::create(array_merge([
            'booking_id' => $booking->id,
            'insurance_company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'patient_name' => 'مريض',
            'service_name' => $this->service->name,
            'invoice_amount' => $amount,
            'insurance_share' => $amount,
            'patient_share' => 0,
            'status' => $status,
            'service_date' => now()->toDateString(),
            'claim_date' => now()->toDateString(),
        ], $overrides));
    }

    private function advance(InsuranceClaim $claim)
    {
        return $this->post(route('insurance.collection.claims.advance', $claim->id));
    }

    public function test_advance_walks_the_whole_cycle_and_stamps_submission(): void
    {
        $claim = $this->makeClaim('draft');

        $this->advance($claim)->assertRedirect();
        $claim->refresh();
        $this->assertSame('submitted', (string) $claim->status);
        $this->assertNotNull($claim->submission_date);
        $this->assertNotNull($claim->claim_reference);

        $this->advance($claim);
        $this->assertSame('under_review', (string) $claim->refresh()->status);

        $this->advance($claim);
        $claim->refresh();
        $this->assertSame('approved', (string) $claim->status);
        $this->assertEquals(1000, $claim->approved_amount);
    }

    public function test_advance_refuses_to_pay_a_claim_without_collection(): void
    {
        $claim = $this->makeClaim('approved', 1000, ['approved_amount' => 1000]);

        $this->advance($claim)->assertSessionHasErrors('stage');
        $this->assertSame('approved', (string) $claim->refresh()->status);
    }

    public function test_paid_claim_can_be_closed_and_closed_is_terminal(): void
    {
        $claim = $this->makeClaim('paid', 1000, ['paid_amount' => 1000]);

        $this->advance($claim);
        $this->assertSame('closed', (string) $claim->refresh()->status);

        $this->advance($claim)->assertSessionHasErrors('stage');
    }

    public function test_deposit_needs_a_contract_booking_and_is_recorded_once(): void
    {
        $cash = $this->makeBooking();
        $cash->update(['ins_company_id' => null]);
        $this->post(route('insurance.collection.deposits.store'), ['booking_id' => $cash->id, 'amount' => 100])
            ->assertSessionHasErrors('booking_id');

        $booking = $this->makeBooking();
        $this->post(route('insurance.collection.deposits.store'), ['booking_id' => $booking->id, 'amount' => 100]);
        $this->post(route('insurance.collection.deposits.store'), ['booking_id' => $booking->id, 'amount' => 100])
            ->assertSessionHasErrors('booking_id');
        $this->assertSame(1, PendingDeposit::count());
    }

    public function test_approved_deposit_is_not_converted_when_booking_already_has_a_claim(): void
    {
        $booking = $this->makeBooking(1000);
        $this->post(route('insurance.collection.deposits.store'), ['booking_id' => $booking->id, 'amount' => 1000]);
        $deposit = PendingDeposit::sole();
        $this->post(route('insurance.collection.deposits.decision', $deposit->id), ['decision' => 'approved']);
        InsuranceClaim::create([
            'booking_id' => $booking->id, 'insurance_company_id' => $this->company->id, 'service_id' => $this->service->id,
            'patient_name' => 'مريض', 'service_name' => 'x', 'invoice_amount' => 1000, 'insurance_share' => 1000,
            'status' => 'draft', 'service_date' => now()->toDateString(), 'claim_date' => now()->toDateString(),
        ]);

        $this->post(route('insurance.collection.deposits.convert', $deposit->id))->assertSessionHasErrors('deposit');
        $this->assertSame(1, InsuranceClaim::where('booking_id', $booking->id)->count());
    }

    public function test_resubmission_keeps_the_original_submission_date(): void
    {
        $original = now()->subDays(40)->toDateString();
        $claim = $this->makeClaim('rejected', 1000, ['submission_date' => $original, 'claim_reference' => 'R-1']);

        $this->advance($claim);

        $this->assertSame($original, $claim->refresh()->submission_date->toDateString());
    }

    public function test_partially_rejected_collected_claim_does_not_raise_a_false_gap(): void
    {
        $this->makeClaim('paid', 1000, [
            'approved_amount' => 800, 'rejected_amount' => 200, 'paid_amount' => 800, 'deduction' => 0,
            'submission_date' => now()->subDays(5)->toDateString(),
        ]);

        $kinds = collect($this->get(route('insurance.collection.index'))->viewData('page')['props']['flags'])->pluck('kind');
        $this->assertNotContains('collection_gap', $kinds->all());
    }

    public function test_rejected_claim_can_be_resubmitted(): void
    {
        $claim = $this->makeClaim('rejected', 1000, ['rejection_reason' => 'x']);

        $this->advance($claim);
        $this->assertSame('submitted', (string) $claim->refresh()->status);
    }

    public function test_collection_records_net_deduction_collector_and_posts_journal(): void
    {
        $claim = $this->makeClaim('approved', 1000, ['approved_amount' => 1000]);

        $this->post(route('insurance.collection.claims.collect', $claim->id), [
            'amount' => 1000, 'discount' => 50,
        ])->assertSessionHasNoErrors();

        $claim->refresh();
        $this->assertSame('paid', (string) $claim->status);
        $this->assertEquals(950, $claim->paid_amount);
        $this->assertEquals(50, $claim->deduction);
        $this->assertSame($this->user->id, $claim->collected_by);
        $this->assertNotNull($claim->collected_at);
        $this->assertSame('paid', (string) $claim->booking->refresh()->pay_status->value);
        // Bank receives the 950 paid; the 50 gap is posted as tax (1080).
        $this->assertSame(2, JournalEntry::where('source', 'insurance_collect')->count());
        $this->assertEquals(50, (float) JournalEntry::where('source', 'insurance_collect')
            ->where('debit_account_id', Account::where('code', '1080')->value('id'))->sole()->amount);
        $this->assertTrue(ActivityLog::where('module', 'insurance_collection')->where('record_id', $claim->id)->exists());
    }

    public function test_discount_above_ten_percent_requires_reason(): void
    {
        $claim = $this->makeClaim('approved', 1000, ['approved_amount' => 1000]);

        $this->post(route('insurance.collection.claims.collect', $claim->id), [
            'amount' => 1000, 'discount' => 150,
        ])->assertSessionHasErrors('reason');
        $this->assertSame('approved', (string) $claim->refresh()->status);

        $this->post(route('insurance.collection.claims.collect', $claim->id), [
            'amount' => 1000, 'discount' => 150, 'reason' => 'خصم تعاقدي موثق',
        ])->assertSessionHasNoErrors();
        $this->assertEquals('خصم تعاقدي موثق', $claim->refresh()->deduction_reason);
    }

    public function test_collection_rejects_invalid_amounts_and_unapproved_claims(): void
    {
        $approved = $this->makeClaim('approved', 1000, ['approved_amount' => 1000]);

        $this->post(route('insurance.collection.claims.collect', $approved->id), ['amount' => -5, 'discount' => 0])
            ->assertSessionHasErrors('amount');
        $this->post(route('insurance.collection.claims.collect', $approved->id), ['amount' => 100, 'discount' => 200, 'reason' => 'x'])
            ->assertSessionHasErrors('discount');

        $review = $this->makeClaim('under_review');
        $this->post(route('insurance.collection.claims.collect', $review->id), ['amount' => 1000, 'discount' => 0])
            ->assertSessionHasErrors('stage');
    }

    public function test_collection_is_idempotent(): void
    {
        $claim = $this->makeClaim('approved', 1000, ['approved_amount' => 1000]);
        $payload = ['amount' => 1000, 'discount' => 0];

        $this->post(route('insurance.collection.claims.collect', $claim->id), $payload);
        $this->post(route('insurance.collection.claims.collect', $claim->id), $payload)->assertSessionHasErrors('stage');

        $this->assertSame(1, JournalEntry::where('source', 'insurance_collect')->count());
    }

    public function test_actions_require_collect_permission(): void
    {
        $claim = $this->makeClaim('draft');
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('insurance.view');

        $this->actingAs($viewer)->post(route('insurance.collection.claims.advance', $claim->id))->assertForbidden();
        $this->actingAs($viewer)->get(route('insurance.collection.index'))->assertOk();
    }

    public function test_deposit_receipt_is_a_liability_and_conversion_creates_one_claim_and_refunds(): void
    {
        $booking = $this->makeBooking(15000);

        $this->post(route('insurance.collection.deposits.store'), [
            'booking_id' => $booking->id, 'amount' => 15000,
        ])->assertSessionHasNoErrors();

        $deposit = PendingDeposit::sole();
        $this->assertSame('waiting', $deposit->decision->value);
        $advances = Account::where('code', '2050')->firstOrFail();
        $this->assertEquals(15000.00, (float) $advances->fresh()->balance);

        // not convertible before approval
        $this->post(route('insurance.collection.deposits.convert', $deposit->id))->assertSessionHasErrors('deposit');

        $this->post(route('insurance.collection.deposits.decision', $deposit->id), ['decision' => 'approved']);
        $this->post(route('insurance.collection.deposits.convert', $deposit->id))->assertSessionHasNoErrors();

        $deposit->refresh();
        $this->assertTrue($deposit->converted);
        $this->assertTrue($deposit->refunded);
        $this->assertSame(1, InsuranceClaim::where('booking_id', $booking->id)->count());
        $this->assertEquals(0.00, (float) $advances->fresh()->balance);

        // second conversion refused, no extra claim
        $this->post(route('insurance.collection.deposits.convert', $deposit->id))->assertSessionHasErrors('deposit');
        $this->assertSame(1, InsuranceClaim::where('booking_id', $booking->id)->count());
    }

    public function test_rejected_deposit_is_reclassified_to_revenue_and_not_refunded(): void
    {
        $booking = $this->makeBooking(600);
        $this->post(route('insurance.collection.deposits.store'), ['booking_id' => $booking->id, 'amount' => 600]);
        $deposit = PendingDeposit::sole();

        $this->post(route('insurance.collection.deposits.decision', $deposit->id), ['decision' => 'rejected']);

        $this->assertEquals(0.00, (float) Account::where('code', '2050')->firstOrFail()->fresh()->balance);
        $this->assertEquals(600.00, (float) Account::where('code', '4030')->firstOrFail()->fresh()->balance);
        $this->assertFalse($deposit->refresh()->refunded);
        $this->post(route('insurance.collection.deposits.convert', $deposit->id))->assertSessionHasErrors('deposit');

        // a decision can only be taken once
        $this->post(route('insurance.collection.deposits.decision', $deposit->id), ['decision' => 'approved'])
            ->assertSessionHasErrors('deposit');
    }

    public function test_console_page_exposes_dashboard_ageing_and_flags(): void
    {
        $this->makeClaim('draft', 500);
        $this->makeClaim('approved', 2000, [
            'approved_amount' => 2000, 'submission_date' => now()->subDays(100)->toDateString(),
        ]);
        $this->makeClaim('submitted', 1000, [
            'submission_date' => now()->subDays(10)->toDateString(),
        ]);
        $paid = $this->makeClaim('paid', 3000, [
            'paid_amount' => 3000, 'submission_date' => now()->subDays(40)->toDateString(),
        ]);
        $this->makeBooking(300); // reception case with no claim, same-day

        $this->get(route('insurance.collection.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('insurance/Collection')
                ->where('counts.total', 4)
                ->where('counts.made', 3)
                ->where('counts.not_made', 1)
                ->where('counts.open', 2)
                ->where('money.collected', 3000)
                ->where('money.outstanding', 3000)
                ->where('stages.draft.count', 1)
                ->where('stages.approved.amount', 2000)
                ->where('companies.0.ageing.over_90', 2000)
                ->where('companies.0.ageing.within_30', 1000)
                ->where('reception.0.status', 'ready')
                ->has('flags', fn (Assert $flags) => $flags->etc())
                ->has('log')
            );

        $this->assertNotNull($paid);
    }

    public function test_audit_flags_fire_on_their_triggers_only(): void
    {
        // approved >15 days, uncollected -> high; older than 90 -> medium
        $this->makeClaim('approved', 2000, [
            'approved_amount' => 2000, 'submission_date' => now()->subDays(100)->toDateString(),
        ]);
        // collected with big unexplained deduction
        $this->makeClaim('paid', 1000, [
            'approved_amount' => 1000, 'paid_amount' => 700, 'deduction' => 200,
            'submission_date' => now()->subDays(30)->toDateString(),
        ]);
        // clean claim — must raise nothing
        $this->makeClaim('submitted', 1000, ['submission_date' => now()->subDays(5)->toDateString()]);

        $flags = collect(
            $this->get(route('insurance.collection.index'))->viewData('page')['props']['flags']
        );

        $kinds = $flags->pluck('kind')->all();
        $this->assertContains('approved_uncollected', $kinds);
        $this->assertContains('overdue_90', $kinds);
        $this->assertContains('deduction_high', $kinds);
        $this->assertContains('collection_gap', $kinds);
        $this->assertNotContains('rejection_high', $kinds);
        $this->assertNotContains('duplicate', $kinds);
        $this->assertSame('high', $flags->first()['severity']);
    }
}
