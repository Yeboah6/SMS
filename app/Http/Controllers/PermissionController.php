<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePermissionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Permissions/Index', [
            'groups' => Permission::query()
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->withCount([
                    'roles as assigned_role_count' => fn ($query) => $query->where('name', '!=', 'Super Admin'),
                ])
                ->get(['id', 'name', 'is_system'])
                ->groupBy(fn (Permission $permission): string => str($permission->name)->before('.')->toString())
                ->map(fn ($group): array => $group
                    ->map(fn (Permission $permission): array => [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'roleCount' => $permission->assigned_role_count,
                        'isSystem' => (bool) $permission->is_system,
                    ])
                    ->values()
                    ->all())
                ->all(),
        ]);
    }

    public function store(StorePermissionRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $permission = Permission::query()->create([
                'name' => $request->validated('name'),
                'guard_name' => 'web',
            ]);

            Role::findByName('Super Admin', 'web')->givePermissionTo($permission);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('status', 'Custom permission created.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        abort_unless($permission->guard_name === 'web', 404);

        DB::transaction(function () use ($permission): void {
            $permission = Permission::query()->lockForUpdate()->findOrFail($permission->id);

            abort_if($permission->is_system, 403);

            $isAssignedToNonAdminRole = $permission->roles()
                ->where('name', '!=', 'Super Admin')
                ->exists();

            if ($isAssignedToNonAdminRole) {
                throw ValidationException::withMessages([
                    'permission' => 'Remove this permission from every non-admin role before deleting it.',
                ]);
            }

            $permission->delete();
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('status', 'Custom permission deleted.');
    }
}
