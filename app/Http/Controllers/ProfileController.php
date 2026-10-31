<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Support\LinkedProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The signed-in person's own account. Everyone has one, so it needs no permission. */
class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $profile = LinkedProfile::for($user);

        return Inertia::render('Profile/Show', [
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles()->orderBy('name')->pluck('name'),
                'createdAt' => $user->created_at?->toDateString(),
                'emailVerifiedAt' => $user->email_verified_at?->toDateTimeString(),
            ],
            'profile' => $profile,
            'locked' => $profile['locked'] ?? [],
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->safe()->only(['name', 'email']));

        return back()->with('status', 'Your profile was updated.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        // The model's "hashed" cast hashes it.
        $request->user()->update(['password' => $request->validated('password')]);

        return back()->with('status', 'Your password was changed.');
    }
}