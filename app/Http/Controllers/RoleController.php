<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRolePermissionsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    private const PRIMARY_ROLES = [
        'Accountant',
        'Librarian',
        'Parent',
        'Principal',
        'Receptionist',
        'Student',
        'Super Admin',
        'Teacher',
    ];

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
                    'isPrimary' => in_array($role->name, self::PRIMARY_ROLES, true),
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

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $role = Role::query()->create([
                'name' => $request->validated('name'),
                'guard_name' => 'web',
            ]);
            $permissionIds = $request->validated('permission_ids', []);
            $permissions = Permission::query()
                ->where('guard_name', 'web')
                ->whereKey($permissionIds)
                ->get();

            $role->syncPermissions($permissions);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('status', 'Custom role created.');
    }

    public function update(
        UpdateRolePermissionsRequest $request,
        Role $role,
    ): RedirectResponse {
        abort_unless($role->guard_name === 'web', 404);

        DB::transaction(function () use ($request, $role): void {
            $permissions = Permission::query()
                ->where('guard_name', 'web')
                ->whereKey($request->validated('permission_ids'))
                ->get();

            $role->syncPermissions($permissions);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('status', 'Role permissions updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_unless($role->guard_name === 'web', 404);
        abort_if(in_array($role->name, self::PRIMARY_ROLES, true), 403);

        DB::transaction(function () use ($role): void {
            $role = Role::query()->lockForUpdate()->findOrFail($role->id);

            if ($role->users()->exists()) {
                throw ValidationException::withMessages([
                    'role' => 'Reassign this role’s users before deleting it.',
                ]);
            }

            $role->delete();
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('status', 'Custom role deleted.');
    }
}
