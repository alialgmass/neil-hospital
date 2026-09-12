<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DevelopmentTreasuryFeeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['booking.create', 'booking.pay'] as $p) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->service = Service::create([
            'name' => 'كشف عام', 'dept' => 'clinic', 'price' => 300,
            'center_type' => 'pct', 'center_val' => 40, 'center_share' => 120, 'dr_share' => 180,
            'dev_treasury_fee' => 50,
        ]);
    }

    private function developmentFundId(): string
    {
        return Account::where('code', '1011')->value('id');
    }

    private function cashId(): string
    {
        return Account::where('code', '1010')->value('id');
    }

    private function createBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'file_no' => 'MRN-'.uniqid(),
            'patient_name' => 'محمد علي',
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
            'service_id' => $this->service->id,
            'service_name' => $this->service->name,
            'price' => 0,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 0,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
            'created_by' => $this->user->id,
        ], $overrides));
    }

    public function test_cash_payment_for_a_service_with_dev_fee_posts_a_treasury_transfer(): void
    {
        $booking = $this->createBooking();

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 300, 'paid_amount' => 300, 'pay_method' => 'cash',
        ]);

        $entry = JournalEntry::where('reference', $booking->file_no)
            ->where('idempotency_key', "development_fee:{$booking->file_no}")
            ->sole();

        $this->assertSame($this->developmentFundId(), $entry->debit_account_id);
        $this->assertSame($this->cashId(), $entry->credit_account_id);
        $this->assertEquals(50.0, (float) $entry->amount);

        // Revenue entry is untouched — still posted for the full price.
        $revenueEntry = JournalEntry::where('reference', $booking->file_no)
            ->where('idempotency_key', "booking_payment:{$booking->file_no}:300")
            ->sole();
        $this->assertEquals(300.0, (float) $revenueEntry->amount);
    }

    public function test_creating_an_already_paid_cash_booking_also_posts_the_dev_fee(): void
    {
        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض', 'dept' => 'clinic', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'price' => 300, 'paid_amount' => 300,
            'pay_method' => 'cash', 'pay_status' => 'paid', 'status' => 'waiting',
        ])->assertRedirect();

        $booking = Booking::latest('id')->first();

        $entry = JournalEntry::where('reference', $booking->file_no)
            ->where('idempotency_key', "development_fee:{$booking->file_no}")
            ->sole();

        $this->assertEquals(50.0, (float) $entry->amount);
    }

    public function test_card_payment_does_not_post_a_dev_fee_transfer(): void
    {
        $booking = $this->createBooking();

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 300, 'paid_amount' => 300, 'pay_method' => 'card',
        ]);

        $this->assertSame(0, JournalEntry::where('idempotency_key', "development_fee:{$booking->file_no}")->count());
    }

    public function test_service_without_dev_fee_posts_nothing(): void
    {
        $plainService = Service::create([
            'name' => 'أشعة', 'dept' => 'clinic', 'price' => 200,
            'center_type' => 'pct', 'center_val' => 40, 'center_share' => 80, 'dr_share' => 120,
        ]);

        $booking = $this->createBooking(['service_id' => $plainService->id, 'service_name' => $plainService->name]);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 200, 'paid_amount' => 200, 'pay_method' => 'cash',
        ]);

        $this->assertSame(0, JournalEntry::where('idempotency_key', "development_fee:{$booking->file_no}")->count());
    }

    public function test_two_installments_post_the_dev_fee_only_once(): void
    {
        $booking = $this->createBooking();

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 300, 'paid_amount' => 100, 'pay_method' => 'cash',
        ]);
        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 300, 'paid_amount' => 200, 'pay_method' => 'cash',
        ]);

        $this->assertSame(1, JournalEntry::where('idempotency_key', "development_fee:{$booking->file_no}")->count());
    }
}
