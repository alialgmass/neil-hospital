<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Admin\Models\Setting;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsLogoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'settings.manage']);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo(['settings.manage']);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
    }

    public function test_hospital_logo_url_is_null_when_none_uploaded(): void
    {
        $this->assertNull(Setting::logoUrl());
    }

    public function test_admin_can_upload_hospital_logo(): void
    {
        $file = UploadedFile::fake()->image('logo.png');

        $response = $this->actingAs($this->user)->post('/settings/logo', [
            'logo' => $file,
        ]);

        $response->assertRedirect();
        $this->assertNotNull(Setting::logoUrl());
    }

    public function test_uploading_a_new_logo_replaces_the_previous_one(): void
    {
        $this->actingAs($this->user)->post('/settings/logo', [
            'logo' => UploadedFile::fake()->image('first.png'),
        ]);
        $firstUrl = Setting::logoUrl();

        $this->actingAs($this->user)->post('/settings/logo', [
            'logo' => UploadedFile::fake()->image('second.png'),
        ]);
        $secondUrl = Setting::logoUrl();

        $this->assertNotSame($firstUrl, $secondUrl);

        $setting = Setting::where('key', 'hospital_logo')->firstOrFail();
        $this->assertCount(1, $setting->getMedia('logo'));
    }

    public function test_hospital_logo_url_is_shared_via_inertia_settings_prop(): void
    {
        $response = $this->actingAs($this->user)->get('/settings');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('hospitalLogoUrl'));
    }
}
