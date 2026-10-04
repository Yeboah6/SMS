<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Permissions/Index', [
            'groups' => Permission::query()
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->groupBy(fn (Permission $permission): string => str($permission->name)->before('.')->toString())
                ->map(fn ($group): array => $group
                    ->map(fn (Permission $permission): array => [
                        'id' => $permission->id,
                        'name' => $permission->name,
                    ])
                    ->values()
                    ->all())
                ->all(),
        ]);
    }
}
