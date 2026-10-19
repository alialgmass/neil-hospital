<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'archive.view',
        'archive.create',
        'archive.upload',
        'archive.delete_file',
    ];

    /**
     * Splits the archive off `reports.clinical`. Existing roles that held
     * `reports.clinical` (the old archive gate) keep full archive access so
     * nothing changes until an admin narrows them from the roles screen.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = array_map(
            fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']),
            self::PERMISSIONS,
        );

        foreach (Role::where('guard_name', 'web')->with('permissions')->get() as $role) {
            if ($role->name === 'admin' || $role->permissions->contains('name', 'reports.clinical')) {
                $role->givePermissionTo($permissions);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', self::PERMISSIONS)->delete();
    }
};
