<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_user_successfully()
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'TestUser@Example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/register', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'User created successfully',
                'status' => 201,
            ]);

        $this->assertDatabaseHas('users', [
            'email' => strtolower($payload['email']),
            'name' => $payload['name'],
        ]);
    }

    public function test_failed_registration_with_invalid_data()
    {
        $payload = [
            'name' => 'invalid email',
            'password' => '111',
        ];

        $response = $this->postJson('/api/v1/register', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'status']);
    }

    public function test_failed_registration_with_existing_email()
    {
        User::factory()->create(['email' => 'test@gmail.com']);

        $payload = [
            'name' => 'test user',
            'email' => 'test@gmail.com',
            'password' => 'password',
        ];

        $response = $this->postJson('/api/v1/register', $payload);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'email' => ['The email you provided is already in use!'],
            ]);
    }

    public function test_login_successfully()
    {
        $password = 'password';
        User::factory()->create([
            'email' => 'testlogin@gmail.com',
            'password' => bcrypt($password),
        ]);

        $payload = [
            'email' => 'testlogin@gmail.com',
            'password' => $password,
        ];

        $response = $this->postJson('/api/v1/login', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Login successful',
                'status' => 200,
            ])
            ->assertJsonStructure([
                'data' => ['user', 'token'],
            ]);
    }

    public function test_failed_login_with_invalid_credential()
    {
        User::factory()->create([
            'email' => 'fail@gmail.com',
            'password' => bcrypt('password'),
        ]);

        $payload = [
            'email' => 'fail@gmail.com',
            'password' => '111111111',
        ];

        $response = $this->postJson('/api/v1/login', $payload);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials',
                'status' => 401,
            ]);
    }

    public function test_failed_login_with_nonexistent_user()
    {
        $payload = [
            'email' => 'notest@gmail.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/login', $payload);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'User not found',
                'status' => 404,
            ]);
    }

    public function test_failed_login_with_invalid_data()
    {
        $payload = [
            'email' => 'email',
            'password' => '111',
        ];

        $response = $this->postJson('/api/v1/login', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'status',
            ]);
    }

    public function test_logout_user()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'User logged out successfully',
                'status' => 200,
            ]);
    }

    public function test_error_401_loggout_without_authentication()
    {
        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.'
            ]);
    }
}

