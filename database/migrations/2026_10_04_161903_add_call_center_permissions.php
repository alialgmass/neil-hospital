<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = ['callcenter.view', 'callcenter.write'];

    /**
     * Creates the call-center permissions and role on existing installs
     * without re-running RolesPermissionsSeeder (which would reset
     * customised roles). Mirrors the seeder for these keys.
     *
     * @var array<string, array<int, string>>
     */
    private const ROLE_PERMISSIONS = [
        'admin' => self::PERMISSIONS,
        'call_center' => ['dashboard', 'callcenter.view', 'callcenter.write', 'hide_amounts'],
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            foreach ($permissions as $permission) {
                $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::where('name', 'call_center')->where('guard_name', 'web')->delete();
        Permission::whereIn('name', self::PERMISSIONS)->delete();
    }
};
