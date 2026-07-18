<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Enums\SystemModule;
use Modules\Admin\Models\Setting;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemModuleToggleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'hr.view']);
        Permission::firstOrCreate(['name' => 'settings.manage']);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo(['hr.view', 'settings.manage']);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
    }

    public function test_route_is_reachable_while_its_module_is_enabled(): void
    {
        $response = $this->actingAs($this->user)->get('/employees');

        $response->assertOk();
    }

    public function test_route_is_blocked_once_its_module_is_disabled(): void
    {
        Setting::setValue(SystemModule::Hr->settingKey(), 'false', 'modules');

        $response = $this->actingAs($this->user)->get('/employees');

        $response->assertForbidden();
    }

    public function test_route_is_reachable_again_after_module_is_re_enabled(): void
    {
        Setting::setValue(SystemModule::Hr->settingKey(), 'false', 'modules');
        $this->actingAs($this->user)->get('/employees')->assertForbidden();

        Setting::setValue(SystemModule::Hr->settingKey(), 'true', 'modules');

        $this->actingAs($this->user)->get('/employees')->assertOk();
    }

    public function test_disabling_a_module_does_not_block_unrelated_routes(): void
    {
        Setting::setValue(SystemModule::Hr->settingKey(), 'false', 'modules');

        $response = $this->actingAs($this->user)->get('/settings');

        $response->assertOk();
    }

    public function test_settings_page_exposes_module_toggles(): void
    {
        $response = $this->actingAs($this->user)->get('/settings');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('systemModules', count(SystemModule::cases()))
            ->where('systemModules.0.enabled', true)
        );
    }

    public function test_settings_update_persists_module_toggle(): void
    {
        $response = $this->actingAs($this->user)->post('/settings', [
            'settings' => [
                ['key' => SystemModule::Hr->settingKey(), 'value' => 'false'],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('settings', [
            'key' => SystemModule::Hr->settingKey(),
            'value' => 'false',
        ]);
    }
}
