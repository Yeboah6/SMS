<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the supplies permissions and gives them to roles that exist. Adjust the grants to your school's policy.
 * Remember to remove the old library.* and portal.library.view permissions.
 */
class SuppliesPermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'supplies.dashboard.view',
        'supplies.items.view', 'supplies.items.create', 'supplies.items.update',
        'supplies.categories.manage',
        'supplies.suppliers.view', 'supplies.suppliers.manage',
        'supplies.purchases.view', 'supplies.purchases.create', 'supplies.purchases.reverse',
        'supplies.stock.view', 'supplies.stock.adjust', 'supplies.stock.override',
        'supplies.sales.view', 'supplies.sales.create', 'supplies.sales.discount', 'supplies.sales.refund', 'supplies.sales.cancel',
        'supplies.payments.create',
        'supplies.requirements.view', 'supplies.requirements.manage', 'supplies.requirements.fulfil_external',
        'supplies.reports.view', 'supplies.reports.export',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $grants = [
            'Super Admin' => self::PERMISSIONS,
            'Principal' => [
                'supplies.dashboard.view', 'supplies.items.view', 'supplies.suppliers.view', 'supplies.purchases.view',
                'supplies.stock.view', 'supplies.sales.view', 'supplies.requirements.view', 'supplies.reports.view', 'supplies.reports.export',
            ],
            // Sells and takes payments, can refund and see reports.
            'Accountant' => [
                'supplies.dashboard.view', 'supplies.items.view', 'supplies.stock.view', 'supplies.sales.view', 'supplies.sales.create',
                'supplies.sales.discount', 'supplies.sales.refund', 'supplies.sales.cancel', 'supplies.payments.create',
                'supplies.requirements.view', 'supplies.reports.view', 'supplies.reports.export',
            ],
            // Keeps the shop: stock, purchases, suppliers and day-to-day sales (no discounts or refunds).
            'Librarian' => [
                'supplies.dashboard.view', 'supplies.items.view', 'supplies.items.create', 'supplies.items.update',
                'supplies.categories.manage', 'supplies.suppliers.view', 'supplies.suppliers.manage',
                'supplies.purchases.view', 'supplies.purchases.create', 'supplies.stock.view', 'supplies.stock.adjust',
                'supplies.sales.view', 'supplies.sales.create', 'supplies.payments.create',
                'supplies.requirements.view', 'supplies.requirements.manage', 'supplies.requirements.fulfil_external',
            ],
        ];

        foreach ($grants as $roleName => $permissions) {
            Role::query()->where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}