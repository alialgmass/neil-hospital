<?php

namespace Tests\Feature\Booking;

use App\Enums\EyeSide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Booking\Services\ServicePricingService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServicePricingTest extends TestCase
{
    use RefreshDatabase;

    private ServicePricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricing = app(ServicePricingService::class);
    }

    private function service(array $attributes = []): Service
    {
        return Service::create(array_merge([
            'name' => 'مياه بيضاء',
            'dept' => 'surgery',
            'price' => 1000,
            'one_eye_price' => 1000,
            'both_eyes_price' => 1800,
            'ins_price' => 1000,
        ], $attributes));
    }

    public function test_one_eye_selection_uses_one_eye_price(): void
    {
        $service = $this->service();

        $this->assertSame(1000.0, $this->pricing->resolveEyePrice($service, EyeSide::OD));
        $this->assertSame(1000.0, $this->pricing->resolveEyePrice($service, EyeSide::OS));
    }

    public function test_both_eyes_selection_uses_both_eyes_price(): void
    {
        $service = $this->service();

        $this->assertSame(1800.0, $this->pricing->resolveEyePrice($service, EyeSide::OU));
    }

    public function test_both_eyes_falls_back_to_double_one_eye_price_when_not_set(): void
    {
        $service = $this->service(['both_eyes_price' => null]);

        $this->assertSame(2000.0, $this->pricing->resolveEyePrice($service, EyeSide::OU));
    }

    public function test_falls_back_to_legacy_price_when_eye_prices_missing(): void
    {
        $service = $this->service(['one_eye_price' => null, 'both_eyes_price' => null, 'price' => 750]);

        $this->assertSame(750.0, $this->pricing->resolveEyePrice($service, EyeSide::OD));
        $this->assertSame(1500.0, $this->pricing->resolveEyePrice($service, EyeSide::OU));
    }

    public function test_missing_eye_side_defaults_to_one_eye_price(): void
    {
        $service = $this->service();

        $this->assertSame(1000.0, $this->pricing->resolveEyePrice($service, null));
    }

    public function test_zero_configured_price_is_honoured_not_treated_as_unconfigured(): void
    {
        $service = $this->service(['one_eye_price' => 0, 'both_eyes_price' => 0, 'price' => 0]);

        $this->assertSame(0.0, $this->pricing->priceFor($service->id, 'OD', 500.0));
        $this->assertSame(0.0, $this->pricing->priceFor($service->id, 'OU', 500.0));
    }

    public function test_price_for_returns_fallback_only_when_no_service(): void
    {
        $this->assertSame(250.0, $this->pricing->priceFor(null, 'OD', 250.0));
        $this->assertSame(250.0, $this->pricing->priceFor('missing-id', 'OD', 250.0));
    }

    public function test_store_booking_ignores_client_price_and_recomputes_from_service_and_eye(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'booking.create', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $user = User::factory()->create();
        $user->assignRole($role);

        $service = $this->service(['dept' => 'laser']);

        $this->actingAs($user)->post('/booking', [
            'patient_name' => 'مريض اختبار',
            'dept' => 'laser',
            'service_id' => $service->id,
            'service_name' => $service->name,
            'visit_date' => '2026-05-01',
            'eye_side' => 'OU',
            'price' => 1, // tampered — must be ignored
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1800.0, (float) Booking::firstOrFail()->price);
    }
}
