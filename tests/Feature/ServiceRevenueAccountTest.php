<?php

namespace Tests\Feature;

use App\Enums\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\AutoPostBookingPaymentAction;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Enums\AccountNature;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Tests\TestCase;

class ServiceRevenueAccountTest extends TestCase
{
    use RefreshDatabase;

    // The migration seeds accounts 2500, 2600, 2700. Retrieve them by code.
    private function account(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }

    private function createAccount(string $code, string $name, AccountGroup $group = AccountGroup::Revenues): Account
    {
        return Account::create([
            'code' => $code,
            'name' => $name,
            'group' => $group,
            'nature' => AccountNature::Credit,
            'balance' => 0,
            'is_active' => true,
        ]);
    }

    private function makeBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'file_no' => 'T'.rand(100, 999),
            'patient_name' => 'مريض تجريبي',
            'dept' => Department::Laser,
            'service_name' => 'ليزر',
            'service_id' => null,
            'price' => 1000,
            'paid_amount' => 1000,
            'ins_amount' => 0,
            'discount' => 0,
            'pay_method' => 'cash',
            'pay_status' => 'paid',
            'visit_date' => now()->toDateString(),
        ], $overrides));
    }

    public function test_service_can_store_revenue_account_id(): void
    {
        $account = $this->account('2500');

        $service = Service::create([
            'name' => 'ليزر شبكية',
            'dept' => 'laser',
            'price' => 2000,
            'center_type' => 'pct',
            'center_val' => 40,
            'center_share' => 800,
            'dr_share' => 1200,
            'revenue_account_id' => $account->id,
        ]);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'revenue_account_id' => $account->id,
        ]);

        $this->assertEquals($account->id, $service->fresh()->revenue_account_id);
    }

    public function test_service_revenue_account_relationship(): void
    {
        $account = $this->account('2600');

        $service = Service::create([
            'name' => 'كشف تأمين',
            'dept' => 'clinic',
            'price' => 500,
            'center_type' => 'pct',
            'center_val' => 60,
            'center_share' => 300,
            'dr_share' => 200,
            'revenue_account_id' => $account->id,
        ]);

        $this->assertEquals($account->name, $service->revenueAccount->name);
        $this->assertEquals('2600', $service->revenueAccount->code);
    }

    public function test_auto_post_uses_service_revenue_account_when_set(): void
    {
        // Seed cash and dept-level accounts
        $cashAccount = $this->createAccount('1000', 'الصندوق', AccountGroup::Assets);
        $this->createAccount('2400', 'إيرادات الليزر');

        // Use the migration-seeded account 2500 as the service-specific account
        $specificRevenueAccount = $this->account('2500');

        $service = Service::create([
            'name' => 'ليزر شبكية',
            'dept' => 'laser',
            'price' => 1000,
            'center_type' => 'pct',
            'center_val' => 40,
            'center_share' => 400,
            'dr_share' => 600,
            'revenue_account_id' => $specificRevenueAccount->id,
        ]);

        $booking = $this->makeBooking(['service_id' => $service->id]);

        app(AutoPostBookingPaymentAction::class)->execute($booking, 1000);

        $entry = JournalEntry::where('credit_account_id', $specificRevenueAccount->id)->first();
        $this->assertNotNull($entry, 'Journal entry should credit the service-specific revenue account');
        $this->assertEquals(1000, (float) $entry->amount);

        // The dept-level account should NOT have been credited
        $deptAccount = Account::where('code', '2400')->first();
        $this->assertNull(
            JournalEntry::where('credit_account_id', $deptAccount->id)->first(),
            'Dept-level account should not be credited when service has a specific revenue account',
        );
    }

    public function test_auto_post_falls_back_to_dept_account_when_service_has_no_revenue_account(): void
    {
        $this->createAccount('1000', 'الصندوق', AccountGroup::Assets);
        $deptAccount = $this->createAccount('2400', 'إيرادات الليزر');

        $service = Service::create([
            'name' => 'ليزر',
            'dept' => 'laser',
            'price' => 1000,
            'center_type' => 'pct',
            'center_val' => 40,
            'center_share' => 400,
            'dr_share' => 600,
            'revenue_account_id' => null,
        ]);

        $booking = $this->makeBooking(['service_id' => $service->id]);

        app(AutoPostBookingPaymentAction::class)->execute($booking, 1000);

        $entry = JournalEntry::where('credit_account_id', $deptAccount->id)->first();
        $this->assertNotNull($entry, 'Journal entry should fall back to dept revenue account');
    }

    public function test_auto_post_falls_back_to_dept_account_when_no_service_id(): void
    {
        $this->createAccount('1000', 'الصندوق', AccountGroup::Assets);
        $deptAccount = $this->createAccount('2400', 'إيرادات الليزر');

        $booking = $this->makeBooking(['service_id' => null]);

        app(AutoPostBookingPaymentAction::class)->execute($booking, 1000);

        $entry = JournalEntry::where('credit_account_id', $deptAccount->id)->first();
        $this->assertNotNull($entry, 'Journal entry should use dept account when no service linked');
    }
}
