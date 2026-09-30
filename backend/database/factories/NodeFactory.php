<?php

namespace Database\Factories;

use App\Models\Library;
use App\Models\Node;
use Illuminate\Database\Eloquent\Factories\Factory;

class NodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'library_id' => Library::factory(),
            'parent_id' => null,
            'type' => Node::TYPE_FOLDER,
            'name' => fake()->unique()->word(),
        ];
    }

    public function folder(): static
    {
        return $this->state(['type' => Node::TYPE_FOLDER]);
    }
}
