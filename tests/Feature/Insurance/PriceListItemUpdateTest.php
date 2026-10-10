<?php

namespace Tests\Feature\Insurance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Service;
use Modules\Insurance\Models\InsuranceCompany;
use Modules\Insurance\Models\PriceList;
use Modules\Insurance\Models\PriceListItem;
use Modules\Insurance\Services\InsuranceService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PriceListItemUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'insurance.price_lists.edit', 'guard_name' => 'web']));
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    public function test_updating_a_price_list_item_changes_its_price(): void
    {
        $company = InsuranceCompany::create(['name' => 'شركة تأمين', 'coverage_pct' => 80, 'disc_pct' => 0, 'status' => 'active']);
        $service = Service::create(['name' => 'كشف', 'dept' => 'clinic', 'price' => 200]);
        $priceList = PriceList::create(['name' => 'قائمة 2026', 'type' => 'insurance', 'ins_company_id' => $company->id, 'is_active' => true]);
        $item = PriceListItem::create(['price_list_id' => $priceList->id, 'service_id' => $service->id, 'price' => 300]);

        $this->actingAs($this->user)
            ->put("/insurance/price-lists/items/{$item->id}", ['price' => 450])
            ->assertRedirect();

        $this->assertEquals(450.0, (float) $item->fresh()->price);
    }

    public function test_price_change_does_not_affect_historical_coverage_already_computed(): void
    {
        $company = InsuranceCompany::create(['name' => 'شركة ب', 'coverage_pct' => 80, 'disc_pct' => 0, 'status' => 'active']);
        $service = Service::create(['name' => 'كشف 2', 'dept' => 'clinic', 'price' => 200]);
        $priceList = PriceList::create(['name' => 'قائمة قديمة', 'type' => 'insurance', 'ins_company_id' => $company->id, 'is_active' => true]);
        $item = PriceListItem::create(['price_list_id' => $priceList->id, 'service_id' => $service->id, 'price' => 300]);

        // Coverage computed at the old price (snapshot semantics).
        $before = app(InsuranceService::class)->calculateInsuranceCoverage($service->id, $company->id);
        $this->assertEquals(300.0, $before['base_price']);

        $this->actingAs($this->user)->put("/insurance/price-lists/items/{$item->id}", ['price' => 500])->assertRedirect();

        // A NEW coverage calculation now uses the updated price...
        $after = app(InsuranceService::class)->calculateInsuranceCoverage($service->id, $company->id);
        $this->assertEquals(500.0, $after['base_price']);

        // ...but the earlier computed figure ($before) is just a plain array,
        // already returned to the caller — it was never a live reference and
        // is naturally unaffected by the later price change.
        $this->assertEquals(300.0, $before['base_price']);
    }

    public function test_user_without_price_list_edit_permission_cannot_update_price(): void
    {
        $company = InsuranceCompany::create(['name' => 'شركة ج', 'coverage_pct' => 80, 'disc_pct' => 0, 'status' => 'active']);
        $service = Service::create(['name' => 'كشف 3', 'dept' => 'clinic', 'price' => 200]);
        $priceList = PriceList::create(['name' => 'قائمة', 'type' => 'insurance', 'ins_company_id' => $company->id, 'is_active' => true]);
        $item = PriceListItem::create(['price_list_id' => $priceList->id, 'service_id' => $service->id, 'price' => 300]);

        $plainUser = User::factory()->create();

        $this->actingAs($plainUser)
            ->put("/insurance/price-lists/items/{$item->id}", ['price' => 999])
            ->assertForbidden();

        $this->assertEquals(300.0, (float) $item->fresh()->price);
    }
}
