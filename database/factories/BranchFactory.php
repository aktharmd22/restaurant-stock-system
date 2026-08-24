<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Branch>
 */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->streetName().' Branch',
            'code' => Str::upper(Str::random(4)),
            'type' => 'sub',
            'address' => fake()->address(),
            'phone' => fake()->numerify('90########'),
            'cutoff_time' => '18:00:00',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ];
    }

    public function main(): static
    {
        return $this->state(fn () => [
            'name' => 'Main Store',
            'code' => 'MAIN',
            'type' => 'main',
        ]);
    }
}
