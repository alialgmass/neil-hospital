<?php

namespace Tests\Feature\Clinic;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Clinic\Models\ClinicSheet;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InitialAssessmentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => 'clinic.view', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'clinic.write', 'guard_name' => 'web']),
        ]);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function makeBooking(): Booking
    {
        return Booking::create([
            'file_no' => 'P-100-123',
            'patient_name' => 'مريض تجريبي',
            'dept' => 'clinic',
            'visit_date' => today()->toDateString(),
            'price' => 100, 'discount' => 0, 'ins_amount' => 0, 'paid_amount' => 0,
            'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'confirmed',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_medical_and_nursing_assessment_fields_are_saved(): void
    {
        $booking = $this->makeBooking();

        $response = $this->actingAs($this->user)->post("/clinic/{$booking->id}/sheet", [
            'booking_id' => $booking->id,
            'patient_contact' => '01012345678',
            'allergies_status' => 'yes',
            'allergies_specify' => 'بنسلين',
            'current_medications' => [
                ['name' => 'باراسيتامول', 'dose' => '500mg', 'route' => 'فم', 'frequency' => 'يومي', 'remarks' => ''],
            ],
            'plan_medications' => [
                ['name' => 'قطرة مضاد حيوي', 'dose' => '', 'route' => 'عين', 'frequency' => '3 مرات', 'remarks' => ''],
            ],
            'visual_exam' => [
                'uncorrected' => ['right' => '6/12', 'left' => '6/18'],
                'correction' => ['right' => '6/6', 'left' => '6/9'],
            ],
            'eye_exam_grid' => [
                'cornea' => ['right' => 'طبيعية', 'left' => 'طبيعية'],
            ],
            'plan_education' => true,
            'plan_followup' => 'بعد أسبوعين',
            'nursing_history_answers' => [
                ['key' => 'heart_disease', 'answer' => 'yes', 'specify' => 'ضغط الدم'],
                ['key' => 'anemia', 'answer' => 'no', 'specify' => ''],
            ],
            'fall_screening' => [
                'dizziness' => 5,
                'pain_meds_today' => 0,
                'diabetes_meds' => 5,
                'gait' => 0,
                'walking_aid' => 0,
            ],
            'nurse_signature_name' => 'ممرضة سارة',
            'evaluator_name' => 'ممرضة سارة',
        ]);

        $response->assertRedirect();

        $sheet = ClinicSheet::where('booking_id', $booking->id)->firstOrFail();

        $this->assertSame('01012345678', $sheet->patient_contact);
        $this->assertSame('yes', $sheet->allergies_status);
        $this->assertSame('باراسيتامول', $sheet->current_medications[0]['name']);
        $this->assertSame('6/12', $sheet->visual_exam['uncorrected']['right']);
        $this->assertSame('طبيعية', $sheet->eye_exam_grid['cornea']['right']);
        $this->assertTrue($sheet->plan_education);
        $this->assertSame('heart_disease', $sheet->nursing_history_answers[0]['key']);
        $this->assertSame(10, $sheet->fall_screening_score);
    }

    public function test_fall_screening_score_is_recomputed_on_resave(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->user)->post("/clinic/{$booking->id}/sheet", [
            'booking_id' => $booking->id,
            'fall_screening' => ['dizziness' => 5, 'pain_meds_today' => 5, 'diabetes_meds' => 5, 'gait' => 5, 'walking_aid' => 5],
        ]);

        $sheet = ClinicSheet::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame(25, $sheet->fall_screening_score);

        $this->actingAs($this->user)->post("/clinic/{$booking->id}/sheet", [
            'booking_id' => $booking->id,
            'fall_screening' => ['dizziness' => 0, 'pain_meds_today' => 0, 'diabetes_meds' => 0, 'gait' => 0, 'walking_aid' => 0],
        ]);

        $this->assertSame(0, $sheet->fresh()->fall_screening_score);
    }
}
