<?php

namespace Tests\Feature\Insurance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Insurance\Models\InsuranceCompany;
use Modules\Insurance\Models\PriceList;
use Modules\Insurance\Models\PriceListItem;
use Modules\Inventory\Models\Service;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PriceListUpdateTest extends TestCase
{
    use RefreshDatabase;

    private InsuranceCompany $company;

    private PriceList $priceList;

    private Service $serviceA;

    private Service $serviceB;

    private Service $serviceC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = InsuranceCompany::create(['name' => 'شركة أ', 'coverage_pct' => 80, 'disc_pct' => 0, 'status' => 'active']);
        $this->serviceA = Service::create(['name' => 'كشف', 'dept' => 'clinic', 'price' => 200]);
        $this->serviceB = Service::create(['name' => 'أشعة', 'dept' => 'clinic', 'price' => 300]);
        $this->serviceC = Service::create(['name' => 'تحليل', 'dept' => 'clinic', 'price' => 150]);

        $this->priceList = PriceList::create([
            'name' => 'قائمة 2026', 'type' => 'insurance', 'ins_company_id' => $this->company->id,
            'ins_coverage' => 80, 'discount_pct' => 0, 'is_active' => true,
        ]);
        PriceListItem::create(['price_list_id' => $this->priceList->id, 'service_id' => $this->serviceA->id, 'price' => 250]);
        PriceListItem::create(['price_list_id' => $this->priceList->id, 'service_id' => $this->serviceB->id, 'price' => 350]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'role_'.uniqid(), 'guard_name' => 'web']);

        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'قائمة 2026 معدلة',
            'type' => 'insurance',
            'ins_company_id' => $this->company->id,
            'ins_coverage' => 70,
            'discount_pct' => 5,
            'notes' => 'ملاحظة',
            'is_active' => false,
            'items' => [
                ['service_id' => $this->serviceA->id, 'price' => 275],   // updated
                ['service_id' => $this->serviceC->id, 'price' => 120],   // added
                // serviceB omitted → removed
            ],
        ], $overrides);
    }

    public function test_user_with_permission_can_edit_header_and_sync_items(): void
    {
        $user = $this->userWithPermissions(['insurance.view', 'insurance.price_lists.edit']);

        $this->actingAs($user)
            ->put("/insurance/price-lists/{$this->priceList->id}", $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $priceList = $this->priceList->fresh('items');

        $this->assertSame('قائمة 2026 معدلة', $priceList->name);
        $this->assertSame(70.0, $priceList->ins_coverage);
        $this->assertSame(5.0, $priceList->discount_pct);
        $this->assertFalse($priceList->is_active);

        $prices = $priceList->items->pluck('price', 'service_id');
        $this->assertCount(2, $prices);
        $this->assertEquals(275.0, $prices[$this->serviceA->id]);
        $this->assertEquals(120.0, $prices[$this->serviceC->id]);
        $this->assertArrayNotHasKey($this->serviceB->id, $prices->all());
    }

    public function test_existing_item_rows_are_updated_in_place_not_recreated(): void
    {
        $user = $this->userWithPermissions(['insurance.price_lists.edit']);
        $originalId = PriceListItem::where('service_id', $this->serviceA->id)->value('id');

        $this->actingAs($user)->put("/insurance/price-lists/{$this->priceList->id}", $this->payload())->assertRedirect();

        $this->assertSame($originalId, PriceListItem::where('service_id', $this->serviceA->id)->value('id'));
    }

    public function test_user_without_permission_cannot_edit(): void
    {
        $user = $this->userWithPermissions(['insurance.view']);

        $this->actingAs($user)
            ->put("/insurance/price-lists/{$this->priceList->id}", $this->payload())
            ->assertForbidden();

        $this->assertSame('قائمة 2026', $this->priceList->fresh()->name);
        $this->assertSame(2, $this->priceList->items()->count());
    }

    public function test_insurance_write_alone_is_not_enough_to_edit_price_lists(): void
    {
        $user = $this->userWithPermissions(['insurance.view', 'insurance.write']);

        $this->actingAs($user)
            ->put("/insurance/price-lists/{$this->priceList->id}", $this->payload())
            ->assertForbidden();
    }

    public function test_json_api_request_without_permission_is_rejected_with_403(): void
    {
        $user = $this->userWithPermissions(['insurance.view']);

        $this->actingAs($user)
            ->putJson("/insurance/price-lists/{$this->priceList->id}", $this->payload())
            ->assertStatus(403);
    }

    public function test_validation_rejects_duplicate_services_invalid_percentages_and_negative_prices(): void
    {
        $user = $this->userWithPermissions(['insurance.price_lists.edit']);

        $this->actingAs($user)
            ->put("/insurance/price-lists/{$this->priceList->id}", $this->payload([
                'name' => '',
                'type' => 'bogus',
                'ins_coverage' => 150,
                'discount_pct' => -1,
                'items' => [
                    ['service_id' => $this->serviceA->id, 'price' => 100],
                    ['service_id' => $this->serviceA->id, 'price' => -5],
                    ['service_id' => 'missing-service', 'price' => 10],
                ],
            ]))
            ->assertSessionHasErrors([
                'name', 'type', 'ins_coverage', 'discount_pct',
                'items.0.service_id', 'items.1.service_id', 'items.1.price', 'items.2.service_id',
            ]);

        $this->assertSame('قائمة 2026', $this->priceList->fresh()->name);
        $this->assertEquals(250.0, PriceListItem::where('service_id', $this->serviceA->id)->value('price'));
    }

    public function test_non_editable_fields_are_ignored(): void
    {
        $user = $this->userWithPermissions(['insurance.price_lists.edit']);
        $originalId = $this->priceList->id;
        $originalCreatedAt = $this->priceList->created_at->toDateTimeString();

        $this->actingAs($user)
            ->put("/insurance/price-lists/{$this->priceList->id}", $this->payload([
                'id' => 'hijacked-id',
                'created_at' => '2000-01-01 00:00:00',
                'items' => [
                    ['service_id' => $this->serviceA->id, 'price' => 275, 'price_list_id' => 'other-list', 'id' => 999999],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNotNull(PriceList::find($originalId));
        $this->assertNull(PriceList::find('hijacked-id'));
        $this->assertSame($originalCreatedAt, PriceList::find($originalId)->created_at->toDateTimeString());
        $this->assertSame(0, PriceListItem::where('price_list_id', 'other-list')->count());
        $this->assertSame(1, PriceListItem::where('price_list_id', $originalId)->count());
    }

    public function test_all_items_can_be_removed_while_keeping_the_list(): void
    {
        $user = $this->userWithPermissions(['insurance.price_lists.edit']);

        $this->actingAs($user)
            ->put("/insurance/price-lists/{$this->priceList->id}", $this->payload(['items' => []]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $this->priceList->items()->count());
        $this->assertNotNull($this->priceList->fresh());
    }
}
