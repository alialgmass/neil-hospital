<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\LedgerService;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Actions\UpdateDoctorAction;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\DoctorClaimsService;
use Modules\Surgery\Models\OrBed;
use Modules\Surgery\Models\OrRoom;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: array<string, mixed>, 3: float, 4: float}>
     */
    public static function unpaidThenCollectedCases(): array
    {
        return [
            'clinic 50%' => ['clinic', ['price' => 200, 'both_eyes_price' => 200], self::CLINIC_HALF, 200.0, 100.0],
            'labs 27% after dev fee' => ['labs', ['price' => 650, 'both_eyes_price' => 650, 'dev_treasury_fee' => 50], ['labs' => ['fee_type' => 'percentage', 'fee_value' => 27]], 650.0, 162.0],
            'laser above center price' => ['laser', ['price' => 1150, 'one_eye_price' => 550, 'both_eyes_price' => 1150, 'dev_treasury_fee' => 50], [], 2500.0, 1300.0],
            'lasik above center price' => ['lasik', ['price' => 2500, 'one_eye_price' => 1500, 'both_eyes_price' => 2500, 'dev_treasury_fee' => 50], [], 10000.0, 7450.0],
        ];
    }

    #[DataProvider('unpaidThenCollectedCases')]
    public function test_a_booking_created_unpaid_then_collected_accrues_the_doctors_full_share(string $dept, array $service, array $deptFees, float $collected, float $expectedShare): void
    {
        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['booking.create', 'booking.pay', 'drpayments.write'] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $user = User::factory()->create();
        $user->assignRole($role);

        $service = Service::create(['name' => 'خدمة', 'dept' => $dept, ...$service]);
        $doctor = Doctor::create(['name' => 'د. خالد', 'fee_type' => 'percentage', 'fee_value' => 0, 'is_active' => true, 'dept_fees' => $deptFees]);
        $bedId = $dept === 'lasik' ? OrBed::create(['room_id' => OrRoom::create(['name' => 'Room 1'])->id, 'bed_number' => 1])->id : null;

        $this->actingAs($user)->post('/booking', [
            'patient_name' => 'محمد', 'dept' => $dept, 'eye_side' => 'OU', 'visit_date' => today()->toDateString(),
            'service_id' => $service->id, 'service_name' => $service->name, 'doctor_id' => $doctor->id, 'bed_id' => $bedId,
            'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'waiting',
            ...($dept === 'labs' ? ['service_ids' => [$service->id]] : []),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($user)->patch(route('booking.pay', Booking::firstOrFail()->id), [
            'paid_amount' => $collected, 'pay_method' => 'cash',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals($expectedShare, app(DoctorClaimsService::class)->calculateClaims($doctor->id, null, null)['net_due']);
        $this->assertEquals($expectedShare, $this->payableBalance($doctor));
        $this->assertEquals(0.0, (float) $doctor->fresh()->doctor_debt_balance);
    }

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
