<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'email' => fake()->unique()->companyEmail(),
            'contact_person' => fake()->name(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}