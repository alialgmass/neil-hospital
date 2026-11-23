<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Stock take moves from the generic inventory permissions to its own.
     * Whoever could view/adjust it before (via inventory.view / inventory.write,
     * on a role or directly on the user) keeps that access, so existing
     * customised roles are not reset.
     *
     * @var array<string, string>
     */
    private const GRANTED_FROM = [
        'stocktake.view' => 'inventory.view',
        'stocktake.adjust' => 'inventory.write',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::GRANTED_FROM as $permissionName => $sourceName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
            $source = Permission::where('name', $sourceName)->where('guard_name', 'web')->first();

            if (! $source) {
                continue;
            }

            $source->roles->each->givePermissionTo($permission);
            $source->users->each->givePermissionTo($permission);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', array_keys(self::GRANTED_FROM))->delete();
    }
};
