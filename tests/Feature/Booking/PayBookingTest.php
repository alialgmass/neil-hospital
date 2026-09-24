<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Enums\AccountNature;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\DoctorClaimsService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::firstOrCreate(['name' => 'booking.pay', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        Account::create([
            'code' => '5110', 'name' => 'نصيب الأطباء - عيادة',
            'group' => AccountGroup::Expenses, 'nature' => AccountNature::Debit,
            'balance' => 0, 'is_active' => true,
        ]);
        Account::create([
            'code' => '2010', 'name' => 'مستحقات الأطباء',
            'group' => AccountGroup::Liabilities, 'nature' => AccountNature::Credit,
            'balance' => 0, 'is_active' => true,
        ]);
        Account::create([
            'code' => '1010', 'name' => 'الخزنة الرئيسية',
            'group' => AccountGroup::Assets, 'nature' => AccountNature::Debit,
            'balance' => 0, 'is_active' => true,
        ]);
        Account::create([
            'code' => '1020', 'name' => 'البنك — الحساب الجاري',
            'group' => AccountGroup::Assets, 'nature' => AccountNature::Debit,
            'balance' => 0, 'is_active' => true,
        ]);
        Account::create([
            'code' => '4010', 'name' => 'إيرادات العيادة الخارجية (كشف)',
            'group' => AccountGroup::Revenues, 'nature' => AccountNature::Credit,
            'balance' => 0, 'is_active' => true,
        ]);
    }

    private function createBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'file_no' => 'MRN-001',
            'patient_name' => 'محمد علي',
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
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

    public function test_pay_booking_updates_price_and_paid_amount(): void
    {
        $booking = $this->createBooking(['price' => 0]);

        $this->actingAs($this->user)
            ->patch("/booking/{$booking->id}/pay", [
                'price' => 300,
                'paid_amount' => 300,
                'pay_method' => 'cash',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'price' => 300,
            'paid_amount' => 300,
            'pay_status' => 'paid',
        ]);
    }

    public function test_pay_booking_posts_percentage_doctor_dues(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. أحمد', 'fee_type' => 'percentage', 'fee_value' => 10, 'is_active' => true,
        ]);
        $booking = $this->createBooking(['doctor_id' => $doctor->id]);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 1000,
            'paid_amount' => 1000,
            'pay_method' => 'cash',
        ]);

        $entry = JournalEntry::where('reference', $booking->file_no)
            ->where('debit_account_id', Account::where('code', '5110')->value('id'))
            ->first();

        $this->assertNotNull($entry, 'Doctor dues journal entry should be posted');
        $this->assertEquals(100.0, (float) $entry->amount);
    }

    public function test_pay_booking_posts_fixed_doctor_dues_only_on_first_payment(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. سارة', 'fee_type' => 'fixed', 'fee_value' => 50, 'is_active' => true,
        ]);
        $booking = $this->createBooking(['doctor_id' => $doctor->id, 'price' => 500]);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 500,
            'paid_amount' => 200,
            'pay_method' => 'cash',
        ]);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 500,
            'paid_amount' => 300,
            'pay_method' => 'cash',
        ]);

        $entries = JournalEntry::where('reference', $booking->file_no)
            ->where('debit_account_id', Account::where('code', '5110')->value('id'))
            ->get();

        $this->assertCount(1, $entries, 'Fixed doctor fee should only be posted once, on the first payment');
        $this->assertEquals(50.0, (float) $entries->first()->amount);
    }

    public function test_paying_zero_on_a_cash_booking_writes_off_the_price_as_doctor_debt(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. منى', 'fee_type' => 'fixed', 'fee_value' => 50, 'is_active' => true,
        ]);
        $booking = $this->createBooking(['doctor_id' => $doctor->id, 'price' => 1000]);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'paid_amount' => 0,
            'pay_method' => 'cash',
        ])->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'paid_amount' => 0,
            'pay_status' => 'paid',
        ]);
        $this->assertEquals(1000.0, (float) $doctor->fresh()->doctor_debt_balance);

        // No cash/doctor-dues journal entries should be posted for a
        // write-off — nothing was actually collected or newly payable.
        $this->assertDatabaseMissing('journal_entries', ['reference' => $booking->file_no]);

        $this->assertDatabaseHas('doctor_debt_settlements', [
            'doctor_id' => $doctor->id,
            'booking_id' => $booking->id,
            'amount' => 1000,
            'type' => 'incurred',
        ]);

        // The claims report must show this booking as 0 مستحق (written off),
        // not a normal computed share — see DoctorClaimsService.
        $result = app(DoctorClaimsService::class)
            ->calculateClaims($doctor->id, '2026-01-01', '2026-12-31');

        $this->assertEquals(0.0, $result['rows'][0]['dr_share']);
        $this->assertEquals(1000.0, $result['rows'][0]['debt_incurred']);
    }

    public function test_paying_zero_twice_never_incurs_the_debt_twice(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. هند', 'fee_type' => 'fixed', 'fee_value' => 50, 'is_active' => true,
        ]);
        $booking = $this->createBooking(['doctor_id' => $doctor->id, 'price' => 1000]);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'paid_amount' => 0,
            'pay_method' => 'cash',
        ])->assertRedirect();

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'paid_amount' => 0,
            'pay_method' => 'cash',
        ])->assertRedirect();

        $this->assertEquals(1000.0, (float) $doctor->fresh()->doctor_debt_balance);
    }

    public function test_paying_zero_on_an_insurance_booking_does_not_incur_doctor_debt(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. كريم', 'fee_type' => 'fixed', 'fee_value' => 50, 'is_active' => true,
        ]);
        $booking = $this->createBooking(['doctor_id' => $doctor->id, 'price' => 1000, 'pay_method' => 'insurance']);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'paid_amount' => 0,
            'pay_method' => 'insurance',
        ])->assertRedirect();

        $this->assertEquals(0.0, (float) $doctor->fresh()->doctor_debt_balance);
    }

    public function test_paying_zero_on_a_partially_paid_booking_writes_off_only_the_remainder(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. عادل', 'fee_type' => 'fixed', 'fee_value' => 50, 'is_active' => true,
        ]);
        $booking = $this->createBooking([
            'doctor_id' => $doctor->id, 'price' => 1000, 'paid_amount' => 400, 'pay_status' => 'partial',
        ]);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'paid_amount' => 0,
            'pay_method' => 'cash',
        ])->assertRedirect();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'pay_status' => 'paid']);
        // Only the remaining 600 (1000 − 400 already paid) becomes debt.
        $this->assertEquals(600.0, (float) $doctor->fresh()->doctor_debt_balance);
    }

    public function test_paying_zero_on_an_already_paid_booking_is_rejected(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. سامي', 'fee_type' => 'fixed', 'fee_value' => 50, 'is_active' => true,
        ]);
        $booking = $this->createBooking([
            'doctor_id' => $doctor->id, 'price' => 1000, 'paid_amount' => 1000, 'pay_status' => 'paid',
        ]);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'paid_amount' => 0,
            'pay_method' => 'cash',
        ])->assertRedirect();

        $this->assertEquals(0.0, (float) $doctor->fresh()->doctor_debt_balance);
    }
}
