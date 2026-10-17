<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Admission;
use App\Models\FeePayment;
use App\Models\HomeworkSubmission;
use App\Models\Invoice;
use App\Models\LibraryLoan;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TeacherClassAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @return array{
     *     roleLabel: string,
     *     academicYear: ?string,
     *     metrics: list<array{label: string, value: int|float, detail: string, icon: string, href: string, format: string}>,
     *     actions: list<array{label: string, href: string}>
     * }
     */
    public function forUser(User $user): array
    {
        $academicYear = AcademicYear::active()->first();
        $metrics = [];
        $isStudent = $user->hasRole('Student');
        $isParent = $user->hasRole('Parent');
        $isTeacher = $user->hasRole('Teacher') && ! $user->hasRole('Super Admin');

        if ($isStudent || $isParent) {
            $students = $this->portalStudents($user, $isStudent);
            $studentIds = $students->modelKeys();
            $todayStart = today()->startOfDay()->toDateTimeString();
            $todayEnd = today()->endOfDay()->toDateTimeString();

            $metrics[] = $this->metric(
                $isStudent ? 'My profile' : 'Linked children',
                $students->count(),
                $isStudent ? 'Student profile linked to your account' : 'Student profiles linked to your account',
                'users',
                '/fees',
            );

            $dueHomework = $academicYear && $studentIds !== []
                ? DB::table('enrolments')
                    ->join('homework_assignments', function (JoinClause $join): void {
                        $join->on('homework_assignments.section_id', '=', 'enrolments.section_id')
                            ->on('homework_assignments.academic_year_id', '=', 'enrolments.academic_year_id');
                    })
                    ->leftJoin('homework_submissions', function (JoinClause $join): void {
                        $join->on('homework_submissions.homework_assignment_id', '=', 'homework_assignments.id')
                            ->on('homework_submissions.student_id', '=', 'enrolments.student_id');
                    })
                    ->where('enrolments.academic_year_id', $academicYear->id)
                    ->where('enrolments.status', 'active')
                    ->whereIn('enrolments.student_id', $studentIds)
                    ->where('homework_assignments.assigned_date', '<=', $todayEnd)
                    ->where('homework_assignments.due_date', '>=', $todayStart)
                    ->whereNull('homework_submissions.id')
                    ->count()
                : 0;

            $invoices = $academicYear && $studentIds !== []
                ? Invoice::query()
                    ->where('academic_year_id', $academicYear->id)
                    ->whereIn('student_id', $studentIds)
                    ->whereColumn('total_amount', '>', 'paid_amount')
                : Invoice::query()->whereRaw('1 = 0');

            $metrics[] = $this->metric(
                'Homework due',
                $dueHomework,
                'Assignments due this academic year',
                'clipboard',
                '/homework',
            );
            $metrics[] = $this->metric(
                'Outstanding invoices',
                (clone $invoices)->count(),
                'Unpaid or partially paid invoices',
                'receipt',
                '/fees',
            );
            $metrics[] = $this->metric(
                'Current balance',
                (float) ((clone $invoices)->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) AS balance')->first()?->balance ?? 0),
                'Remaining balance for this academic year',
                'receipt',
                '/fees',
                'amount',
            );
        }

        if ($user->can('students.view')) {
            $metrics[] = $this->metric(
                $isTeacher ? 'Students in assigned sections' : 'Active students',
                Student::query()->visibleTo($user)->where('status', 'active')->count(),
                $isTeacher ? 'Students in your assigned sections' : 'Currently enrolled student profiles',
                'graduation',
                '/students',
            );
        }

        if ($user->can('staff.view')) {
            $metrics[] = $this->metric(
                'Active staff',
                Staff::query()->where('status', 'active')->count(),
                'Staff profiles currently active',
                'users',
                '/staff',
            );
        }

        if ($user->can('admissions.view')) {
            $metrics[] = $this->metric(
                'Admissions to review',
                Admission::query()
                    ->whereIn('status', ['enquiry', 'application_received', 'under_review'])
                    ->count(),
                'Enquiries and applications awaiting a decision',
                'clipboard',
                '/admissions',
            );
        }

        if ($isTeacher && $academicYear) {
            $staffId = Staff::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->where('is_teacher', true)
                ->value('id');
            $sectionCount = $staffId
                ? TeacherClassAssignment::query()
                    ->where('academic_year_id', $academicYear->id)
                    ->where('staff_id', $staffId)
                    ->distinct('section_id')
                    ->count('section_id')
                : 0;
            $metrics[] = $this->metric(
                'Assigned sections',
                $sectionCount,
                'Sections assigned to you this academic year',
                'graduation',
                '/classes',
            );

            if ($user->can('homework.grade')) {
                $metrics[] = $this->metric(
                    'Submissions to grade',
                    HomeworkSubmission::query()
                        ->whereNull('graded_at')
                        ->whereHas('assignment', fn (Builder $assignments): Builder => $assignments
                            ->where('academic_year_id', $academicYear->id)
                            ->whereHas('section.teacherSubjectAssignments', fn (Builder $teacherAssignments): Builder => $teacherAssignments
                                ->where('academic_year_id', $academicYear->id)
                                ->where('staff_id', $staffId ?? 0)
                                ->whereColumn('teacher_subject_assignments.subject_id', 'homework_assignments.subject_id')))
                        ->count(),
                    'Submitted assignments awaiting your review',
                    'clipboard',
                    '/homework',
                );
            }
        }

        if ($user->can('invoices.view') || $user->can('fees.view')) {
            $unpaidInvoices = Invoice::query()
                ->when($academicYear, fn (Builder $invoices): Builder => $invoices
                    ->where('academic_year_id', $academicYear->id), fn (Builder $invoices): Builder => $invoices->whereRaw('1 = 0'))
                ->whereColumn('total_amount', '>', 'paid_amount');

            $metrics[] = $this->metric(
                'Outstanding invoices',
                (clone $unpaidInvoices)->count(),
                'Invoices with a remaining balance this academic year',
                'receipt',
                '/fees',
            );
            $metrics[] = $this->metric(
                'Current balance',
                (float) ((clone $unpaidInvoices)->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) AS balance')->first()?->balance ?? 0),
                'Remaining balance for this academic year',
                'receipt',
                '/fees',
                'amount',
            );
            $metrics[] = $this->metric(
                'Payments recorded today',
                FeePayment::query()
                    ->whereDate('payment_date', today()->toDateString())
                    ->whereHas('invoice', fn (Builder $invoices): Builder => $invoices
                        ->when($academicYear, fn (Builder $query): Builder => $query
                            ->where('academic_year_id', $academicYear->id), fn (Builder $query): Builder => $query->whereRaw('1 = 0')))
                    ->count(),
                'Receipts recorded today',
                'receipt',
                '/fees',
            );
        }

        if ($user->can('library.view')) {
            $issuedLoans = LibraryLoan::query()->where('status', 'issued');
            $metrics[] = $this->metric(
                'Books on loan',
                (clone $issuedLoans)->count(),
                'Copies currently issued to students',
                'book',
                '/library',
            );
            $metrics[] = $this->metric(
                'Overdue books',
                (clone $issuedLoans)->where('due_date', '<', today()->toDateString())->count(),
                'Issued copies past their due date',
                'book',
                '/library',
            );
        }

        return [
            'roleLabel' => $user->getRoleNames()->implode(', ') ?: 'School member',
            'academicYear' => $academicYear?->name,
            'metrics' => $metrics,
            'actions' => $this->actions($user),
        ];
    }

    /**
     * @return Collection<int, Student>
     */
    private function portalStudents(User $user, bool $isStudent): Collection
    {
        return Student::query()
            ->when(
                $isStudent,
                fn (Builder $students): Builder => $students->where('user_id', $user->id),
                fn (Builder $students): Builder => $students->forGuardianAccount($user),
            )
            ->get(['id']);
    }

    /**
     * @return array{label: string, value: int|float, detail: string, icon: string, href: string, format: string}
     */
    private function metric(
        string $label,
        int|float $value,
        string $detail,
        string $icon,
        string $href,
        string $format = 'number',
    ): array {
        return compact('label', 'value', 'detail', 'icon', 'href', 'format');
    }

    /**
     * @return list<array{label: string, href: string}>
     */
    private function actions(User $user): array
    {
        $availableActions = [
            ['label' => 'Students', 'href' => '/students', 'permission' => 'students.view'],
            ['label' => 'Staff', 'href' => '/staff', 'permission' => 'staff.view'],
            ['label' => 'Classes & sections', 'href' => '/classes', 'permission' => 'classes.view'],
            ['label' => 'Attendance', 'href' => '/attendance', 'permission' => 'attendance.view'],
            ['label' => 'My attendance', 'href' => '/attendance', 'permission' => 'portal.attendance.view', 'roles' => ['Student']],
            ['label' => 'Children’s attendance', 'href' => '/attendance', 'permission' => 'portal.attendance.view', 'roles' => ['Parent']],
            ['label' => 'Attendance reports', 'href' => '/reports/attendance', 'permission' => 'reports.view'],
            ['label' => 'Academic results report', 'href' => '/reports/academic', 'permission' => 'results.view'],
            ['label' => 'Fee collection report', 'href' => '/reports/financial', 'permission' => 'reports.financial.view'],
            ['label' => 'Outstanding fees report', 'href' => '/reports/financial/outstanding', 'permission' => 'reports.financial.view'],
            ['label' => 'Admissions', 'href' => '/admissions', 'permission' => 'admissions.view'],
            ['label' => 'Homework', 'href' => '/homework', 'permission' => 'homework.view'],
            ['label' => 'Exams & results', 'href' => '/exams', 'permission' => 'exams.view'],
            ['label' => 'Fees', 'href' => '/fees', 'permission' => 'fees.view'],
            ['label' => 'Library', 'href' => '/library', 'permission' => 'library.view'],
            ['label' => 'Notices', 'href' => '/notices', 'permission' => 'notices.view'],
            ['label' => 'Timetable', 'href' => '/timetable', 'permission' => 'timetable.view'],
            ['label' => 'Leave requests', 'href' => '/leave-requests', 'permission' => 'leave.view'],
            ['label' => 'Certificates', 'href' => '/certificates', 'permission' => 'certificates.view'],
            ['label' => 'My timetable', 'href' => '/timetable', 'permission' => 'portal.timetable.view'],
            ['label' => 'My homework', 'href' => '/homework', 'permission' => 'portal.homework.view', 'roles' => ['Student']],
            ['label' => 'Children’s homework', 'href' => '/homework', 'permission' => 'portal.homework.view', 'roles' => ['Parent']],
            ['label' => 'My results', 'href' => '/exams', 'permission' => 'portal.results.view', 'roles' => ['Student']],
            ['label' => 'Children’s results', 'href' => '/exams', 'permission' => 'portal.results.view', 'roles' => ['Parent']],
            ['label' => 'My fees', 'href' => '/fees', 'permission' => 'portal.fees.view'],
            ['label' => 'My library', 'href' => '/library', 'permission' => 'portal.library.view'],
            ['label' => 'School notices', 'href' => '/notices', 'permission' => 'portal.notices.view'],
            ['label' => 'Request a certificate', 'href' => '/certificates', 'permission' => 'portal.certificates.request'],
            ['label' => 'Leave requests', 'href' => '/leave-requests', 'permission' => 'portal.leave.apply'],
        ];

        return collect($availableActions)
            ->filter(fn (array $action): bool => $user->can($action['permission'])
                && (! isset($action['roles']) || $user->hasRole($action['roles'])))
            ->unique('href')
            ->map(fn (array $action): array => [
                'label' => $action['label'],
                'href' => $action['href'],
            ])
            ->values()
            ->all();
    }
}
