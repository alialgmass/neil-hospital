<?php

namespace Tests\Feature\Reporting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardStatsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName): User
    {
        $permission = Permission::firstOrCreate(['name' => 'dashboard', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_sees_financial_statistics(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('canViewStats', true)
                ->has('todayStats.today_revenue')
                ->has('treasury.balance')
                ->has('revenueByDept')
                ->has('todayQueue')
            );
    }

    public function test_non_admin_does_not_receive_financial_statistics(): void
    {
        $this->actingAs($this->userWithRole('reception'))
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('canViewStats', false)
                ->where('todayStats', null)
                ->where('treasury', null)
                ->where('revenueByDept', [])
                ->where('revenueByDoc', [])
                ->has('todayQueue')
                ->has('lowStockCount')
            );
    }
}
