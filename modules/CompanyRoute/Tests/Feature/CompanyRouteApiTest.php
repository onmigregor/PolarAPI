<?php

namespace Modules\CompanyRoute\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Modules\CompanyRoute\Models\CompanyRoute;
use Modules\Region\Models\Region;
use Modules\User\Models\User;
use Tests\TestCase;

class CompanyRouteApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $region;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->region = Region::factory()->create();
    }

    public function test_can_list_company_routes()
    {
        CompanyRoute::create([
            'name' => 'Test Company Route',
            'code' => 'TC001',
            'rif' => 'J-12345678-9',
            'fiscal_address' => 'Test Address',
            'region_id' => $this->region->id,
            'db_name' => 'test_db'
        ]);

        $response = $this->actingAs($this->user)
                         ->getJson('/api/company-routes');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'meta']);
    }

    public function test_can_list_all_company_routes_for_selector()
    {
        CompanyRoute::create([
            'name' => 'Selector Company Route',
            'code' => 'SEL001',
            'rif' => 'J-55555555-5',
            'fiscal_address' => 'Selector Address',
            'region_id' => $this->region->id,
            'db_name' => 'sel_db'
        ]);

        $response = $this->actingAs($this->user)
                         ->getJson('/api/company-routes/all');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data']);
    }

    public function test_can_filter_company_routes_by_region()
    {
        $otherRegion = Region::factory()->create();

        CompanyRoute::create([
            'name' => 'Target Route',
            'code' => 'TR001',
            'rif' => 'J-11111111-1',
            'fiscal_address' => 'Target Address',
            'region_id' => $this->region->id,
            'db_name' => 'target_db'
        ]);

        CompanyRoute::create([
            'name' => 'Other Route',
            'code' => 'OR001',
            'rif' => 'J-22222222-2',
            'fiscal_address' => 'Other Address',
            'region_id' => $otherRegion->id,
            'db_name' => 'other_db'
        ]);

        // Filter by the first region
        $response = $this->actingAs($this->user)
                         ->getJson("/api/company-routes?region_id={$this->region->id}");

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.name', 'Target Route');
    }

    public function test_can_create_company_route()
    {
        $data = [
            'name' => 'New Company Route',
            'code' => 'NC001',
            'rif' => 'J-98765432-1',
            'fiscal_address' => 'New Address',
            'region_id' => $this->region->id,
            'db_name' => 'new_company_route_db'
        ];

        $response = $this->actingAs($this->user)
                         ->postJson('/api/company-routes', $data);

        $response->assertStatus(201)
                 ->assertJsonPath('data.name', 'New Company Route');

        $this->assertDatabaseHas('company_routes', ['name' => 'New Company Route']);
    }

    public function test_can_update_company_route()
    {
        $companyRoute = CompanyRoute::create([
            'name' => 'Old Name',
            'code' => 'OLD001',
            'rif' => 'J-98765432-1',
            'fiscal_address' => 'Old Address',
            'region_id' => $this->region->id,
            'db_name' => 'old_db'
        ]);

        $data = [
             'name' => 'Updated Name',
             'code' => 'OLD001', // Keep same code
             'rif' => 'J-98765432-1',
             'fiscal_address' => 'Old Address',
             'region_id' => $this->region->id,
             'db_name' => 'old_db'
        ];

        $response = $this->actingAs($this->user)
                         ->putJson("/api/company-routes/{$companyRoute->id}", $data);

        $response->assertStatus(200)
                 ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_can_delete_company_route()
    {
        $companyRoute = CompanyRoute::create([
            'name' => 'To Delete',
            'code' => 'DEL001',
            'rif' => 'J-00000000-0',
            'fiscal_address' => 'Delete Address',
            'db_name' => 'delete_db',
            'region_id' => $this->region->id
        ]);

        $response = $this->actingAs($this->user)
                         ->deleteJson("/api/company-routes/{$companyRoute->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('company_routes', ['id' => $companyRoute->id]);
    }
}
