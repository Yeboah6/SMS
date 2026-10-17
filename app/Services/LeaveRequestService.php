<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveRequestService
{
    /**
     * @param  array{leave_type: string, start_date: string, end_date: string, reason: string, student_id?: int}  $data
     */
    public function submit(array $data, User $actor): LeaveRequest
    {
        return DB::transaction(function () use ($data, $actor): LeaveRequest {
            $staff = Staff::query()
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            $student = null;
            if (! $staff || $staff->status !== 'active' || ! $actor->can('leave.apply')) {
                if (! $actor->can('portal.leave.apply')) {
                    throw new AuthorizationException('An active staff or student profile is required to submit a leave request.');
                }

                if ($actor->hasRole('Student')) {
                    $student = Student::query()
                        ->where('user_id', $actor->id)
                        ->where('status', 'active')
                        ->lockForUpdate()
                        ->first();
                } elseif ($actor->hasRole('Parent')) {
                    $student = Student::query()
                        ->whereKey($data['student_id'])
                        ->where('status', 'active')
                        ->forGuardianAccount($actor)
                        ->lockForUpdate()
                        ->first();
                }

                if (! $student) {
                    throw ValidationException::withMessages([
                        'student_id' => 'Select an active student linked to your account.',
                    ]);
                }
            }

            $startDate = Carbon::parse($data['start_date'])->toDateString();
            $endDate = Carbon::parse($data['end_date'])->toDateString();

            $overlappingLeave = LeaveRequest::query()
                ->whereIn('status', LeaveRequest::ACTIVE_STATUSES)
                ->whereDate('start_date', '<=', $endDate)
                ->whereDate('end_date', '>=', $startDate);
            if ($staff && $staff->status === 'active' && $actor->can('leave.apply')) {
                $hasOverlappingLeave = (clone $overlappingLeave)
                    ->where('staff_id', $staff->id)
                    ->lockForUpdate()
                    ->exists();
            } else {
                $hasOverlappingLeave = (clone $overlappingLeave)
                    ->where('student_id', $student->id)
                    ->lockForUpdate()
                    ->exists();
            }

            if ($hasOverlappingLeave) {
                throw ValidationException::withMessages([
                    'start_date' => 'This period overlaps another pending or approved leave request.',
                ]);
            }

            return LeaveRequest::query()->create([
                ...$data,
                'staff_id' => $staff && $staff->status === 'active' && $actor->can('leave.apply')
                    ? $staff->id
                    : null,
                'student_id' => $student?->id,
                'submitted_by' => $actor->id,
                'status' => 'pending',
            ]);
        });
    }

    /**
     * @param  array{outcome: string, review_comments?: ?string}  $data
     */
    public function review(LeaveRequest $leaveRequest, array $data, User $actor): LeaveRequest
    {
        if (! $actor->can('leave.approve')) {
            throw new AuthorizationException('You are not allowed to review leave requests.');
        }

        return DB::transaction(function () use ($leaveRequest, $data, $actor): LeaveRequest {
            $leaveRequest = LeaveRequest::query()->lockForUpdate()->findOrFail($leaveRequest->id);

            if ($leaveRequest->submitted_by === $actor->id) {
                throw new AuthorizationException('You cannot review your own leave request.');
            }

            if ($leaveRequest->status !== 'pending') {
                throw ValidationException::withMessages([
                    'outcome' => 'Only pending leave requests can be reviewed.',
                ]);
            }

            $leaveRequest->update([
                'status' => $data['outcome'],
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_comments' => $data['review_comments'] ?? null,
            ]);

            return $leaveRequest->refresh();
        });
    }

    public function cancel(LeaveRequest $leaveRequest, User $actor): void
    {
        DB::transaction(function () use ($leaveRequest, $actor): void {
            $leaveRequest = LeaveRequest::query()->lockForUpdate()->findOrFail($leaveRequest->id);

            abort_unless($leaveRequest->submitted_by === $actor->id, 404);

            if ($leaveRequest->status !== 'pending') {
                throw ValidationException::withMessages([
                    'leave_request' => 'Only pending leave requests can be cancelled.',
                ]);
            }

            $leaveRequest->update(['status' => 'cancelled']);
        });
    }
}
