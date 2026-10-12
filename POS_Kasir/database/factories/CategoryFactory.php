<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'owner_type' => 'makanan',
            'icon' => 'utensils',
        ];
    }

    public function makanan(): static
    {
        return $this->state(fn () => ['owner_type' => 'makanan']);
    }

    public function minuman(): static
    {
        return $this->state(fn () => ['owner_type' => 'minuman']);
    }
}
