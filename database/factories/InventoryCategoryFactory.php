<?php

namespace Database\Factories;

use App\Models\InventoryCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryCategory> */
class InventoryCategoryFactory extends Factory
{
    protected $model = InventoryCategory::class;

    public function definition(): array
    {
        return ['name' => fake()->unique()->words(2, true), 'description' => null, 'is_active' => true];
    }
}