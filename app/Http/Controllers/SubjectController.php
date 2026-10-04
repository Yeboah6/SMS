<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubjectRequest;
use App\Models\Staff;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('subjects.view'), 403);

        $user = $request->user();
        $isScopedTeacher = $user->hasRole('Teacher') && ! $user->hasRole('Super Admin');
        $teacherStaffId = $isScopedTeacher
            ? Staff::query()->where('user_id', $user->id)->value('id')
            : null;

        return Inertia::render('Subjects/Index', [
            'subjects' => Subject::query()
                ->when($isScopedTeacher, fn ($query) => $query->whereHas('teacherSubjectAssignments', fn ($query) => $query
                    ->where('staff_id', $teacherStaffId ?? 0)))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'canCreate' => $request->user()->can('subjects.create'),
        ]);
    }

    public function store(StoreSubjectRequest $request): RedirectResponse
    {
        Subject::query()->create($request->validated());

        return back()->with('status', 'Subject created.');
    }
}
