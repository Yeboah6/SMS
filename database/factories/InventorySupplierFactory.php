<?php

namespace Database\Factories;

use App\Models\InventorySupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventorySupplier> */
class InventorySupplierFactory extends Factory
{
    protected $model = InventorySupplier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'contact_person' => fake()->name(),
            'phone' => fake()->numerify('024#######'),
            'email' => fake()->safeEmail(),
            'is_active' => true,
        ];
    }
}