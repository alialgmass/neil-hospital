<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermissionsSeeder extends Seeder
{
    /** All system permissions (from spec §6). */
    private const PERMISSIONS = [
        'dashboard',
        'booking.view',
        'booking.create',
        'booking.edit',
        'booking.delete',
        'booking.pay',
        'clinic.view',
        'clinic.write',
        'labs.view',
        'labs.write',
        'surgery.view',
        'surgery.write',
        'lasik.view',
        'lasik.write',
        'laser.view',
        'laser.write',
        'treasury.view',
        'treasury.write',
        'journal.view',
        'journal.write',
        'reports.financial',
        'reports.clinical',
        'doctors.view',
        'doctors.write',
        'drpayments.view',
        'drpayments.write',
        'services.view',
        'services.write',
        'inventory.view',
        'inventory.write',
        'insurance.view',
        'insurance.write',
        'users.manage',
        'settings.manage',
        'hide_amounts',
    ];

    /** Role → permission map (admin gets everything in run()). */
    private const ROLE_PERMISSIONS = [
        'doctor' => [
            'dashboard',
            'booking.view',
            'clinic.view',
            'clinic.write',
            'labs.view',
            'surgery.view',
            'surgery.write',
            'lasik.view',
            'lasik.write',
            'laser.view',
            'laser.write',
            'reports.clinical',
            'drpayments.view',
            'hide_amounts',
        ],
        'reception' => [
            'dashboard',
            'booking.view',
            'booking.create',
            'booking.edit',
            'clinic.view',
            'labs.view',
            'surgery.view',
            'lasik.view',
            'laser.view',
        ],
        'accountant' => [
            'dashboard',
            'booking.view',
            'booking.pay',
            'treasury.view',
            'treasury.write',
            'journal.view',
            'journal.write',
            'reports.financial',
            'drpayments.view',
            'drpayments.write',
            'insurance.view',
        ],
        'nurse' => [
            'dashboard',
            'booking.view',
            'booking.create',
            'clinic.view',
            'surgery.view',
            'lasik.view',
            'laser.view',
            'inventory.view',
        ],
        'store_keeper' => [
            'dashboard',
            'inventory.view',
            'inventory.write',
            'services.view',
        ],
    ];

    public function run(): void
    {
        // Reset cached roles & permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create admin role with ALL permissions
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(self::PERMISSIONS);

        // Create remaining roles with their permission subsets
        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($permissions);
        }
    }
}
