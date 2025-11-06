<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Administrator',
            'Human Agent',
            'Supervisor',
            'Manager',
            'Support Agent',
            'Senior Agent',
        ]);

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'code' => strtoupper(\Illuminate\Support\Str::slug($name, '_')),
            'description' => fake()->sentence(),
        ];
    }
}
