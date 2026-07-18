<?php

namespace Tests\Feature\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Booking\Models\Booking;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ArchiveFileUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::firstOrCreate(['name' => 'reports.clinical', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function makeCompletedBooking(): Booking
    {
        return Booking::create([
            'file_no' => 'TST-001',
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

    public function test_archive_index_includes_media_files_key(): void
    {
        $this->actingAs($this->user);
        $this->makeCompletedBooking();

        $response = $this->get(route('archive'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('admin/Archive')
            ->has('bookings.data.0.media_files')
        );
    }

    public function test_can_upload_file_to_archive_booking(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user);
        $booking = $this->makeCompletedBooking();

        $file = UploadedFile::fake()->create('report.pdf', 500, 'application/pdf');

        $response = $this->post(route('archive.upload', $booking), ['file' => $file]);

        $response->assertRedirect();
        $this->assertCount(1, $booking->fresh()->getMedia('archive-files'));
    }

    public function test_upload_validates_file_type(): void
    {
        $this->actingAs($this->user);
        $booking = $this->makeCompletedBooking();

        $file = UploadedFile::fake()->create('script.exe', 100, 'application/octet-stream');

        $response = $this->post(route('archive.upload', $booking), ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertCount(0, $booking->getMedia('archive-files'));
    }

    public function test_upload_rejects_files_over_20mb(): void
    {
        $this->actingAs($this->user);
        $booking = $this->makeCompletedBooking();

        $file = UploadedFile::fake()->create('large.pdf', 25000, 'application/pdf');

        $response = $this->post(route('archive.upload', $booking), ['file' => $file]);

        $response->assertSessionHasErrors('file');
    }

    public function test_can_delete_media_from_archive(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user);
        $booking = $this->makeCompletedBooking();

        $file = UploadedFile::fake()->image('scan.jpg');
        $booking->addMedia($file)->toMediaCollection('archive-files');

        $media = $booking->getFirstMedia('archive-files');
        $this->assertNotNull($media);

        $response = $this->delete(route('archive.media.destroy', $media));

        $response->assertRedirect();
        $this->assertNull(Media::find($media->id));
    }

    public function test_store_attaches_files_when_provided(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user);

        $payload = [
            'patient_name' => 'مريض مع ملف',
            'dept' => 'clinic',
            'visit_date' => now()->toDateString(),
            'files' => [
                UploadedFile::fake()->create('report.pdf', 200, 'application/pdf'),
                UploadedFile::fake()->image('scan.jpg'),
            ],
        ];

        $response = $this->post(route('archive.store'), $payload);

        $response->assertRedirect(route('archive'));

        $booking = Booking::where('patient_name', 'مريض مع ملف')->firstOrFail();
        $this->assertCount(2, $booking->getMedia('archive-files'));
    }

    public function test_store_works_without_files(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('archive.store'), [
            'patient_name' => 'مريض بلا ملفات',
            'dept' => 'labs',
            'visit_date' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('archive'));
        $this->assertDatabaseHas('bookings', ['patient_name' => 'مريض بلا ملفات']);
    }

    public function test_guests_cannot_upload_files(): void
    {
        $booking = $this->makeCompletedBooking();
        $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

        $response = $this->post(route('archive.upload', $booking), ['file' => $file]);

        $response->assertRedirect(route('login'));
    }
}
