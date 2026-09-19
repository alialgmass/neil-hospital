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
}
