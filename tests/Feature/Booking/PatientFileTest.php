<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\States\DraftState;
use Modules\Surgery\Models\Surgery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PatientFileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::firstOrCreate(['name' => 'booking.view', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function makeBooking(string $fileNo, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'file_no' => $fileNo,
            'patient_name' => 'مريض تجريبي',
            'dept' => 'clinic',
            'visit_date' => now()->toDateString(),
            'price' => 100,
            'paid_amount' => 100,
            'pay_method' => 'cash',
            'pay_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ], $overrides));
    }

    public function test_patient_file_includes_media_files_key(): void
    {
        $this->actingAs($this->user);
        $this->makeBooking('TST-100');

        $response = $this->get(route('booking.patient-file', 'TST-100'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('booking/PatientFile')
            ->has('bookings.0.media_files')
        );
    }

    public function test_patient_file_returns_uploaded_archive_files(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user);
        $booking = $this->makeBooking('TST-101');

        $file = UploadedFile::fake()->image('scan.jpg');
        $booking->addMedia($file)->toMediaCollection('archive-files');

        $response = $this->get(route('booking.patient-file', 'TST-101'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('booking/PatientFile')
            ->where('bookings.0.media_files.0.name', 'scan.jpg')
        );
    }

    public function test_patient_file_exposes_full_patient_identity_fields(): void
    {
        $this->actingAs($this->user);
        $this->makeBooking('TST-102', [
            'national_id' => '29001011234567',
            'gender' => 'male',
            'kinship_degree' => 'father',
        ]);

        $response = $this->get(route('booking.patient-file', 'TST-102'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('booking/PatientFile')
            ->where('patient.national_id', '29001011234567')
            ->where('patient.gender', 'male')
            ->where('patient.kinship_degree', 'father')
            ->where('patient.kinship_degree_label', 'الوالد')
        );
    }

    public function test_patient_file_eager_loads_service_surgery_and_insurance_claim(): void
    {
        $this->actingAs($this->user);

        $service = Service::create(['name' => 'مياه بيضاء', 'dept' => 'surgery', 'price' => 5000]);
        $company = InsuranceCompany::create(['name' => 'شركة التأمين', 'coverage_pct' => 80]);

        $booking = $this->makeBooking('TST-103', ['service_id' => $service->id, 'dept' => 'surgery']);

        Surgery::create([
            'booking_id' => $booking->id,
            'dept' => 'surgery',
            'procedure' => 'استئصال المياه البيضاء',
            'status' => 'completed',
        ]);

        InsuranceClaim::create([
            'booking_id' => $booking->id,
            'insurance_company_id' => $company->id,
            'service_id' => $service->id,
            'patient_name' => $booking->patient_name,
            'file_no' => $booking->file_no,
            'service_name' => $service->name,
            'invoice_amount' => 5000,
            'discount' => 0,
            'insurance_share' => 4000,
            'patient_share' => 1000,
            'status' => DraftState::class,
            'service_date' => $booking->visit_date,
            'claim_date' => today()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $response = $this->get(route('booking.patient-file', 'TST-103'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('booking/PatientFile')
            ->where('bookings.0.service.name', 'مياه بيضاء')
            ->where('bookings.0.surgery.procedure', 'استئصال المياه البيضاء')
            ->where('bookings.0.insurance_claim.company.name', 'شركة التأمين')
            ->where('bookings.0.insurance_claim.insurance_share', 4000)
        );
    }
}
