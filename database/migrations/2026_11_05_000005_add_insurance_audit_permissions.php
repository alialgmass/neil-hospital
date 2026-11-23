<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = ['insurance.audit'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $audit = Permission::firstOrCreate(['name' => 'insurance.audit', 'guard_name' => 'web']);
        $collect = Permission::firstOrCreate(['name' => 'insurance.collect', 'guard_name' => 'web']);
        foreach (Role::where('guard_name', 'web')->get() as $role) {
            if ($role->name === 'admin') {
                $role->givePermissionTo([$audit, $collect]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'insurance.audit')->where('guard_name', 'web')->delete();
    }
};
