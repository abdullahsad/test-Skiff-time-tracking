<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectTimeLog;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $this->actingAs($this->user);
    }

    public function test_return_projects()
    {
        Project::factory()->count(15)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/v1/projects');

        $response->assertStatus(200);
    }

    public function test_creates_project()
    {
        $payload = [
            'title' => 'Test Project',
            'description' => 'Test Description',
            'status' => 'active',
            'deadline' => now()->addWeek()->toDateString(),
            'client_id' => $this->client->id,
        ];

        $response = $this->postJson('/api/v1/projects', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Project created successfully',
                'status' => 201,
            ]);

        $this->assertDatabaseHas('projects', [
            'title' => 'Test Project',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_show_project()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/v1/projects/' . $project->id);

        $response->assertStatus(200);
    }

    public function test_update_project()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'title' => 'Old Title',
        ]);

        $payload = [
            'title' => 'New Title',
            'status' => 'completed',
        ];

        $response = $this->putJson('/api/v1/projects/' . $project->id, $payload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Project updated successfully',
                'status' => 200,
            ]);
    }

    public function test_delete_project()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $timeLog = ProjectTimeLog::factory()->create([
            'project_id' => $project->id,
        ]);

        $response = $this->deleteJson('/api/v1/projects/' . $project->id);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Project deleted successfully',
                'status' => 200,
            ]);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('project_time_logs', ['id' => $timeLog->id]);
    }
}
