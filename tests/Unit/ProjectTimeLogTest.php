<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\ProjectTimeLog;
use App\Models\User;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTimeLogTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $project;
    protected $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);
    }

    public function test_paginated_time_logs()
    {
        ProjectTimeLog::factory()->count(15)->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/v1/project-timelogs');

        $response->assertStatus(200);
    }

    public function test_start_new_time_log()
    {
        $response = $this->postJson('/api/v1/project-timelogs/'. $this->project->id.'/start');

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Project time log started successfully.']);
    }

    public function test_start_fails_if_ongoing_log_exists()
    {
        ProjectTimeLog::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'end_time' => null,
        ]);

        $response = $this->postJson('/api/v1/project-timelogs/'. $this->project->id.'/start');

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'You have an ongoing time log for this project. Please end it before starting a new one.']);
    }

    public function test_stop_ongoing_time_log()
    {
        $log = ProjectTimeLog::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'end_time' => null,
        ]);

        $response = $this->postJson('/api/v1/project-timelogs/' . $this->project->id . '/stop');

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Project time log stopped successfully.']);

        $this->assertNotNull($log->fresh()->end_time);
    }

    public function test_creates_time_log()
    {
        $start = now()->subHour()->toDateTimeString();
        $end = now()->toDateTimeString();

        $response = $this->postJson('/api/v1/project-timelogs', [
            'project_id' => $this->project->id,
            'start_time' => $start,
            'end_time' => $end,
            'description' => 'test description',
            'tag' => 'billable',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Project time log created successfully.']);

        $this->assertDatabaseHas('project_time_logs', [
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'description' => 'test description',
            'tag' => 'billable',
        ]);
    }

    public function test_fails_with_overlapping_time_log()
    {
        $start = now()->subHour();
        $end = now();

        ProjectTimeLog::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'start_time' => $start->copy()->subMinutes(30),
            'end_time' => $end->copy()->addMinutes(30),
        ]);

        $response = $this->postJson('/api/v1/project-timelogs', [
            'project_id' => $this->project->id,
            'start_time' => $start->toDateTimeString(),
            'end_time' => $end->toDateTimeString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Time log overlaps with an existing entry.']);
    }

    public function test_get_time_log_by_id()
    {
        $log = ProjectTimeLog::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/v1/project-timelogs/' . $log->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $log->id]);
    }

    public function test_update_time_log()
    {
        $log = ProjectTimeLog::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'description' => 'Old desc',
            'tag' => 'billable',
        ]);

        $response = $this->putJson('/api/v1/project-timelogs/' . $log->id, [
            'description' => 'Updated desc',
            'tag' => 'non-billable',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Project time log updated successfully.']);

        $this->assertDatabaseHas('project_time_logs', [
            'id' => $log->id,
            'description' => 'Updated desc',
            'tag' => 'non-billable',
        ]);
    }

    public function test_deletes_time_log()
    {
        $log = ProjectTimeLog::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->deleteJson('/api/v1/project-timelogs/' . $log->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Project time log deleted successfully.']);

        $this->assertDatabaseMissing('project_time_logs', [
            'id' => $log->id,
        ]);
    }

    public function test_report_returns_report_data()
    {
        ProjectTimeLog::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'hours' => 2.5,
            'end_time' => now(),
        ]);

        $response = $this->getJson('/api/v1/report');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['by_date', 'by_project', 'by_client'], 'message', 'status']);
    }
}
