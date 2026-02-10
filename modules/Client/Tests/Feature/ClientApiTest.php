<?php

namespace Modules\Client\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Modules\Client\Models\Client;
use Modules\Region\Models\Region;
use Modules\User\Models\User;
use Tests\TestCase;

class ClientApiTest extends TestCase
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

    public function test_can_list_clients()
    {
        Client::create([
            'name' => 'Test Client',
            'code' => 'TC001',
            'rif' => 'J-12345678-9',
            'fiscal_address' => 'Test Address',
            'region_id' => $this->region->id,
            'db_name' => 'test_db'
        ]);

        $response = $this->actingAs($this->user)
                         ->getJson('/api/clients');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'meta']);
    }

    public function test_can_list_all_clients_for_selector()
    {
        Client::create([
            'name' => 'Selector Client',
            'code' => 'SEL001',
            'rif' => 'J-55555555-5',
            'fiscal_address' => 'Selector Address',
            'region_id' => $this->region->id,
            'db_name' => 'sel_db'
        ]);

        $response = $this->actingAs($this->user)
                         ->getJson('/api/clients/all');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data']);
    }

    public function test_can_create_client()
    {
        $data = [
            'name' => 'New Client',
            'code' => 'NC001',
            'rif' => 'J-98765432-1',
            'fiscal_address' => 'New Address',
            'region_id' => $this->region->id,
            'db_name' => 'new_client_db'
        ];

        $response = $this->actingAs($this->user)
                         ->postJson('/api/clients', $data);

        $response->assertStatus(201)
                 ->assertJsonPath('data.name', 'New Client');

        $this->assertDatabaseHas('clients', ['name' => 'New Client']);
    }

    public function test_can_update_client()
    {
        $client = Client::create([
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
                         ->putJson("/api/clients/{$client->id}", $data);

        // $response->dump();

        $response->assertStatus(200)
                 ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_can_delete_client()
    {
        $client = Client::create([
            'name' => 'To Delete',
            'code' => 'DEL001',
            'rif' => 'J-00000000-0',
            'fiscal_address' => 'Delete Address',
            'db_name' => 'delete_db',
            'region_id' => $this->region->id
        ]);

        $response = $this->actingAs($this->user)
                         ->deleteJson("/api/clients/{$client->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }
}
