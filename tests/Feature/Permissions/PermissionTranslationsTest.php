<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use App\Services\PermissionLabelService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionTranslationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_seeded_permission_has_arabic_and_english_labels(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $labels = trans('permissions.labels', [], $locale);
            $groups = trans('permissions.groups', [], $locale);

            foreach (RolesPermissionsSeeder::PERMISSIONS as $permission) {
                $this->assertArrayHasKey($permission, $labels, "Missing {$locale} label for {$permission}");
                $this->assertNotSame('', trim($labels[$permission]));

                $group = PermissionLabelService::groupOf($permission);
                $this->assertArrayHasKey($group, $groups, "Missing {$locale} group label for {$group}");
            }
        }
    }

    public function test_every_seeded_role_has_arabic_and_english_labels(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        foreach (['ar', 'en'] as $locale) {
            $roles = trans('permissions.roles', [], $locale);

            foreach (Role::pluck('name') as $role) {
                $this->assertArrayHasKey($role, $roles, "Missing {$locale} label for role {$role}");
            }
        }
    }

    public function test_labels_default_to_arabic_and_fall_back_to_the_key(): void
    {
        $labels = app(PermissionLabelService::class);

        $this->assertSame('إضافة حجز', $labels->permission('booking.create'));
        $this->assertSame('Create booking', $labels->permission('booking.create', 'en'));
        $this->assertSame('تعديل قوائم أسعار التأمين', $labels->permission('insurance.price_lists.edit'));
        $this->assertSame('unknown.key', $labels->permission('unknown.key'));
    }

    public function test_roles_screen_receives_translated_labels_instead_of_raw_keys(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get('/roles')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Roles')
                ->where('allPermissions', fn ($permissions) => collect($permissions)
                    ->firstWhere('name', 'booking.create')['label'] === 'إضافة حجز')
                ->where('allPermissions', fn ($permissions) => collect($permissions)
                    ->every(fn ($p) => $p['label'] !== $p['name'] && $p['group_label'] !== ''))
                ->where('roles', fn ($roles) => collect($roles)->firstWhere('name', 'doctor')['label'] === 'طبيب')
            );
    }

    public function test_employee_permissions_modal_receives_translated_labels_grouped_by_module(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get('/employees')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('hr/Employees')
                ->where('permissions_by_module.booking', fn ($permissions) => collect($permissions)
                    ->firstWhere('name', 'booking.delete')['label'] === 'حذف حجز'
                    && collect($permissions)->every(fn ($p) => $p['group_label'] === 'الحجوزات'))
                ->where('permissions_by_module.insurance', fn ($permissions) => collect($permissions)
                    ->contains(fn ($p) => $p['name'] === 'insurance.price_lists.edit' && $p['label'] === 'تعديل قوائم أسعار التأمين'))
                ->where('roles', fn ($roles) => collect($roles)->every(fn ($role) => $role['label'] !== ''))
            );
    }
}
