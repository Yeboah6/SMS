<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRolePermissionsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(): Response
    {
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->groupBy(fn (Permission $permission): string => str($permission->name)->before('.')->toString());

        return Inertia::render('Roles/Index', [
            'roles' => Role::query()
                ->where('guard_name', 'web')
                ->with('permissions:id,name')
                ->withCount('users')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'userCount' => $role->users_count,
                    'permissionIds' => $role->permissions->pluck('id')->all(),
                ]),
            'permissionGroups' => $permissions->map(fn ($group): array => $group
                ->map(fn (Permission $permission): array => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                ])
                ->values()
                ->all())
                ->all(),
        ]);
    }

    public function update(
        UpdateRolePermissionsRequest $request,
        Role $role,
    ): RedirectResponse {
        abort_unless($role->guard_name === 'web', 404);

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereKey($request->validated('permission_ids'))
            ->get();

        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('status', 'Role permissions updated.');
    }
}
