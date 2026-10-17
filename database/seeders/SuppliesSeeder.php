<?php

namespace Database\Seeders;

use App\Models\InventoryCategory;
use Illuminate\Database\Seeder;

class SuppliesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Exercise books', 'Ruled and plain exercise books.'],
            ['Notebooks', 'Hard and soft cover notebooks.'],
            ['Textbooks', 'Prescribed and recommended textbooks.'],
            ['Stationery', 'Pens, pencils, geometry sets and similar.'],
            ['Other supplies', 'Anything else sold to students.'],
        ] as [$name, $description]) {
            InventoryCategory::query()->firstOrCreate(['name' => $name], ['description' => $description, 'is_active' => true]);
        }

        $this->call(SuppliesPermissionSeeder::class);
    }
}