<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use App\Models\Client;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectTimeLogFactory extends Factory
{
    public function definition()
    {
        $start = fake()->dateTimeThisMonth();
        $end = (clone $start)->modify('+' . rand(1, 8) . ' hours');
        
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'client_id' => Client::factory(),
            'start_time' => $start,
            'end_time' => $end,
            'description' => fake()->sentence(),
            'tag' => 'billable',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}