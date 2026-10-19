<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Enums\AccountNature;
use Modules\Accounting\Models\Account;
use Modules\Booking\Models\Booking;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingVisitTimeAndAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'booking_staff', 'guard_name' => 'web']);
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
            'visit_date' => '2026-10-06',
            'price' => 0,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 0,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
        ], $overrides);
    }

    public function test_new_booking_without_a_visit_time_gets_the_current_time(): void
    {
        $this->travelTo('2026-10-06 14:37:00');

        $this->actingAs($this->user)->post('/booking', $this->payload())->assertSessionHasNoErrors();

        $this->assertStringStartsWith('14:37', (string) Booking::firstOrFail()->visit_time);
    }

    public function test_a_visit_time_typed_by_the_user_is_kept(): void
    {
        $this->travelTo('2026-10-06 14:37:00');

        $this->actingAs($this->user)
            ->post('/booking', $this->payload(['visit_time' => '09:15']))
            ->assertSessionHasNoErrors();

        $this->assertStringStartsWith('09:15', (string) Booking::firstOrFail()->visit_time);
    }

    public function test_analysis_type_accepts_negative_and_positive(): void
    {
        foreach (['negative', 'positive'] as $value) {
            $this->actingAs($this->user)
                ->post('/booking', $this->payload(['analysis_type' => $value]))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(['negative', 'positive'], Booking::orderBy('created_at')->orderBy('id')->pluck('analysis_type')->sort()->values()->all());
    }

    public function test_analysis_type_rejects_any_other_value(): void
    {
        $this->actingAs($this->user)
            ->post('/booking', $this->payload(['analysis_type' => 'OCT شبكية']))
            ->assertSessionHasErrors('analysis_type');

        $this->assertSame(0, Booking::count());
    }

    public function test_updating_a_legacy_booking_keeps_its_old_analysis_type(): void
    {
        $booking = Booking::create([
            'file_no' => 'MRN-900',
            'patient_name' => 'قديم',
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
            'price' => 0,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 0,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
            'analysis_type' => 'OCT شبكية',
        ]);

        $this->actingAs($this->user)
            ->put("/booking/{$booking->id}", $this->payload(['analysis_type' => 'OCT شبكية']))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->put("/booking/{$booking->id}", $this->payload(['analysis_type' => 'تحليل آخر']))
            ->assertSessionHasErrors('analysis_type');
    }
}
