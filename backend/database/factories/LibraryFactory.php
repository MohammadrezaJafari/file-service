<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LibraryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
