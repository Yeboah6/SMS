<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Staff;
use App\Models\User;
use App\Services\StaffDirectoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('staff.view'), 403);

        return Inertia::render('Staff/Index', [
            'staff' => Staff::query()
                ->with(['department:id,name', 'designation:id,name'])
                ->when($request->filled('search'), function ($query) use ($request): void {
                    $search = $request->string('search')->trim()->toString();
                    $query->where(fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('staff_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
                })
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Staff $member): array => [
                    'id' => $member->id,
                    'staffNumber' => $member->staff_number,
                    'name' => $member->name,
                    'email' => $member->email,
                    'phone' => $member->phone,
                    'isTeacher' => $member->is_teacher,
                    'status' => $member->status,
                    'department' => $member->department?->name,
                    'designation' => $member->designation?->name,
                ]),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'designations' => Designation::query()->orderBy('name')->get(['id', 'name']),
            'teacherUsers' => User::query()
                ->role('Teacher')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'canCreate' => $request->user()->can('staff.create'),
            'canManageDepartments' => $request->user()->can('departments.manage'),
            'canManageDesignations' => $request->user()->can('designations.manage'),
        ]);
    }

    public function store(
        StoreStaffRequest $request,
        StaffDirectoryService $service,
    ): RedirectResponse {
        $service->create($request->validated());

        return back()->with('status', 'Staff profile created.');
    }
}
