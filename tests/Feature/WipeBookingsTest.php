<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\TreasuryEntry;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingService;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Clinic\Models\ClinicSheet;
use Modules\Clinic\Models\MedicalExamination;
use Modules\Doctor\Models\BookingDoctorDelegation;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorDebtSettlement;
use Modules\Doctor\Models\DoctorEntitlement;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Labs\Models\DiagnosticResult;
use Modules\Surgery\Models\Surgery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WipeBookingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('settings.manage', 'web');
    }

    public function test_unauthorized_users_cannot_wipe_bookings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->delete(route('settings.wipe-bookings'));

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_wipe_all_bookings_and_reflections(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('settings.manage');

        $booking = Booking::create([
            'id' => (string) Str::ulid(),
            'file_no' => 'F-1001',
            'patient_name' => 'John Doe',
            'dept' => 'clinic',
            'service_name' => 'Kashf',
            'price' => 100,
            'visit_date' => today()->toDateString(),
            'pay_status' => 'unpaid',
            'status' => 'confirmed',
        ]);

        BookingService::create([
            'booking_id' => $booking->id,
            'service_name' => 'Test Service',
            'price' => 100,
        ]);

        ClinicSheet::create([
            'booking_id' => $booking->id,
            'diagnosis' => 'Normal',
            'recorded_at' => now(),
        ]);

        MedicalExamination::create([
            'booking_id' => $booking->id,
            'examined_at' => now(),
        ]);

        DiagnosticResult::create([
            'booking_id' => $booking->id,
            'test_name' => 'OCT',
            'recorded_at' => now(),
        ]);

        Surgery::create([
            'booking_id' => $booking->id,
            'eye_side' => 'right',
        ]);

        $insCompany = InsuranceCompany::create([
            'id' => (string) Str::ulid(),
            'name' => 'Test Ins Company',
        ]);

        $service = Service::create([
            'id' => (string) Str::ulid(),
            'name' => 'Test Service',
            'price' => 100,
            'dept' => 'clinic',
        ]);

        InsuranceClaim::create([
            'booking_id' => $booking->id,
            'insurance_company_id' => $insCompany->id,
            'service_id' => $service->id,
            'patient_name' => 'John Doe',
            'service_name' => 'Kashf',
            'service_date' => today()->toDateString(),
            'claim_date' => today()->toDateString(),
            'claim_amount' => 500,
            'status' => 'draft',
        ]);

        $doctor = Doctor::create([
            'id' => (string) Str::ulid(),
            'name' => 'Dr Test',
            'specialty' => 'Ophthalmology',
        ]);

        DoctorEntitlement::create([
            'booking_id' => $booking->id,
            'doctor_id' => $doctor->id,
            'amount' => 100,
            'source' => 'contract',
            'status' => 'pending',
        ]);

        BookingDoctorDelegation::create([
            'booking_id' => $booking->id,
            'doctor_id' => $doctor->id,
            'role' => 'delegate',
            'service_name' => 'Kashf',
            'fee_amount' => 50,
        ]);

        TreasuryEntry::create([
            'type' => 'in',
            'description' => 'Test booking payment',
            'amount' => 100,
            'date' => today()->toDateString(),
            'source' => JournalSource::BOOKING,
            'booking_id' => $booking->id,
        ]);

        $acc1 = Account::create([
            'id' => (string) Str::ulid(),
            'code' => '1010',
            'name' => 'Cash',
            'group' => 'assets',
            'nature' => 'debit',
        ]);

        $acc2 = Account::create([
            'id' => (string) Str::ulid(),
            'code' => '4110',
            'name' => 'Revenue',
            'group' => 'revenues',
            'nature' => 'credit',
        ]);

        JournalEntry::create([
            'date' => today()->toDateString(),
            'description' => 'Test booking journal',
            'amount' => 100,
            'reference' => 'F-1001',
            'source' => JournalSource::BOOKING,
            'debit_account_id' => $acc1->id,
            'credit_account_id' => $acc2->id,
        ]);

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);

        $debtDoctor = Doctor::create(['name' => 'Debt Doctor', 'is_active' => true, 'fee_type' => 'fixed', 'fee_value' => 10]);
        DoctorDebtSettlement::create([
            'doctor_id' => $debtDoctor->id,
            'booking_id' => $booking->id,
            'amount' => 25,
            'type' => 'incurred',
        ]);
        DB::table('dr_payments')->insert([
            'id' => (string) Str::ulid(), 'doctor_id' => $debtDoctor->id, 'amount' => 50,
            'paid_at' => today(), 'method' => 'cash',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('monthly_settlements')->insert([
            'month_period' => '2026-11', 'total_revenue' => 1, 'total_expenses' => 1,
            'total_doctor_claims' => 1, 'net_surplus_deficit' => 0, 'is_locked' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $accounts = Account::query()->limit(2)->pluck('id')->values();
        DB::table('journal_entries')->insert([
            'id' => (string) Str::ulid(), 'date' => today(), 'description' => 'wipe',
            'debit_account_id' => $accounts[0], 'credit_account_id' => $accounts[1],
            'amount' => 1, 'source' => 'manual', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('treasury_entries')->insert([
            'id' => (string) Str::ulid(), 'type' => 'in', 'description' => 'wipe',
            'amount' => 1, 'date' => today(), 'source' => 'manual',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->delete(route('settings.wipe-bookings'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('treasury_entries', 0);
        $this->assertDatabaseCount('doctor_debt_settlements', 0);
        $this->assertDatabaseCount('monthly_settlements', 0);
        $this->assertDatabaseCount('doctor_entitlements', 0);
        $this->assertDatabaseCount('booking_doctor_delegations', 0);
        $this->assertDatabaseCount('dr_payments', 0);
        $this->assertDatabaseCount('insurance_claims', 0);
        $this->assertDatabaseMissing('booking_services', ['booking_id' => $booking->id]);
        $this->assertDatabaseMissing('clinic_sheets', ['booking_id' => $booking->id]);
        $this->assertDatabaseMissing('medical_examinations', ['booking_id' => $booking->id]);
        $this->assertDatabaseMissing('diagnostic_results', ['booking_id' => $booking->id]);
        $this->assertDatabaseMissing('surgeries', ['booking_id' => $booking->id]);
        $this->assertDatabaseMissing('insurance_claims', ['booking_id' => $booking->id]);
        $this->assertDatabaseMissing('doctor_entitlements', ['booking_id' => $booking->id]);
        $this->assertDatabaseMissing('booking_doctor_delegations', ['booking_id' => $booking->id]);
        $this->assertDatabaseMissing('treasury_entries', ['booking_id' => $booking->id]);
        $this->assertDatabaseMissing('journal_entries', ['reference' => 'F-1001']);
    }
}
