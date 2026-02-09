<?php

namespace Modules\Region\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Region\Models\Region;
use Tests\TestCase;

class RegionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_all_regions_for_selector()
    {
        // Arrange
        Region::factory()->count(5)->create();

        // Act
        $response = $this->getJson('/api/regions/all');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'citCode',
                        'citName',
                        'staCode'
                    ]
                ]
            ])
            ->assertJsonCount(5, 'data');
    }

    public function test_can_list_paginated_regions()
    {
        // Arrange
        Region::factory()->count(15)->create();

        // Act: Request page 1 with 5 items per page
        $response = $this->getJson('/api/regions?per_page=5&page=1');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'links',
                'meta'
            ])
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_can_create_region()
    {
        // Arrange
        $payload = [
            'citCode' => 'TEST01',
            'citName' => 'Test Region',
            'staCode' => 'TS01'
        ];

        // Act
        $response = $this->postJson('/api/regions', $payload);

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.citCode', 'TEST01');

        $this->assertDatabaseHas('regions', $payload);
    }

    public function test_cannot_create_region_with_invalid_data()
    {
        // Arrange: Existing region to test duplicate code
        Region::factory()->create(['citCode' => 'DUP01']);

        $payload = [
            'citCode' => 'DUP01', // Duplicate
            'citName' => '',      // Empty
            'staCode' => ''       // Empty
        ];

        // Act
        $response = $this->postJson('/api/regions', $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['citCode', 'citName', 'staCode']);
    }

    public function test_can_update_region()
    {
        // Arrange
        $region = Region::factory()->create();
        $payload = [
            'citCode' => 'UPD01',
            'citName' => 'Updated Name',
            'staCode' => 'UP01'
        ];

        // Act
        $response = $this->putJson("/api/regions/{$region->id}", $payload);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.citName', 'Updated Name');

        $this->assertDatabaseHas('regions', $payload);
    }

    public function test_can_delete_region()
    {
        // Arrange
        $region = Region::factory()->create();

        // Act
        $response = $this->deleteJson("/api/regions/{$region->id}");

        // Assert
        $response->assertStatus(200);
        $this->assertSoftDeleted('regions', ['id' => $region->id]);
    }
}
