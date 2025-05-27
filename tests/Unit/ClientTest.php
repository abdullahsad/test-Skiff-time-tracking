<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_get_clients_for_loggedin_user()
    {
        Client::factory()->count(15)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/clients');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'current_page',
                    'data',
                    'first_page_url',
                    'last_page',
                ],
                'status'
            ]);
    }

    public function test_creates_client()
    {
        $payload = [
            'name' => 'Test Client',
            'email' => 'client@gmail.com',
            'contact_person' => 'Test person',
        ];

        $response = $this->postJson('/api/v1/clients', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Client created successfully',
                'status' => 201,
            ]);

        $this->assertDatabaseHas('clients', [
            'user_id' => $this->user->id,
            'email' => 'client@gmail.com',
        ]);
    }

    public function test_failed_email_already_exists()
    {
        Client::factory()->create([
            'user_id' => $this->user->id,
            'email' => 'client@gmail.com',
        ]);

        $payload = [
            'name' => 'Test Client',
            'email' => 'client@gmail.com',
            'contact_person' => 'Test person',
        ];

        $response = $this->postJson('/api/v1/clients', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Client already exists for this user',
                'status' => 422,
            ]);
    }

    public function test_update_client()
    {
        $client = Client::factory()->create(['user_id' => $this->user->id]);

        $payload = [
            'name' => 'New name',
            'email' => 'newemail@gmail.com',
            'contact_person' => 'Update person',
        ];

        $response = $this->putJson('/api/v1/clients/' . $client->id, $payload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Client updated successfully',
                'status' => 200,
            ]);

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'New name',
            'email' => 'newemail@gmail.com',
            'contact_person' => 'Update person',
        ]);
    }

    public function test_deletes_client()
    {
        $client = Client::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson('/api/v1/clients/' . $client->id);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Client deleted successfully',
                'status' => 200,
            ]);

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    public function test_delete_fails_for_client_with_projects()
    {
        $client = Client::factory()->create(['user_id' => $this->user->id]);
        Project::factory()->create(['client_id' => $client->id]);

        $response = $this->deleteJson('/api/v1/clients/' . $client->id);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete client with projects. Please delete the projects first.',
                'status' => 422,
            ]);

        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }
}
