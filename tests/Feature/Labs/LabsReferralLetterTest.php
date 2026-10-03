<?php

namespace Tests\Feature\Labs;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Clinic\Enums\ExaminationStatus;
use Modules\Clinic\Models\MedicalExamination;
use Modules\Labs\Models\DiagnosticResult;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LabsReferralLetterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'labs.view']);
        Permission::firstOrCreate(['name' => 'labs.write']);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo(['labs.view', 'labs.write']);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');

        $this->booking = Booking::create([
            'file_no' => 'MRN-LABS-1',
            'patient_name' => 'مريض الفحوصات',
            'dept' => 'labs',
            'visit_date' => today()->toDateString(),
            'price' => 200.00,
            'discount' => 0.00,
            'ins_amount' => 0.00,
            'paid_amount' => 200.00,
            'pay_method' => 'cash',
            'pay_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Results are now recorded through the medical examination
     * (see MedicalExaminationTest); the standalone endpoint is gone while
     * previously recorded results keep their referral letters.
     */
    public function test_results_are_no_longer_recorded_outside_the_medical_examination(): void
    {
        $response = $this->actingAs($this->user)->post("/labs/{$this->booking->id}/results", [
            'test_name' => 'B-Scan',
            'eye' => 'OS',
            'result_text' => 'Normal posterior segment.',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('diagnostic_results', 0);
    }

    public function test_referral_letter_page_renders_with_result_data(): void
    {
        $result = DiagnosticResult::create([
            'booking_id' => $this->booking->id,
            'test_name' => 'B-Scan',
            'eye' => 'OS',
            'result_text' => 'Normal posterior segment.',
            'technician_id' => $this->user->id,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get("/labs/results/{$result->id}/letter");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('result.test_name', 'B-Scan')
            ->where('result.eye', 'OS')
            ->where('result.booking.patient_name', 'مريض الفحوصات')
        );
    }

    private function makeResult(): DiagnosticResult
    {
        return DiagnosticResult::create([
            'booking_id' => $this->booking->id,
            'test_name' => 'OCT',
            'eye' => 'OD',
            'result_text' => null,
            'technician_id' => $this->user->id,
            'recorded_at' => now(),
        ]);
    }

    private function makeExamination(): MedicalExamination
    {
        $examination = MedicalExamination::create([
            'booking_id' => $this->booking->id,
            'status' => ExaminationStatus::Finalized,
            'chief_complaint' => 'Blurred vision',
            'assessment' => 'Macular edema OD',
            'examined_at' => now(),
        ]);
        $examination->eyes()->create(['eye' => 'OD', 'macula' => 'Edema', 'iop' => 17]);

        return $examination;
    }

    public function test_letter_reflects_the_visits_medical_examination(): void
    {
        Permission::firstOrCreate(['name' => 'examinations.view']);
        $this->user->givePermissionTo('examinations.view');
        $result = $this->makeResult();
        $examination = $this->makeExamination();

        $this->actingAs($this->user)
            ->get("/labs/results/{$result->id}/letter")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('labs/ReferralLetter')
                ->where('examination.id', $examination->id)
                ->where('examination.assessment', 'Macular edema OD')
                ->where('examination.eyes.0.macula', 'Edema')
                ->has('options.iop_methods')
            );
    }

    public function test_letter_hides_the_examination_without_view_permission(): void
    {
        $result = $this->makeResult();
        $this->makeExamination();

        $this->actingAs($this->user)
            ->get("/labs/results/{$result->id}/letter")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('examination', null)->where('options', null));
    }

    public function test_letter_without_an_examination_shows_the_recorded_result_only(): void
    {
        Permission::firstOrCreate(['name' => 'examinations.view']);
        $this->user->givePermissionTo('examinations.view');
        $result = $this->makeResult();

        $this->actingAs($this->user)
            ->get("/labs/results/{$result->id}/letter")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('examination', null)->where('result.test_name', 'OCT'));
    }
}
