<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Booking\Models\Service;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['services.view', 'services.write'] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'كشف',
            'dept' => 'clinic',
            'price' => 100,
            'center_type' => 'fixed',
            'center_val' => 10,
        ], $overrides);
    }

    public function test_code_is_generated_per_department_when_omitted(): void
    {
        $this->actingAs($this->user)->post('/services', $this->payload(['name' => 'كشف 1']))->assertSessionHasNoErrors();
        $this->post('/services', $this->payload(['name' => 'كشف 2']))->assertSessionHasNoErrors();
        $this->post('/services', $this->payload(['name' => 'عملية', 'dept' => 'surgery']))->assertSessionHasNoErrors();

        $this->assertSame('CLN-0001', Service::where('name', 'كشف 1')->value('code'));
        $this->assertSame('CLN-0002', Service::where('name', 'كشف 2')->value('code'));
        $this->assertSame('SRG-0001', Service::where('name', 'عملية')->value('code'));
    }

    public function test_manual_code_is_kept_and_uppercased(): void
    {
        $this->actingAs($this->user)->post('/services', $this->payload(['code' => 'ex-100']))->assertSessionHasNoErrors();

        $this->assertSame('EX-100', Service::sole()->code);
    }

    public function test_code_must_be_unique_and_well_formed(): void
    {
        $this->actingAs($this->user)->post('/services', $this->payload(['code' => 'DUP-1']))->assertSessionHasNoErrors();

        $this->post('/services', $this->payload(['name' => 'آخر', 'code' => 'dup-1']))->assertSessionHasErrors('code');
        $this->post('/services', $this->payload(['name' => 'آخر', 'code' => 'bad code!']))->assertSessionHasErrors('code');
    }

    public function test_update_can_change_code_but_blank_keeps_it(): void
    {
        $service = Service::create($this->payload(['code' => 'AAA-1']));
        $other = Service::create($this->payload(['name' => 'ثاني', 'code' => 'BBB-1']));

        $this->actingAs($this->user)->put("/services/{$service->id}", $this->payload(['code' => 'AAA-2']))->assertSessionHasNoErrors();
        $this->assertSame('AAA-2', $service->fresh()->code);

        $this->put("/services/{$service->id}", $this->payload(['code' => '']))->assertSessionHasNoErrors();
        $this->assertSame('AAA-2', $service->fresh()->code);

        $this->put("/services/{$service->id}", $this->payload(['code' => 'bbb-1']))->assertSessionHasErrors('code');
        $this->put("/services/{$other->id}", $this->payload(['name' => 'ثاني', 'code' => 'BBB-1']))->assertSessionHasNoErrors();
    }

    public function test_services_list_is_searchable_by_code(): void
    {
        Service::create($this->payload(['name' => 'أول', 'code' => 'FIND-7']));
        Service::create($this->payload(['name' => 'ثاني', 'code' => 'OTHER-1']));

        $this->actingAs($this->user)->get('/services?search=find-7')
            ->assertInertia(fn (Assert $page) => $page->has('services.data', 1)->where('services.data.0.name', 'أول'));
    }
}
