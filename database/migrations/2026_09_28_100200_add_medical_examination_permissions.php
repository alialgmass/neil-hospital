<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'examinations.view',
        'examinations.create',
        'examinations.update',
        'examinations.delete',
        'examinations.print',
    ];

    /**
     * Grants the new examination permissions on existing installs without
     * re-running RolesPermissionsSeeder (which would reset customised roles).
     * Mirrors the seeder's role map for these keys.
     *
     * @var array<string, array<int, string>>
     */
    private const ROLE_PERMISSIONS = [
        'admin' => self::PERMISSIONS,
        'doctor' => ['examinations.view', 'examinations.create', 'examinations.update', 'examinations.print'],
        'nurse' => ['examinations.view'],
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permissions);
        }
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', self::PERMISSIONS)->delete();
    }
};
