<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectTimeLog;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Client::factory(5)->create(['user_id' => $user->id])
            ->each(function ($client) use ($user) {
                Project::factory(rand(2, 5))->create(['client_id' => $client->id])
                    ->each(function ($project) use ($user) {
                        ProjectTimeLog::factory(rand(5, 15))->create([
                            'project_id' => $project->id,
                            'user_id' => $user->id
                        ]);
                    });
            });
    }
}
