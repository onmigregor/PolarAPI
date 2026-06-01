<?php

namespace Modules\Analytics\Tests\Feature;

use Tests\TestCase;
use Modules\User\Models\User;
use Modules\Region\Models\Region;
use Modules\CompanyRoute\Models\CompanyRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup authenticated user if needed
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
    }

    public function test_sales_trend_endpoint_validation()
    {
        $region = Region::create(['citCode' => 'R1', 'citName' => 'Region 1', 'staCode' => 'S1']);
        $client = CompanyRoute::create([
            'name' => 'Client 1',
            'code' => 'C1',
            'rif' => 'J123',
            'fiscal_address' => 'Addr',
            'region_id' => $region->id,
            'db_name' => 'tenant_db'
        ]);

        $response = $this->postJson('/api/analytics/reports/daily-sales-trend', [
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31',
            'client_ids' => [$client->id]
        ]);

        // If this passes, the 500 error is gone and validation is working
        $response->assertStatus(200);
    }

    public function test_invalid_client_id_returns_validation_error()
    {
        $response = $this->postJson('/api/analytics/reports/daily-sales-trend', [
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31',
            'client_ids' => [99999] // Non-existent ID
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['client_ids.0']);
    }

    public function test_get_filters_returns_hierarchical_data()
    {
        \Modules\MasterProduct\Models\MasterProductFamily::create(['cl1_code' => 'F1', 'cl1_name' => 'Familia 1']);
        \Modules\MasterProduct\Models\MasterProductCategory::create(['cl2_code' => 'C1', 'cl1_code' => 'F1', 'cl2_name' => 'Categoria 1']);
        
        \Modules\MasterProduct\Models\MasterProduct::insert([
            'sku' => 'SKU1', 
            'name' => 'P1', 
            'cl1_code' => 'F1', 
            'cl2_code' => 'C1', 
            'brand_code' => 'B1', 
            'segment_code' => 'S1', 
            'is_active' => true,
            'category' => 'C1',
            'brand' => 'B1',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $response = $this->getJson('/api/analytics/filters');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'clients', 'regions', 'products', 'families', 'categories', 'brands', 'segments'
            ]
        ]);
        
        $data = $response->json('data');
        $this->assertCount(1, $data['families']);
        $this->assertEquals('F1', $data['families'][0]['id']);
        
        $this->assertCount(1, $data['brands']);
        $this->assertEquals('B1', $data['brands'][0]['id']);
        
        $this->assertCount(1, $data['segments']);
        $this->assertEquals('S1', $data['segments'][0]['id']);
    }
}
