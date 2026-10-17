<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewLeaveRequest;
use App\Http\Requests\StoreLeaveRequest;
use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\Student;
use App\Services\LeaveRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canViewAll = $user->can('leave.view');
        $hasActiveStaffProfile = Staff::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
        $isStudent = $user->hasRole('Student') && $user->can('portal.leave.apply');
        $isParent = ! $isStudent && $user->hasRole('Parent') && $user->can('portal.leave.apply');
        $portalStudents = $isParent
            ? Student::query()
                ->where('status', 'active')
                ->forGuardianAccount($user)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'middle_name', 'last_name', 'admission_number'])
            : collect();
        $hasActiveStudentProfile = $isStudent && Student::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
        $canApply = ($user->can('leave.apply') && $hasActiveStaffProfile)
            || ($user->can('portal.leave.apply') && ($hasActiveStudentProfile || $portalStudents->isNotEmpty()));
        $canReview = $user->can('leave.approve');

        abort_unless($canViewAll || $canApply, 403);

        $leaveRequests = LeaveRequest::query()
            ->with([
                'staff:id,name,staff_number',
                'student:id,first_name,middle_name,last_name,admission_number',
                'submitter:id,name',
                'reviewer:id,name',
            ])
            ->when(! $canViewAll, fn ($query) => $query->where('submitted_by', $user->id))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (LeaveRequest $leaveRequest): array => [
                'id' => $leaveRequest->id,
                'staffName' => $leaveRequest->student
                    ? trim(implode(' ', array_filter([
                        $leaveRequest->student->first_name,
                        $leaveRequest->student->middle_name,
                        $leaveRequest->student->last_name,
                    ])))
                    : $leaveRequest->staff?->name,
                'staffNumber' => $leaveRequest->student?->admission_number ?? $leaveRequest->staff?->staff_number,
                'subjectType' => $leaveRequest->student ? 'student' : 'staff',
                'submittedBy' => $leaveRequest->submitter->name,
                'isOwn' => $leaveRequest->submitted_by === $user->id,
                'leaveType' => $leaveRequest->leave_type,
                'startDate' => $leaveRequest->start_date->toDateString(),
                'endDate' => $leaveRequest->end_date->toDateString(),
                'reason' => $leaveRequest->reason,
                'status' => $leaveRequest->status,
                'reviewer' => $leaveRequest->reviewer?->name,
                'reviewedAt' => $leaveRequest->reviewed_at?->toDateTimeString(),
                'reviewComments' => $leaveRequest->review_comments,
            ]);

        return Inertia::render('LeaveRequests/Index', [
            'leaveRequests' => $leaveRequests,
            'leaveTypes' => LeaveRequest::TYPES,
            'canApply' => $canApply,
            'canReview' => $canReview,
            'hasActiveStaffProfile' => $hasActiveStaffProfile,
            'isPortal' => $isStudent || $isParent,
            'requiresStudentSelection' => $isParent && ! ($user->can('leave.apply') && $hasActiveStaffProfile),
            'students' => $portalStudents->map(fn (Student $student): array => [
                'id' => $student->id,
                'name' => trim(implode(' ', array_filter([
                    $student->first_name,
                    $student->middle_name,
                    $student->last_name,
                ]))),
                'admissionNumber' => $student->admission_number,
            ])->values(),
        ]);
    }

    public function store(StoreLeaveRequest $request, LeaveRequestService $service): RedirectResponse
    {
        $service->submit($request->validated(), $request->user());

        return back()->with('status', 'Leave request submitted.');
    }

    public function review(
        ReviewLeaveRequest $request,
        LeaveRequest $leaveRequest,
        LeaveRequestService $service,
    ): RedirectResponse {
        $service->review($leaveRequest, $request->validated(), $request->user());

        return back()->with('status', 'Leave request reviewed.');
    }

    public function cancel(
        Request $request,
        LeaveRequest $leaveRequest,
        LeaveRequestService $service,
    ): RedirectResponse {
        $service->cancel($leaveRequest, $request->user());

        return back()->with('status', 'Leave request cancelled.');
    }
}
