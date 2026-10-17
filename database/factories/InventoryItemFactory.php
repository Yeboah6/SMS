<?php

namespace Database\Factories;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryItem> */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'category_id' => InventoryCategory::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####')),
            'name' => fake()->unique()->words(3, true),
            'unit' => 'piece',
            'cost_price' => 5,
            'selling_price' => 8,
            'reorder_level' => 5,
            'is_active' => true,
        ];
    }

    /** Stock is set directly because quantity_on_hand is not mass-assignable. */
    public function stocked(int $quantity): static
    {
        return $this->afterCreating(function (InventoryItem $item) use ($quantity): void {
            $item->quantity_on_hand = $quantity;
            $item->save();
        });
    }
}