<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Enums\SystemModule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModuleExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'users.manage']);
        Permission::firstOrCreate(['name' => 'inventory.view']);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo('users.manage');

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
    }

    public function test_index_lists_all_modules(): void
    {
        $response = $this->actingAs($this->user)->get('/module-exports');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('modules', count(SystemModule::cases()))
        );
    }

    public function test_index_is_blocked_without_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/module-exports')->assertForbidden();
    }

    public function test_each_module_download_returns_xlsx(): void
    {
        foreach (SystemModule::cases() as $module) {
            $response = $this->actingAs($this->user)->get("/module-exports/{$module->value}/download");

            $response->assertStatus(200);
            $this->assertStringStartsWith(
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                $response->headers->get('Content-Type'),
            );
        }
    }
}
