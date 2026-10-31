<?php

namespace Tests\Feature;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\InventoryItem;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\SuppliesPermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Minimal fixtures. If your students, academic_years or users tables need more columns, add them here once and
 * every supplies test picks them up.
 */
trait CreatesSupplyFixtures
{
    protected function setUpSupplies(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (SuppliesPermissionSeeder::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        config([
            'supplies.allow_partial_payment' => true,
            'supplies.allow_stock_override' => false,
            'supplies.allow_external_fulfilment' => true,
        ]);
    }

    /** A user holding every supplies permission, or only the ones listed. */
    protected function actor(?array $permissions = null): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions ?? SuppliesPermissionSeeder::PERMISSIONS);

        return $user;
    }

    protected function activeYear(): AcademicYear
    {
        return AcademicYear::query()->create([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'status' => AcademicYearStatus::Active,
        ]);
    }

    protected function student(array $overrides = []): Student
    {
        static $counter = 0;
        $counter++;

        return Student::query()->create([
            'admission_number' => 'TST'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'first_name' => 'Ama',
            'last_name' => 'Mensah'.$counter,
            'date_of_birth' => '2015-04-12',
            'gender' => 'female',
            'admission_date' => '2026-09-01',
            'status' => 'active',
            ...$overrides,
        ]);
    }

    protected function item(int $stock, float $price = 10.00, float $cost = 6.00, array $overrides = []): InventoryItem
    {
        return InventoryItem::factory()->stocked($stock)->create(['selling_price' => $price, 'cost_price' => $cost, ...$overrides]);
    }
}
