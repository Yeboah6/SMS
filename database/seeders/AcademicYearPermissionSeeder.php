<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AcademicYearPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['view', 'create', 'update', 'delete', 'activate', 'complete', 'lock'] as $action) {
            Permission::findOrCreate("academic_years.{$action}", 'web');
        }
    }
}
