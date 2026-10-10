<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const VIEW = 'booking.view_prices';

    private const EDIT = 'booking.edit_prices';

    /**
     * Creates the booking price permissions on existing installs without
     * re-running RolesPermissionsSeeder (which would reset customised roles),
     * and keeps today's behaviour: every role that could see bookings keeps
     * seeing prices (unless it is flagged hide_amounts), and every role that
     * could edit bookings keeps editing prices.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $view = Permission::firstOrCreate(['name' => self::VIEW, 'guard_name' => 'web']);
        $edit = Permission::firstOrCreate(['name' => self::EDIT, 'guard_name' => 'web']);

        foreach (Role::where('guard_name', 'web')->with('permissions')->get() as $role) {
            $isAdmin = $role->name === 'admin';
            $held = $role->permissions->pluck('name');

            if ($isAdmin || ($held->contains('booking.view') && ! $held->contains('hide_amounts'))) {
                $role->givePermissionTo($view);
            }

            if ($isAdmin || $held->contains('booking.edit')) {
                $role->givePermissionTo($edit);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', [self::VIEW, self::EDIT])->delete();
    }
};
