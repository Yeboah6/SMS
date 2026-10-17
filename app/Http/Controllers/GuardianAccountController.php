<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteGuardianAccountRequest;
use App\Http\Requests\LinkGuardianAccountRequest;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\GuardianAccountService;
use Illuminate\Http\RedirectResponse;

class GuardianAccountController extends Controller
{
    public function link(
        LinkGuardianAccountRequest $request,
        Student $student,
        Guardian $guardian,
        GuardianAccountService $service,
    ): RedirectResponse {
        $this->authorize('view', $student);
        abort_unless($student->guardians()->whereKey($guardian->id)->exists(), 404);

        $service->linkExisting($guardian, $request->integer('user_id'));

        return back()->with('status', 'Guardian portal account linked.');
    }

    public function unlink(
        InviteGuardianAccountRequest $request,
        Student $student,
        Guardian $guardian,
        GuardianAccountService $service,
    ): RedirectResponse {
        $this->authorize('view', $student);
        abort_unless($student->guardians()->whereKey($guardian->id)->exists(), 404);

        $service->unlink($guardian);

        return back()->with('status', 'Guardian portal account unlinked.');
    }

    public function invite(
        InviteGuardianAccountRequest $request,
        Student $student,
        Guardian $guardian,
        GuardianAccountService $service,
    ): RedirectResponse {
        $this->authorize('view', $student);
        abort_unless($student->guardians()->whereKey($guardian->id)->exists(), 404);

        $service->invite($guardian);

        return back()->with('status', 'Parent account created and password setup email sent.');
    }
}
