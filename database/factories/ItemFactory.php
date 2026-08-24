<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Item>
 */
class ItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => fake()->unique()->words(2, true),
            'base_unit' => 'g',
            'order_unit' => 'kg',
            'conversion_factor' => 1000,
            'step_x100' => 50,
            'is_perishable' => false,
            'shelf_life_days' => null,
            'storage_location' => 'Dry store',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /** Sold and counted in whole pieces - never half an egg. */
    public function pieces(): static
    {
        return $this->state(fn () => [
            'base_unit' => 'piece',
            'order_unit' => 'piece',
            'conversion_factor' => 1,
            'step_x100' => 100,
        ]);
    }

    public function litres(): static
    {
        return $this->state(fn () => [
            'base_unit' => 'ml',
            'order_unit' => 'litre',
            'conversion_factor' => 1000,
            'step_x100' => 50,
        ]);
    }

    public function perishable(int $days = 3): static
    {
        return $this->state(fn () => [
            'is_perishable' => true,
            'shelf_life_days' => $days,
            'storage_location' => 'Cold room',
        ]);
    }
}
