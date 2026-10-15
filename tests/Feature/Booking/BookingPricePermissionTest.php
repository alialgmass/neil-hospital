<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Enums\AccountNature;
use Modules\Accounting\Models\Account;
use Modules\Booking\Models\Booking;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingPricePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Account::create([
            'code' => '1010', 'name' => 'الخزنة الرئيسية',
            'group' => AccountGroup::Assets, 'nature' => AccountNature::Debit,
            'balance' => 0, 'is_active' => true,
        ]);
        Account::create([
            'code' => '4010', 'name' => 'إيرادات العيادة الخارجية (كشف)',
            'group' => AccountGroup::Revenues, 'nature' => AccountNature::Credit,
            'balance' => 0, 'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $role = Role::firstOrCreate(['name' => 'staff_'.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(collect($permissions)->map(
            fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
        )->all());

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function createBooking(float $price = 150): Booking
    {
        return Booking::create([
            'file_no' => 'MRN-001',
            'patient_name' => 'محمد علي',
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
            'price' => $price,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 0,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'patient_name' => 'محمد علي',
            'dept' => 'clinic',
            'eye_side' => 'OD',
            'visit_date' => '2026-04-20',
            'price' => 999,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 0,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
        ], $overrides);
    }

    public function test_creating_without_edit_prices_permission_ignores_the_typed_price(): void
    {
        $user = $this->userWithPermissions(['booking.create']);

        $this->actingAs($user)
            ->post('/booking', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertEquals(0.0, (float) Booking::firstOrFail()->price);
    }

    public function test_creating_with_edit_prices_permission_keeps_the_typed_price(): void
    {
        $user = $this->userWithPermissions(['booking.create', 'booking.edit_prices']);

        $this->actingAs($user)
            ->post('/booking', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertEquals(999.0, (float) Booking::firstOrFail()->price);
    }

    public function test_updating_without_edit_prices_permission_keeps_the_stored_price(): void
    {
        $user = $this->userWithPermissions(['booking.edit']);
        $booking = $this->createBooking(150);

        $this->actingAs($user)
            ->put("/booking/{$booking->id}", $this->payload(['price' => 1, 'patient_name' => 'اسم جديد']))
            ->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame('اسم جديد', $booking->patient_name);
        $this->assertEquals(150.0, (float) $booking->price);
    }

    public function test_updating_with_edit_prices_permission_changes_the_price(): void
    {
        $user = $this->userWithPermissions(['booking.edit', 'booking.edit_prices']);
        $booking = $this->createBooking(150);

        $this->actingAs($user)
            ->put("/booking/{$booking->id}", $this->payload(['price' => 300]))
            ->assertSessionHasNoErrors();

        $this->assertEquals(300.0, (float) $booking->refresh()->price);
    }

    public function test_price_permissions_are_seeded_for_the_expected_roles(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('booking.view_prices'));
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('booking.edit_prices'));
        $this->assertTrue(Role::findByName('reception')->hasPermissionTo('booking.edit_prices'));
        $this->assertTrue(Role::findByName('accountant')->hasPermissionTo('booking.view_prices'));
        $this->assertFalse(Role::findByName('accountant')->hasPermissionTo('booking.edit_prices'));
        $this->assertFalse(Role::findByName('call_center')->hasPermissionTo('booking.view_prices'));
    }
}
