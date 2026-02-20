<?php

namespace Modules\Analytics\Tests\Feature;

use Tests\TestCase;
use Modules\Region\Models\Region;
use Modules\CompanyRoute\Models\CompanyRoute;
use Modules\Analytics\Services\TenantConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AnalyticsFilterTest extends TestCase
{
    use RefreshDatabase;

    private TenantConnectionService $tenantService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantService = new TenantConnectionService();
    }

    public function test_can_resolve_clients_by_explicit_ids()
    {
        $region = Region::create(['citCode' => 'R1', 'citName' => 'Region 1', 'staCode' => 'S1']);
        $client1 = CompanyRoute::create(['name' => 'C1', 'code' => 'CODE1', 'rif' => 'J-1', 'fiscal_address' => 'Address 1', 'region_id' => $region->id, 'db_name' => 'db1']);
        $client2 = CompanyRoute::create(['name' => 'C2', 'code' => 'CODE2', 'rif' => 'J-2', 'fiscal_address' => 'Address 2', 'region_id' => $region->id, 'db_name' => 'db2']);

        $results = $this->tenantService->resolveClients([$client1->id], null);

        $this->assertCount(1, $results);
        $this->assertEquals($client1->id, $results->first()->id);
    }

    public function test_can_resolve_clients_by_region_ids()
    {
        $region1 = Region::create(['citCode' => 'R1', 'citName' => 'Region 1', 'staCode' => 'S1']);
        $region2 = Region::create(['citCode' => 'R2', 'citName' => 'Region 2', 'staCode' => 'S2']);

        $client1 = CompanyRoute::create(['name' => 'C1', 'code' => 'CODE1', 'rif' => 'J-1', 'fiscal_address' => 'Address 1', 'region_id' => $region1->id, 'db_name' => 'db1']);
        $client2 = CompanyRoute::create(['name' => 'C2', 'code' => 'CODE2', 'rif' => 'J-2', 'fiscal_address' => 'Address 2', 'region_id' => $region2->id, 'db_name' => 'db2']);

        $results = $this->tenantService->resolveClients(null, [$region1->id]);

        $this->assertCount(1, $results);
        $this->assertEquals($client1->id, $results->first()->id);
        $this->assertNotEquals($client2->id, $results->first()->id);
    }

    public function test_client_ids_precedence_over_region_ids()
    {
        $region1 = Region::create(['citCode' => 'R1', 'citName' => 'Region 1', 'staCode' => 'S1']);
        $region2 = Region::create(['citCode' => 'R2', 'citName' => 'Region 2', 'staCode' => 'S2']);

        $client1 = CompanyRoute::create(['name' => 'C1', 'code' => 'CODE1', 'rif' => 'J-1', 'fiscal_address' => 'Address 1', 'region_id' => $region1->id, 'db_name' => 'db1']);
        $client2 = CompanyRoute::create(['name' => 'C2', 'code' => 'CODE2', 'rif' => 'J-2', 'fiscal_address' => 'Address 2', 'region_id' => $region2->id, 'db_name' => 'db2']);

        // Request Client 2 (Region 2) but filter by Region 1
        // Expected: Should return Client 2 because client_ids has precedence
        $results = $this->tenantService->resolveClients([$client2->id], [$region1->id]);

        $this->assertCount(1, $results);
        $this->assertEquals($client2->id, $results->first()->id);
    }
}
