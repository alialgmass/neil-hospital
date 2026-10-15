<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'suppliers.write',
        'suppliers.pay',
        'purchases.create',
        'item_sales.create',
        'stock_issue.write',
        'stock_permits.write',
        'purchase_returns.create',
        'supply_bundles.write',
    ];

    /** Roles that could write to inventory keep every split-out capability. */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = array_map(
            fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']),
            self::PERMISSIONS,
        );

        foreach (Role::where('guard_name', 'web')->with('permissions')->get() as $role) {
            if ($role->name === 'admin' || $role->permissions->contains('name', 'inventory.write')) {
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
