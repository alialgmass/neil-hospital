<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Booking\Models\Booking;
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

    private function makeBooking(string $fileNo): Booking
    {
        return Booking::create([
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
        ]);
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
}
