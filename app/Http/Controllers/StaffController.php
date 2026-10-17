<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Staff;
use App\Services\StaffDirectoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    private const WITH_ACCOUNT = ['department:id,name', 'designation:id,name', 'user:id,name,email', 'user.roles:id,name'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('staff.view'), 403);

        return Inertia::render('Staff/Index', [
            'staff' => Staff::query()
                ->with(self::WITH_ACCOUNT)
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
                    'email' => $member->user?->email ?? $member->email,
                    'phone' => $member->phone,
                    'role' => StaffDirectoryService::roleOf($member->user),
                    'isTeacher' => $member->is_teacher,
                    'status' => $member->status,
                    'department' => $member->department?->name,
                    'designation' => $member->designation?->name,
                ]),
            'filters' => ['search' => $request->string('search')->toString()],
            'can' => [
                'create' => $user->can('staff.create'),
                'update' => $user->can('staff.update'),
                'delete' => $user->can('staff.delete'),
                'manageDepartments' => $user->can('departments.manage'),
                'manageDesignations' => $user->can('designations.manage'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('staff.create'), 403);

        return Inertia::render('Staff/Create', [
            ...$this->formOptions(),
            'canManageUsers' => $request->user()->can('users.view'),
        ]);
    }

    public function store(StoreStaffRequest $request, StaffDirectoryService $service): RedirectResponse
    {
        $staff = $service->create($request->validated());

        return to_route('staff.show', $staff)->with('status', "Staff profile {$staff->staff_number} created.");
    }

    public function show(Request $request, Staff $staff): Response
    {
        $user = $request->user();
        abort_unless($user->can('staff.view'), 403);
        $staff->load(self::WITH_ACCOUNT);

        return Inertia::render('Staff/Show', [
            'member' => $this->staffDetails($staff),
            'can' => [
                'update' => $user->can('staff.update'),
                'delete' => $user->can('staff.delete'),
                'manageUsers' => $user->can('users.view'),
            ],
        ]);
    }

    public function edit(Request $request, Staff $staff): Response
    {
        abort_unless($request->user()->can('staff.update'), 403);
        $staff->load(self::WITH_ACCOUNT);

        return Inertia::render('Staff/Edit', [
            ...$this->formOptions(),
            'member' => $this->staffDetails($staff),
            'canManageUsers' => $request->user()->can('users.view'),
        ]);
    }

    public function update(UpdateStaffRequest $request, Staff $staff, StaffDirectoryService $service): RedirectResponse
    {
        $service->update($staff, $request->validated());

        return to_route('staff.show', $staff)->with('status', 'Staff profile updated.');
    }

    public function destroy(Request $request, Staff $staff, StaffDirectoryService $service): RedirectResponse
    {
        abort_unless($request->user()->can('staff.delete'), 403);
        $service->delete($staff);

        return to_route('staff.index')->with('status', 'Staff profile deleted.');
    }

    /** @return array{roles: array<int, string>, departments: mixed, designations: mixed} */
    private function formOptions(): array
    {
        return [
            'roles' => StaffDirectoryService::LINKABLE_ROLES,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'designations' => Designation::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    /** @return array<string, mixed> */
    private function staffDetails(Staff $staff): array
    {
        return [
            'id' => $staff->id,
            'staffNumber' => $staff->staff_number,
            'name' => $staff->name,
            'email' => $staff->user?->email ?? $staff->email,
            'phone' => $staff->phone,
            'joiningDate' => $staff->joining_date?->toDateString(),
            'qualifications' => $staff->qualifications,
            'role' => StaffDirectoryService::roleOf($staff->user),
            'hasAccount' => $staff->user_id !== null,
            'departmentId' => $staff->department_id,
            'designationId' => $staff->designation_id,
            'isTeacher' => $staff->is_teacher,
            'status' => $staff->status,
            'department' => $staff->department?->name,
            'designation' => $staff->designation?->name,
        ];
    }
}