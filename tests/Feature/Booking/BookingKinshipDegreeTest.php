<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Enums\AccountNature;
use Modules\Accounting\Models\Account;
use Modules\Booking\Models\Booking;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingKinshipDegreeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => 'booking.create', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'booking.edit', 'guard_name' => 'web']),
        ]);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

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

    private function createBooking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'file_no' => 'MRN-001',
            'patient_name' => 'محمد علي',
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
            'price' => 150.00,
            'discount' => 0.00,
            'ins_amount' => 0.00,
            'paid_amount' => 0.00,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
            'created_by' => $this->user->id,
        ], $attributes));
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'patient_name' => 'محمد علي',
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
            'price' => 150,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 0,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
        ], $overrides);
    }

    #[DataProvider('validKinshipDegreesProvider')]
    public function test_update_persists_each_valid_kinship_degree(string $value): void
    {
        $booking = $this->createBooking();

        $this->actingAs($this->user)
            ->put("/booking/{$booking->id}", $this->basePayload(['kinship_degree' => $value]))
            ->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'kinship_degree' => $value,
        ]);
    }

    public static function validKinshipDegreesProvider(): array
    {
        return [
            'father' => ['father'],
            'mother' => ['mother'],
            'son' => ['son'],
            'companion' => ['companion'],
        ];
    }

    public function test_update_rejects_invalid_kinship_degree(): void
    {
        $booking = $this->createBooking();

        $this->actingAs($this->user)
            ->put("/booking/{$booking->id}", $this->basePayload(['kinship_degree' => 'uncle']))
            ->assertSessionHasErrors('kinship_degree');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'kinship_degree' => null,
        ]);
    }

    public function test_store_rejects_invalid_kinship_degree(): void
    {
        $this->actingAs($this->user)
            ->post('/booking', $this->basePayload(['kinship_degree' => 'uncle']))
            ->assertSessionHasErrors('kinship_degree');
    }

    public function test_update_without_kinship_degree_leaves_it_null(): void
    {
        $booking = $this->createBooking();

        $this->actingAs($this->user)
            ->put("/booking/{$booking->id}", $this->basePayload())
            ->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'kinship_degree' => null,
        ]);
    }

    public function test_kinship_degree_casts_to_enum_on_the_model(): void
    {
        $booking = $this->createBooking(['kinship_degree' => 'father']);

        $this->assertSame('father', $booking->fresh()->kinship_degree->value);
        $this->assertSame('الوالد', $booking->fresh()->kinship_degree->label());
    }
}
