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

        foreach (['archive.view', 'archive.create', 'archive.upload', 'archive.delete_file', 'booking.view', 'reports.clinical'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(['archive.view', 'archive.create', 'archive.upload', 'archive.delete_file']);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
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

    public function test_booking_and_report_permissions_do_not_grant_archive_access(): void
    {
        $booking = $this->makeCompletedBooking();
        $user = $this->userWith(['booking.view', 'reports.clinical']);

        $this->actingAs($user)->get(route('archive'))->assertForbidden();
        $this->actingAs($user)->post(route('archive.store'), [])->assertForbidden();
        $this->actingAs($user)
            ->post(route('archive.upload', $booking), ['file' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf')])
            ->assertForbidden();
    }

    public function test_archive_view_alone_cannot_create_upload_or_delete(): void
    {
        Storage::fake('public');

        $booking = $this->makeCompletedBooking();
        $booking->addMedia(UploadedFile::fake()->image('scan.jpg'))->toMediaCollection('archive-files');
        $media = $booking->getFirstMedia('archive-files');

        $user = $this->userWith(['archive.view']);

        $this->actingAs($user)->get(route('archive'))->assertOk();
        $this->actingAs($user)->post(route('archive.store'), [])->assertForbidden();
        $this->actingAs($user)
            ->post(route('archive.upload', $booking), ['file' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf')])
            ->assertForbidden();
        $this->actingAs($user)->delete(route('archive.media.destroy', $media))->assertForbidden();
        $this->assertNotNull(Media::find($media->id));
    }

    public function test_archive_staff_can_open_patient_file_without_booking_permission(): void
    {
        $this->makeCompletedBooking();

        $this->actingAs($this->userWith(['archive.view']))
            ->get(route('booking.patient-file', 'TST-001'))
            ->assertOk();

        $this->actingAs($this->userWith(['booking.view']))
            ->get(route('booking.patient-file', 'TST-001'))
            ->assertOk();

        $this->actingAs($this->userWith([]))
            ->get(route('booking.patient-file', 'TST-001'))
            ->assertForbidden();
    }

    public function test_each_archive_action_has_its_own_permission(): void
    {
        Storage::fake('public');

        $booking = $this->makeCompletedBooking();
        $booking->addMedia(UploadedFile::fake()->image('scan.jpg'))->toMediaCollection('archive-files');
        $media = $booking->getFirstMedia('archive-files');

        $this->actingAs($this->userWith(['archive.upload']))
            ->post(route('archive.upload', $booking), ['file' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf')])
            ->assertRedirect();

        $this->actingAs($this->userWith(['archive.upload']))
            ->delete(route('archive.media.destroy', $media))
            ->assertForbidden();

        $this->actingAs($this->userWith(['archive.delete_file']))
            ->delete(route('archive.media.destroy', $media))
            ->assertRedirect();
    }

    public function test_guests_cannot_upload_files(): void
    {
        $booking = $this->makeCompletedBooking();
        $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

        $response = $this->post(route('archive.upload', $booking), ['file' => $file]);

        $response->assertRedirect(route('login'));
    }
}
