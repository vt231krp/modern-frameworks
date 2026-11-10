<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class AuthAndRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_register_and_receives_jwt(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Новий Клієнт',
            'email' => 'client@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '+380501112233',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('token_type', 'bearer')
            ->assertJsonPath('user.role', Role::Client->value)
            ->assertJsonStructure(['access_token', 'expires_in', 'user' => ['id', 'member']]);

        $this->assertNotEmpty($response->json('access_token'));
        $this->assertDatabaseHas('members', ['user_id' => $response->json('user.id')]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::create([
            'name' => 'Client',
            'email' => 'client@example.test',
            'password' => 'password123',
            'role' => Role::Client,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'client@example.test',
            'password' => 'wrong-password',
        ])->assertStatus(401);

        $this->postJson('/api/auth/login', [
            'email' => 'client@example.test',
            'password' => 'password123',
        ])->assertStatus(200)
            ->assertJsonStructure(['access_token', 'user' => ['id', 'email']]);
    }

    public function test_me_requires_valid_token(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);

        $user = $this->makeUser(Role::Client, 'me@example.test');

        $this->withHeader('Authorization', 'Bearer '.auth('api')->login($user))
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('email', 'me@example.test');
    }

    public function test_client_cannot_create_staff_entities(): void
    {
        $client = $this->makeUser(Role::Client, 'client@example.test');

        $this->withHeader('Authorization', 'Bearer '.auth('api')->login($client))
            ->postJson('/api/coaches', ['name' => 'Недоступний тренер'])
            ->assertStatus(403);
    }

    public function test_manager_can_create_but_not_delete(): void
    {
        $manager = $this->makeUser(Role::Manager, 'manager@example.test');

        $this->withHeader('Authorization', 'Bearer '.auth('api')->login($manager));

        $response = $this->postJson('/api/coaches', ['name' => 'Тренер Менеджера']);
        $response->assertStatus(201);

        $coachId = $response->json('coach_id');

        $this->deleteJson("/api/coaches/{$coachId}")->assertStatus(403);
    }

    public function test_only_admin_can_manage_users(): void
    {
        $manager = $this->makeUser(Role::Manager, 'manager@example.test');
        $admin = $this->makeUser(Role::Admin, 'admin@example.test');

        $this->withHeader('Authorization', 'Bearer '.auth('api')->login($manager))
            ->getJson('/api/users')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer '.auth('api')->login($admin))
            ->getJson('/api/users')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);
    }

    public function test_refresh_and_logout(): void
    {
        $user = $this->makeUser(Role::Client, 'refresh@example.test');
        $token = auth('api')->login($user);

        $refreshed = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/refresh')
            ->assertStatus(200)
            ->json('access_token');

        $this->assertNotSame($token, $refreshed);

        $this->withHeader('Authorization', 'Bearer '.$refreshed)
            ->postJson('/api/auth/logout')
            ->assertStatus(200);
    }

    private function makeUser(Role $role, string $email): User
    {
        $user = User::create([
            'name' => ucfirst($role->value),
            'email' => $email,
            'password' => 'password123',
        ]);
        $user->role = $role;
        $user->save();

        return $user;
    }
}
