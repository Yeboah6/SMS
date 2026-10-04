<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\UserAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('roles:id,name')
            ->when($request->string('search')->trim()->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->trim()->toString();
                $query->where(fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->all(),
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name'),
            'filters' => ['search' => $request->string('search')->toString()],
        ]);
    }

    public function store(StoreUserRequest $request, UserAdministrationService $service): RedirectResponse
    {
        $service->create($request->validated());

        return back()->with('status', 'User account created.');
    }

    public function update(
        UpdateUserRequest $request,
        User $user,
        UserAdministrationService $service,
    ): RedirectResponse {
        $service->update($user, $request->validated(), $request->user());

        return back()->with('status', 'User account updated.');
    }
}
