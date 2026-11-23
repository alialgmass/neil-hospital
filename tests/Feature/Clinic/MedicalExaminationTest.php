<?php

namespace Tests\Feature\Clinic;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Clinic\Enums\ExaminationStatus;
use Modules\Clinic\Models\ClinicSheet;
use Modules\Clinic\Models\Diagnosis;
use Modules\Clinic\Models\MedicalExamination;
use Modules\Doctor\Models\Doctor;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MedicalExaminationTest extends TestCase
{
    use RefreshDatabase;

    private const ALL_PERMISSIONS = [
        'examinations.view',
        'examinations.create',
        'examinations.update',
        'examinations.delete',
        'examinations.print',
    ];

    private User $doctorUser;

    private Doctor $doctor;

    private int $fileSequence = 100;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doctorUser = $this->userWithPermissions(self::ALL_PERMISSIONS);
        $this->doctor = Doctor::create(['name' => 'د. منى', 'fee_type' => 'fixed', 'fee_value' => 0, 'user_id' => $this->doctorUser->id]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeBooking(array $overrides = []): Booking
    {
        return Booking::create([
            'file_no' => 'P-'.$this->fileSequence++.'-123',
            'patient_name' => 'أحمد علي',
            'patient_phone' => '01012345678',
            'patient_age' => 45,
            'national_id' => '29001011234567',
            'dept' => 'clinic',
            'doctor_id' => $this->doctor->id,
            'visit_date' => today()->toDateString(),
            'price' => 100, 'discount' => 0, 'ins_amount' => 0, 'paid_amount' => 0,
            'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'confirmed',
            'created_by' => $this->doctorUser->id,
            ...$overrides,
        ]);
    }

    private function makeInvestigation(string $name = 'OCT', string $dept = 'labs'): Service
    {
        return Service::create(['name' => $name, 'dept' => $dept, 'price' => 300, 'status' => 'active']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'chief_complaint' => 'Blurred vision',
            'complaint_duration' => '3 months',
            'affected_eye' => 'OU',
            'eye_disease_history' => ['glaucoma'],
            'systemic_diseases' => ['diabetes', 'hypertension'],
            'glasses_usage' => 'distance',
            'contact_lenses' => 'none',
            'eye_trauma' => false,
            'iop_method' => 'applanation',
            'eyes' => [
                'OD' => ['ucva' => '6/18', 'cva' => '6/9', 'sphere' => -2.25, 'cylinder' => -0.75, 'axis' => 90, 'bcva' => '6/6', 'cornea' => 'Clear', 'iop' => 18, 'cd_ratio' => 0.3],
                'OS' => ['ucva' => '6/24', 'sphere' => -2.5, 'cylinder' => 0, 'add_power' => 1.5, 'lens' => 'NS2', 'iop' => 21.5, 'cd_ratio' => 0.6],
            ],
            'assessment' => 'Myopia with suspicious disc OS',
            'treatment_plan' => 'Glasses + visual field',
            'medications' => [
                ['name' => 'Timolol 0.5%', 'dose' => '1 drop', 'route' => 'eye', 'frequency' => 'BID', 'remarks' => ''],
                ['name' => '', 'dose' => '', 'route' => '', 'frequency' => '', 'remarks' => ''],
            ],
            'follow_up' => 'After 1 month',
            'next_visit_date' => today()->addMonth()->toDateString(),
            ...$overrides,
        ];
    }

    public function test_doctor_can_create_a_draft_examination_with_per_eye_findings(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->doctorUser)
            ->post("/examinations/booking/{$booking->id}", $this->payload())
            ->assertRedirect("/examinations/booking/{$booking->id}")
            ->assertSessionHasNoErrors();

        $examination = MedicalExamination::with('eyes')->where('booking_id', $booking->id)->firstOrFail();

        $this->assertSame(ExaminationStatus::Draft, $examination->status);
        $this->assertSame($this->doctor->id, $examination->doctor_id);
        $this->assertSame($this->doctorUser->id, $examination->created_by);
        $this->assertSame(['glaucoma'], $examination->eye_disease_history);
        $this->assertCount(1, $examination->medications, 'empty medication rows are dropped');

        $od = $examination->eyes->firstWhere('eye.value', 'OD');
        $os = $examination->eyes->firstWhere('eye.value', 'OS');
        $this->assertEquals(-2.25, $od->sphere);
        $this->assertSame(90, $od->axis);
        $this->assertSame('Clear', $od->cornea);
        $this->assertEquals(21.5, $os->iop);
        $this->assertEquals(0.6, $os->cd_ratio);
        $this->assertSame('NS2', $os->lens);
        $this->assertNull($os->cornea, 'OD findings never leak into OS');
    }

    public function test_patient_identity_is_read_from_the_booking_not_duplicated(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->doctorUser)
            ->get("/examinations/booking/{$booking->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('clinic/Examination')
                ->where('patient.patient_name', 'أحمد علي')
                ->where('patient.file_no', $booking->file_no)
                ->where('patient.patient_age', 45)
                ->where('patient.doctor.name', 'د. منى')
                ->where('examination', null)
                ->where('read_only', false)
                ->where('default_doctor_id', $this->doctor->id)
                ->has('options.diagnoses')
            );

        $this->assertFalse(\Schema::hasColumn('medical_examinations', 'patient_name'));
    }

    public function test_new_examination_is_prefilled_from_the_clinic_sheet(): void
    {
        $booking = $this->makeBooking();
        ClinicSheet::create([
            'booking_id' => $booking->id,
            'chief_complaint' => 'Red eye',
            'iop_od' => 16,
            'visual_exam' => ['uncorrected' => ['right' => '6/12', 'left' => '6/6']],
            'recorded_at' => now(),
        ]);

        $this->actingAs($this->doctorUser)
            ->get("/examinations/booking/{$booking->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('prefill.chief_complaint', 'Red eye')
                ->where('prefill.eyes.OD.ucva', '6/12')
                ->where('prefill.eyes.OS.ucva', '6/6')
            );
    }

    public function test_a_visit_can_only_have_one_examination(): void
    {
        $booking = $this->makeBooking();
        $this->actingAs($this->doctorUser)->post("/examinations/booking/{$booking->id}", $this->payload());

        $this->actingAs($this->doctorUser)
            ->post("/examinations/booking/{$booking->id}", $this->payload())
            ->assertSessionHasErrors('booking');

        $this->assertSame(1, MedicalExamination::count());
    }

    public function test_draft_can_be_updated_and_eye_rows_are_replaced_not_duplicated(): void
    {
        $booking = $this->makeBooking();
        $this->actingAs($this->doctorUser)->post("/examinations/booking/{$booking->id}", $this->payload());
        $examination = MedicalExamination::firstOrFail();

        $this->actingAs($this->doctorUser)
            ->put("/examinations/{$examination->id}", $this->payload([
                'chief_complaint' => 'Updated complaint',
                'eyes' => ['OD' => ['iop' => 25], 'OS' => ['iop' => 14]],
            ]))
            ->assertSessionHasNoErrors();

        $examination->refresh()->load('eyes');
        $this->assertSame('Updated complaint', $examination->chief_complaint);
        $this->assertCount(2, $examination->eyes);
        $this->assertEquals(25, $examination->eyes->firstWhere('eye.value', 'OD')->iop);
        $this->assertNull($examination->eyes->firstWhere('eye.value', 'OD')->sphere);
    }

    public function test_diagnoses_link_to_the_catalog_with_eye_and_notes(): void
    {
        $booking = $this->makeBooking();
        $myopia = Diagnosis::where('name', 'Myopia')->firstOrFail();

        $this->actingAs($this->doctorUser)->post("/examinations/booking/{$booking->id}", $this->payload([
            'diagnoses' => [
                ['diagnosis_id' => $myopia->id, 'eye' => 'OU', 'notes' => 'Mild'],
                ['name' => 'Tilted disc', 'eye' => 'OS'],
            ],
        ]))->assertSessionHasNoErrors();

        $examination = MedicalExamination::with('diagnoses.diagnosis')->firstOrFail();

        $this->assertCount(2, $examination->diagnoses);
        $this->assertSame('Myopia', $examination->diagnoses[0]->diagnosis->name);
        $this->assertSame('OU', $examination->diagnoses[0]->eye->value);
        $this->assertSame('Mild', $examination->diagnoses[0]->notes);
        $this->assertTrue(Diagnosis::where('name', 'Tilted disc')->exists(), 'typed diagnosis is added to the catalog');
    }

    public function test_investigations_use_existing_labs_and_pentacam_services_only(): void
    {
        $booking = $this->makeBooking();
        $oct = $this->makeInvestigation('OCT Macula');
        $topography = $this->makeInvestigation('Topography', 'pentacam');
        $surgery = $this->makeInvestigation('Phaco', 'surgery');

        $this->actingAs($this->doctorUser)->post("/examinations/booking/{$booking->id}", $this->payload([
            'investigations' => [['service_id' => $surgery->id]],
        ]))->assertSessionHasErrors('investigations.0.service_id');

        $this->actingAs($this->doctorUser)->post("/examinations/booking/{$booking->id}", $this->payload([
            'investigations' => [
                ['service_id' => $oct->id, 'eye' => 'OD'],
                ['service_id' => $topography->id, 'eye' => 'OU', 'notes' => 'Before LASIK'],
            ],
        ]))->assertSessionHasNoErrors();

        $investigations = MedicalExamination::firstOrFail()->investigations;
        $this->assertCount(2, $investigations);
        $this->assertSame('OCT Macula', $investigations[0]->name);
        $this->assertSame($oct->id, $investigations[0]->service_id);
    }

    public function test_finalizing_requires_chief_complaint_and_a_diagnosis(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->doctorUser)
            ->post("/examinations/booking/{$booking->id}", $this->payload(['finalize' => true, 'chief_complaint' => '']))
            ->assertSessionHasErrors(['chief_complaint', 'diagnoses']);

        $this->assertSame(0, MedicalExamination::count());
    }

    public function test_draft_can_be_saved_with_no_clinical_data(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->doctorUser)
            ->post("/examinations/booking/{$booking->id}", [])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, MedicalExamination::count());
    }

    public function test_finalized_examination_is_locked(): void
    {
        $booking = $this->makeBooking();
        $myopia = Diagnosis::where('name', 'Myopia')->firstOrFail();

        $this->actingAs($this->doctorUser)->post("/examinations/booking/{$booking->id}", $this->payload([
            'finalize' => true,
            'diagnoses' => [['diagnosis_id' => $myopia->id, 'eye' => 'OU']],
        ]))->assertSessionHasNoErrors();

        $examination = MedicalExamination::firstOrFail();
        $this->assertSame(ExaminationStatus::Finalized, $examination->status);
        $this->assertNotNull($examination->finalized_at);
        $this->assertSame($this->doctorUser->id, $examination->finalized_by);

        $this->actingAs($this->doctorUser)
            ->put("/examinations/{$examination->id}", $this->payload(['chief_complaint' => 'changed']))
            ->assertForbidden();

        $this->actingAs($this->doctorUser)
            ->delete("/examinations/{$examination->id}")
            ->assertForbidden();

        $this->assertSame('Blurred vision', $examination->fresh()->chief_complaint);
    }

    public function test_draft_can_be_deleted_with_its_children(): void
    {
        $booking = $this->makeBooking();
        $this->actingAs($this->doctorUser)->post("/examinations/booking/{$booking->id}", $this->payload([
            'diagnoses' => [['name' => 'Myopia', 'eye' => 'OU']],
        ]));
        $examination = MedicalExamination::firstOrFail();

        $this->actingAs($this->doctorUser)
            ->delete("/examinations/{$examination->id}")
            ->assertRedirect("/examinations/booking/{$booking->id}");

        $this->assertDatabaseCount('medical_examinations', 0);
        $this->assertDatabaseCount('medical_examination_eyes', 0);
        $this->assertDatabaseCount('medical_examination_diagnoses', 0);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidEyeValues(): array
    {
        return [
            'IOP above range' => [['OD' => ['iop' => 95]], 'eyes.OD.iop'],
            'IOP not numeric' => [['OS' => ['iop' => 'high']], 'eyes.OS.iop'],
            'sphere not in 0.25 steps' => [['OD' => ['sphere' => -1.3]], 'eyes.OD.sphere'],
            'cylinder out of range' => [['OS' => ['cylinder' => -20]], 'eyes.OS.cylinder'],
            'axis above 180' => [['OD' => ['axis' => 200]], 'eyes.OD.axis'],
            'axis missing with cylinder' => [['OD' => ['cylinder' => -1.0]], 'eyes.OD.axis'],
            'C/D ratio above 1' => [['OS' => ['cd_ratio' => 1.4]], 'eyes.OS.cd_ratio'],
            'add power negative' => [['OD' => ['add_power' => -1]], 'eyes.OD.add_power'],
        ];
    }

    /**
     * @param  array<string, mixed>  $eyes
     */
    #[DataProvider('invalidEyeValues')]
    public function test_per_eye_numeric_values_are_validated(array $eyes, string $errorKey): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->doctorUser)
            ->post("/examinations/booking/{$booking->id}", ['eyes' => $eyes])
            ->assertSessionHasErrors($errorKey);
    }

    public function test_enum_and_date_fields_are_validated(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->doctorUser)
            ->post("/examinations/booking/{$booking->id}", [
                'affected_eye' => 'XX',
                'iop_method' => 'finger',
                'systemic_diseases' => ['flu'],
                'next_visit_date' => today()->subDay()->toDateString(),
            ])
            ->assertSessionHasErrors(['affected_eye', 'iop_method', 'systemic_diseases.0', 'next_visit_date']);
    }

    public function test_previous_examinations_list_only_the_same_patients_other_visits(): void
    {
        $firstVisit = $this->makeBooking(['visit_date' => today()->subDays(20)->toDateString()]);
        $secondVisit = $this->makeBooking(['visit_date' => today()->subDays(5)->toDateString()]);
        $current = $this->makeBooking();
        $otherPatient = $this->makeBooking(['patient_name' => 'مريض آخر', 'national_id' => '28001011111111']);

        foreach ([$firstVisit, $secondVisit, $otherPatient] as $booking) {
            $this->actingAs($this->doctorUser)->post("/examinations/booking/{$booking->id}", $this->payload());
        }

        $this->actingAs($this->doctorUser)
            ->get("/examinations/booking/{$current->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('previous_examinations', 2)
                ->where('previous_examinations.0.booking.id', $secondVisit->id)
                ->where('previous_examinations.1.booking.id', $firstVisit->id)
            );
    }

    public function test_previous_examination_opens_read_only(): void
    {
        $booking = $this->makeBooking();
        $this->actingAs($this->doctorUser)->post("/examinations/booking/{$booking->id}", $this->payload());
        $examination = MedicalExamination::firstOrFail();

        $this->actingAs($this->doctorUser)
            ->get("/examinations/{$examination->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('clinic/Examination')
                ->where('read_only', true)
                ->where('examination.id', $examination->id)
                ->has('examination.eyes', 2)
            );
    }

    public function test_print_report_contains_patient_and_examination(): void
    {
        $booking = $this->makeBooking();
        $this->actingAs($this->doctorUser)->post("/examinations/booking/{$booking->id}", $this->payload([
            'diagnoses' => [['name' => 'Myopia', 'eye' => 'OU']],
        ]));
        $examination = MedicalExamination::firstOrFail();

        $this->actingAs($this->doctorUser)
            ->get("/examinations/{$examination->id}/print")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('clinic/ExaminationPrint')
                ->where('patient.file_no', $booking->file_no)
                ->where('examination.chief_complaint', 'Blurred vision')
                ->where('examination.diagnoses.0.diagnosis.name', 'Myopia')
                ->has('examination.eyes', 2)
            );
    }

    public function test_user_without_view_permission_cannot_open_examinations(): void
    {
        $booking = $this->makeBooking();
        $outsider = $this->userWithPermissions(['clinic.view']);

        $this->actingAs($outsider)->get("/examinations/booking/{$booking->id}")->assertForbidden();
    }

    public function test_view_only_user_cannot_create_update_delete_or_print(): void
    {
        $booking = $this->makeBooking();
        $this->actingAs($this->doctorUser)->post("/examinations/booking/{$booking->id}", $this->payload());
        $examination = MedicalExamination::firstOrFail();
        $viewer = $this->userWithPermissions(['examinations.view']);

        $this->actingAs($viewer)->get("/examinations/{$examination->id}")->assertOk();
        $this->actingAs($viewer)->post('/examinations/booking/'.$this->makeBooking()->id, $this->payload())->assertForbidden();
        $this->actingAs($viewer)->put("/examinations/{$examination->id}", $this->payload(['chief_complaint' => 'x']))->assertForbidden();
        $this->actingAs($viewer)->delete("/examinations/{$examination->id}")->assertForbidden();
        $this->actingAs($viewer)->get("/examinations/{$examination->id}/print")->assertForbidden();

        $this->assertSame('Blurred vision', $examination->fresh()->chief_complaint);
    }

    public function test_labs_queue_exposes_each_visits_examination_status(): void
    {
        $withExam = $this->makeBooking(['dept' => 'labs']);
        $withoutExam = $this->makeBooking(['dept' => 'labs', 'patient_name' => 'مريض آخر', 'national_id' => null]);
        $this->actingAs($this->doctorUser)->post("/examinations/booking/{$withExam->id}", $this->payload());
        $this->doctorUser->givePermissionTo(Permission::firstOrCreate(['name' => 'labs.view', 'guard_name' => 'web']));

        $this->actingAs($this->doctorUser)
            ->get('/labs?date='.today()->toDateString())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('labs/Index')
                ->where('queue.data', fn ($rows) => collect($rows)->firstWhere('id', $withExam->id)['medical_examination']['status'] === 'draft'
                    && collect($rows)->firstWhere('id', $withoutExam->id)['medical_examination'] === null)
            );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $booking = $this->makeBooking();

        $this->get("/examinations/booking/{$booking->id}")->assertRedirect('/login');
    }
}
