<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Guardian;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Services\UserAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Manages existing sign-in accounts: view, edit, reset a password, deactivate.
 * Accounts are created by the staff form, admissions enrolment and guardian invites, never here.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $viewer = $request->user();
        abort_unless($viewer->can('users.view'), 403);

        $search = $request->string('search')->trim()->toString();
        $role = $request->string('role')->trim()->toString();

        $users = User::query()
            ->with('roles:id,name')
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($role !== '', fn ($query) => $query->whereHas('roles', fn ($roles) => $roles->where('name', $role)))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $profiles = $this->profilesFor($users->getCollection()->pluck('id'));

        $users->through(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->all(),
            'isSuperAdmin' => $user->hasRole('Super Admin'),
            'profile' => $profiles[$user->id] ?? null,
            'canEdit' => $viewer->can('users.update') && $this->mayManage($viewer, $user),
            'canDeactivate' => $viewer->can('users.delete') && $this->mayManage($viewer, $user) && $user->id !== $viewer->id,
        ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $this->assignableRoles($viewer),
            'filters' => ['search' => $search, 'role' => $role],
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $viewer = $request->user();
        abort_unless($viewer->can('users.view'), 403);

        $user->load('roles:id,name');
        $profile = $this->profilesFor(collect([$user->id]))[$user->id] ?? null;

        return Inertia::render('Users/Show', [
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->all(),
                'isSuperAdmin' => $user->hasRole('Super Admin'),
                'createdAt' => $user->created_at?->toDateTimeString(),
                'emailVerifiedAt' => $user->email_verified_at?->toDateTimeString(),
                'isSelf' => $user->id === $viewer->id,
            ],
            'profile' => $profile,
            // What this account can do, grouped by area (for example fees, students, library).
            'permissions' => $user->getAllPermissions()
                ->pluck('name')
                ->sort()
                ->groupBy(fn (string $name): string => Str::before($name, '.'))
                ->map(fn (Collection $names): array => $names->values()->all())
                ->all(),
            'can' => [
                'edit' => $viewer->can('users.update') && $this->mayManage($viewer, $user),
                'deactivate' => $viewer->can('users.delete') && $this->mayManage($viewer, $user) && $user->id !== $viewer->id,
            ],
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        $viewer = $request->user();
        abort_unless($viewer->can('users.update'), 403);
        abort_unless($this->mayManage($viewer, $user), 403);

        $user->load('roles:id,name');
        $profile = $this->profilesFor(collect([$user->id]))[$user->id] ?? null;

        return Inertia::render('Users/Edit', [
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name ?? '',
            ],
            'roles' => $this->assignableRoles($viewer),
            'profile' => $profile,
            'locked' => $profile['locked'] ?? [],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UserAdministrationService $service): RedirectResponse
    {
        abort_unless($this->mayManage($request->user(), $user), 403);

        $data = $request->validated();
        $profile = $this->profilesFor(collect([$user->id]))[$user->id] ?? null;

        // Fields owned by a linked profile are changed on that profile, so the two never drift apart.
        $current = ['name' => $user->name, 'email' => $user->email, 'role' => $user->roles->first()?->name];
        foreach ($profile['locked'] ?? [] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== $current[$field]) {
                throw ValidationException::withMessages([
                    $field => "This account belongs to {$profile['label']}. Change its {$field} from that profile.",
                ]);
            }
        }

        $service->update($user, $data, $request->user());

        return to_route('users.show', $user)->with('status', 'User account updated.');
    }

    public function destroy(DeleteUserRequest $request, User $user, UserAdministrationService $service): RedirectResponse
    {
        abort_unless($this->mayManage($request->user(), $user), 403);

        $service->delete($user, $request->user());

        return to_route('users.index')->with('status', 'User account deactivated.');
    }

    // ------------------------------------------------------------------ helpers

    /** Only a Super Admin may change a Super Admin account. */
    private function mayManage(User $viewer, User $target): bool
    {
        return $viewer->hasRole('Super Admin') || ! $target->hasRole('Super Admin');
    }

    /** @return Collection<int, string> */
    private function assignableRoles(User $viewer): Collection
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->when(! $viewer->hasRole('Super Admin'), fn ($query) => $query->where('name', '!=', 'Super Admin'))
            ->orderBy('name')
            ->pluck('name');
    }

    /**
     * The staff, student or guardian profile each account belongs to, if any.
     *
     * @param  Collection<int, int>  $userIds
     * @return array<int, array{type: string, label: string, href: ?string, locked: list<string>}>
     */
    private function profilesFor(Collection $userIds): array
    {
        $profiles = [];

        Guardian::query()->whereIn('user_id', $userIds)->get(['id', 'user_id'])->each(function ($guardian) use (&$profiles): void {
            $profiles[$guardian->user_id] = ['type' => 'guardian', 'label' => 'a guardian profile', 'href' => null, 'locked' => ['role']];
        });
        Student::query()->whereIn('user_id', $userIds)->get(['id', 'user_id', 'admission_number'])->each(function ($student) use (&$profiles): void {
            $profiles[$student->user_id] = ['type' => 'student', 'label' => "student {$student->admission_number}", 'href' => "/students/{$student->id}", 'locked' => ['role']];
        });
        Staff::query()->whereIn('user_id', $userIds)->get(['id', 'user_id', 'staff_number'])->each(function ($staff) use (&$profiles): void {
            $profiles[$staff->user_id] = ['type' => 'staff', 'label' => "staff {$staff->staff_number}", 'href' => "/staff/{$staff->id}", 'locked' => ['name', 'email', 'role']];
        });

        return $profiles;
    }
}