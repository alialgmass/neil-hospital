<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\LedgerService;
use Modules\Booking\Models\Service;
use Modules\Doctor\Actions\UpdateDoctorAction;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\DoctorClaimsService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DoctorPaymentEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_paying_a_doctors_dues_from_the_claims_screen_clears_his_payable_in_the_trial_balance(): void
    {
        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['booking.create', 'drpayments.write', 'drpayments.view'] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $user = User::factory()->create();
        $user->assignRole($role);

        $service = Service::create(['name' => 'كشف', 'dept' => 'clinic', 'price' => 400]);
        $doctor = Doctor::create(['name' => 'د. أحمد', 'fee_type' => 'percentage', 'fee_value' => 50, 'is_active' => true]);

        $this->actingAs($user)->post('/booking', [
            'patient_name' => 'محمد', 'dept' => 'clinic', 'eye_side' => 'OD', 'visit_date' => today()->toDateString(),
            'service_id' => $service->id, 'service_name' => $service->name, 'doctor_id' => $doctor->id,
            'price' => 400, 'paid_amount' => 400, 'pay_method' => 'cash', 'pay_status' => 'paid', 'status' => 'waiting',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $claims = app(DoctorClaimsService::class)->calculateClaims($doctor->id, null, null);
        $this->assertGreaterThan(0, $claims['net_due']);
        $this->assertFalse($claims['rows'][0]['is_insurance']);

        $payableCode = fn () => $doctor->fresh()->payableAccount?->code ?? Account::find($doctor->fresh()->payable_account_id)->code;
        $balance = fn () => collect(app(LedgerService::class)->trialBalance())->firstWhere('code', $payableCode())['balance'] ?? 0.0;

        $this->assertEquals($claims['net_due'], $balance());

        $this->actingAs($user)->post('/dr-claims/pay', [
            'doctor_id' => $doctor->id, 'amount' => $claims['net_due'], 'paid_at' => today()->toDateString(), 'method' => 'cash',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, JournalEntry::where('source', 'doctor_payment')->count());
        $this->assertEquals(0.0, $balance());
        $this->assertEquals(0.0, app(DoctorClaimsService::class)->calculateClaims($doctor->id, null, null)['net_due']);
    }

    /**
     * @return array{0: User, 1: Doctor}
     */
    private function paidBookingForADoctorWithNoFeeYet(): array
    {
        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['booking.create', 'drpayments.write'] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $user = User::factory()->create();
        $user->assignRole($role);

        $service = Service::create(['name' => 'كشف', 'dept' => 'clinic', 'price' => 300]);
        $doctor = Doctor::create(['name' => 'د. عمر', 'fee_type' => 'percentage', 'fee_value' => 0, 'is_active' => true]);

        $this->actingAs($user)->post('/booking', [
            'patient_name' => 'محمد', 'dept' => 'clinic', 'eye_side' => 'OD', 'visit_date' => today()->toDateString(),
            'service_id' => $service->id, 'service_name' => $service->name, 'doctor_id' => $doctor->id,
            'price' => 300, 'paid_amount' => 300, 'pay_method' => 'cash', 'pay_status' => 'paid', 'status' => 'waiting',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, JournalEntry::where('source', 'doctor_shift')->count());

        return [$user, $doctor];
    }

    private function payableBalance(Doctor $doctor): float
    {
        $code = Account::findOrFail($doctor->fresh()->payable_account_id)->code;

        return (float) collect(app(LedgerService::class)->trialBalance())->firstWhere('code', $code)['balance'];
    }

    private const CLINIC_HALF = ['clinic' => ['fee_type' => 'percentage', 'fee_value' => 50]];

    public function test_changing_a_doctors_fees_accrues_the_dues_of_his_already_paid_bookings(): void
    {
        [$user, $doctor] = $this->paidBookingForADoctorWithNoFeeYet();

        app(UpdateDoctorAction::class)->execute($doctor, ['dept_fees' => self::CLINIC_HALF]);
        app(UpdateDoctorAction::class)->execute($doctor->fresh(), ['dept_fees' => self::CLINIC_HALF]);

        $this->assertEquals(150.0, $this->payableBalance($doctor));
        $this->assertEquals(150.0, app(DoctorClaimsService::class)->calculateClaims($doctor->id, null, null)['net_due']);

        $this->actingAs($user)->post('/dr-claims/pay', [
            'doctor_id' => $doctor->id, 'amount' => 150, 'paid_at' => today()->toDateString(), 'method' => 'cash',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals(0.0, $this->payableBalance($doctor));
    }

    public function test_repair_migration_accrues_dues_already_paid_out_without_an_accrual(): void
    {
        [$user, $doctor] = $this->paidBookingForADoctorWithNoFeeYet();

        $doctor->update(['dept_fees' => self::CLINIC_HALF]);
        $this->actingAs($user)->post('/dr-claims/pay', [
            'doctor_id' => $doctor->id, 'amount' => 150, 'paid_at' => today()->toDateString(), 'method' => 'cash',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(-150.0, $this->payableBalance($doctor));

        $migration = require database_path('migrations/2026_11_15_192928_resync_doctor_dues_with_current_fees.php');
        $migration->up();
        $migration->up();

        $this->assertEquals(0.0, $this->payableBalance($doctor));
        $this->assertSame(1, JournalEntry::where('source', 'doctor_shift')->count());
    }
}
