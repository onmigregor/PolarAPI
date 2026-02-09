<?php

namespace Modules\User\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\User\Models\Role;
use Modules\User\Models\User;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create roles
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'user']);
    }

    public function test_admin_can_list_users()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'admin')->first());

        User::factory()->count(10)->create();

        $response = $this->actingAs($admin)->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(11, 'data'); // 10 + admin
    }

    public function test_admin_can_search_users()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'admin')->first());

        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $response = $this->actingAs($admin)->getJson('/api/users?query=John');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'john@example.com');
    }

    public function test_admin_can_filter_users_by_role()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'admin')->first());

        User::factory()->count(5)->create()->each(function ($u) {
            $u->roles()->attach(Role::where('name', 'user')->first());
        });

        $response = $this->actingAs($admin)->getJson('/api/users?role=user');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    public function test_admin_can_list_all_users_without_pagination()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'admin')->first());

        User::factory()->count(20)->create(); // Create more than default perPage (15)

        $response = $this->actingAs($admin)->getJson('/api/users/all');

        $response->assertStatus(200)
            ->assertJsonCount(21, 'data') // 20 + admin
            ->assertJsonMissing(['meta', 'links']); // Ensure no pagination
    }

    public function test_admin_can_create_user_with_role()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'admin')->first());

        $roleUser = Role::where('name', 'user')->first();

        $data = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => [$roleUser->id]
        ];

        $response = $this->actingAs($admin)->postJson('/api/users', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'newuser@example.com')
            ->assertJsonPath('data.roles.0.name', 'user');

        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_admin_can_update_user()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'admin')->first());

        $user = User::factory()->create();
        $roleAdmin = Role::where('name', 'admin')->first();

        $data = [
            'name' => 'Updated Name',
            'email' => $user->email, // Same email
            'roles' => [$roleAdmin->id] // Promote to admin
        ];

        $response = $this->actingAs($admin)->putJson("/api/users/{$user->id}", $data);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.roles.0.name', 'admin');
    }

    public function test_admin_can_delete_user()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'admin')->first());

        $user = User::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/users/{$user->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_non_admin_cannot_access_users()
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'user')->first());

        $response = $this->actingAs($user)->getJson('/api/users');

        $response->assertStatus(403);
    }
}
