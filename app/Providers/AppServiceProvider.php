<?php

namespace App\Providers;

use App\Models\AcademicYear;
use App\Models\Admission;
use App\Models\AdmissionStatusHistory;
use App\Models\Attendance;
use App\Models\AttendanceRecord;
use App\Models\CertificateRequest;
use App\Models\ClassSubject;
use App\Models\Enrolment;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\Guardian;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Models\LeaveRequest;
use App\Models\Promotion;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentNote;
use App\Models\StudentTransfer;
use App\Models\Subject;
use App\Models\TeacherClassAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Observers\AuditLogObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn (User $user, string $ability): ?bool => $user->hasRole('Super Admin') ? true : null);

        foreach ([
            AcademicYear::class,
            Admission::class,
            AdmissionStatusHistory::class,
            Attendance::class,
            AttendanceRecord::class,
            CertificateRequest::class,
            ClassSubject::class,
            Enrolment::class,
            Exam::class,
            ExamMark::class,
            ExamSubject::class,
            HomeworkAssignment::class,
            HomeworkSubmission::class,
            Guardian::class,
            LeaveRequest::class,
            Permission::class,
            Promotion::class,
            Role::class,
            SchoolClass::class,
            SchoolSetting::class,
            Section::class,
            Staff::class,
            Student::class,
            StudentDocument::class,
            StudentNote::class,
            StudentTransfer::class,
            Subject::class,
            TeacherClassAssignment::class,
            TeacherSubjectAssignment::class,
            Term::class,
            TimetableSlot::class,
            User::class,
        ] as $model) {
            $model::observe(AuditLogObserver::class);
        }
    }
}
