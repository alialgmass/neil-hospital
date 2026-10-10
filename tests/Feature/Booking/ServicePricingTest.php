<?php

namespace Tests\Feature\Booking;

use App\Enums\EyeSide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Booking\Services\ServicePricingService;
use Modules\Insurance\Models\InsuranceCompany;
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

    public function test_insurance_uses_insurance_one_eye_and_both_eyes_prices(): void
    {
        $service = $this->service(['ins_one_eye_price' => 800, 'ins_both_eyes_price' => 1500]);

        $this->assertSame(800.0, $this->pricing->resolveInsuranceEyePrice($service, EyeSide::OD));
        $this->assertSame(1500.0, $this->pricing->resolveInsuranceEyePrice($service, EyeSide::OU));
        $this->assertSame(800.0, $this->pricing->priceFor($service->id, 'OS', null, true));
        $this->assertSame(1500.0, $this->pricing->priceFor($service->id, 'OU', null, true));
    }

    public function test_insurance_both_eyes_falls_back_to_double_insurance_one_eye_price(): void
    {
        $service = $this->service(['ins_one_eye_price' => 800, 'ins_both_eyes_price' => null]);

        $this->assertSame(1600.0, $this->pricing->resolveInsuranceEyePrice($service, EyeSide::OU));
    }

    public function test_insurance_falls_back_to_general_insurance_price(): void
    {
        $service = $this->service(['ins_price' => 900]);

        $this->assertSame(900.0, $this->pricing->resolveInsuranceEyePrice($service, EyeSide::OD));
        $this->assertSame(1800.0, $this->pricing->resolveInsuranceEyePrice($service, EyeSide::OU));
    }

    public function test_insurance_falls_back_to_cash_eye_prices_when_no_insurance_price_configured(): void
    {
        $service = $this->service(['ins_price' => 0]);

        $this->assertSame(1000.0, $this->pricing->resolveInsuranceEyePrice($service, EyeSide::OD));
        $this->assertSame(1800.0, $this->pricing->resolveInsuranceEyePrice($service, EyeSide::OU));
    }

    public function test_cash_pricing_ignores_insurance_eye_prices(): void
    {
        $service = $this->service(['ins_one_eye_price' => 800, 'ins_both_eyes_price' => 1500]);

        $this->assertSame(1000.0, $this->pricing->priceFor($service->id, 'OD'));
        $this->assertSame(1800.0, $this->pricing->priceFor($service->id, 'OU'));
    }

    public function test_insurance_multi_service_pricing_uses_insurance_eye_prices(): void
    {
        $service = $this->service(['dept' => 'labs', 'ins_one_eye_price' => 300, 'ins_both_eyes_price' => 500]);

        $lines = $this->pricing->priceForMany([$service->id], 'OU', true);

        $this->assertSame(500.0, $lines[0]['price']);
    }

    public function test_store_insurance_booking_uses_insurance_both_eyes_price(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'booking.create', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $user = User::factory()->create();
        $user->assignRole($role);

        $company = InsuranceCompany::create(['name' => 'شركة تأمين', 'coverage_pct' => 80]);
        $service = $this->service(['dept' => 'laser', 'ins_one_eye_price' => 700, 'ins_both_eyes_price' => 1300]);

        $this->actingAs($user)->post('/booking', [
            'patient_name' => 'مريض تأمين',
            'dept' => 'laser',
            'service_id' => $service->id,
            'service_name' => $service->name,
            'visit_date' => '2026-05-01',
            'eye_side' => 'OU',
            'price' => 1, // tampered — must be ignored
            'pay_method' => 'insurance',
            'ins_company_id' => $company->id,
            'pay_status' => 'unpaid',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1300.0, (float) Booking::firstOrFail()->price);
    }
}
