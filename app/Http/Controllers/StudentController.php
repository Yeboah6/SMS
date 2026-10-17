<?php

namespace App\Http\Controllers;

use App\Enums\AcademicYearStatus;
use App\Http\Requests\StoreEnrolmentRequest;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\ExamMark;
use App\Models\HomeworkSubmission;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentActivity;
use App\Models\User;
use App\Services\StudentProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('students.view'), 403);

        $user = $request->user();

        $activeYearId = AcademicYear::active()->value('id');
        $search = $request->string('search')->trim()->toString();
        $students = Student::query()
            ->visibleTo($request->user())
            ->with(['guardians:id,name,phone', 'enrolments' => fn ($query) => $query
                ->when($activeYearId, fn ($query) => $query->where('academic_year_id', $activeYearId))
                ->when(! $activeYearId, fn ($query) => $query->whereRaw('1 = 0'))
                ->with(['schoolClass:id,name', 'section:id,class_id,name'])])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('admission_number', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('middle_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhereHas('guardians', fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"))))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Student $student): array => [
                'id' => $student->id,
                'admissionNumber' => $student->admission_number,
                'name' => $this->studentName($student),
                'admissionDate' => $student->admission_date->toDateString(),
                'status' => $student->status,
                'canEdit' => $user->can('update', $student),
                'canDelete' => $user->can('delete', $student),
                'guardians' => $student->guardians->map(fn ($guardian): array => [
                    'name' => $guardian->name,
                    'phone' => $guardian->phone,
                ]),
                'enrolment' => $student->enrolments->first() ? [
                    'class' => $student->enrolments->first()->schoolClass->name,
                    'section' => $student->enrolments->first()->section->name,
                ] : null,
            ]);

        return Inertia::render('Students/Index', [
            'students' => $students,
            'search' => $search,
            'canCreate' => $request->user()->can('create', Student::class),
            'canPromote' => $request->user()->can('students.promote'),
            'canImport' => $request->user()->can('create', Student::class) && $request->user()->can('guardians.create'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Student::class);
    
        return Inertia::render('Students/Create');
    }

    public function store(StoreStudentRequest $request, StudentProfileService $service): RedirectResponse
    {
        $data = $request->validated();
        $generated = blank($data['admission_number'] ?? null);
        $data['admission_number'] = $generated ? null : $data['admission_number'];
    
        for ($attempt = 1; ; $attempt++) {
            try {
                $student = $service->create($data, $request->user());
                break;
            } catch (UniqueConstraintViolationException $exception) {
                if (! $generated || $attempt >= 3) {
                    throw $exception;
                }
            }
        }
    
        return redirect()->route('students.show', $student)->with('status', "Student profile {$student->admission_number} created.");
    }

    public function show(Request $request, Student $student): Response
    {
        $student = Student::query()->visibleTo($request->user())->findOrFail($student->getKey());
        $this->authorize('view', $student);
        $user = $request->user();
        $canViewNotes = $user->can('student_notes.view');
        $canViewAuditHistory = $user->can('student_history.view');
        $canManageGuardianAccounts = $user->can('guardians.manage_accounts');
        $canViewAttendance = $user->can('attendance.view') || $user->can('portal.attendance.view');
        $canViewResults = $user->can('results.view') || $user->can('portal.results.view');
        $canViewFees = $user->can('fees.view') || $user->can('portal.fees.view');
        $canViewHomework = $user->can('homework.view') || $user->can('portal.homework.view');
        $student->load([
            'guardians' => fn ($query) => $query->with('user:id,name')->orderByPivot('is_primary', 'desc')->orderBy('name'),
            'enrolments.academicYear',
            'enrolments.schoolClass',
            'enrolments.section',
            'enrolments.promotionAsSource',
            'transfers.academicYear',
            'transfers.processor',
            'documents' => fn ($query) => $query->latest(),
        ]);
        $notes = $canViewNotes
            ? $student->notes()->with('author:id,name')->limit(5)->get()
            : collect();
        $attendanceRecords = $canViewAttendance
            ? AttendanceRecord::query()
                ->with(['attendance.academicYear:id,name', 'attendance.section.schoolClass:id,name', 'enrolment:id,student_id'])
                ->where('student_id', $student->id)
                ->orderByDesc('attendance_id')
                ->orderByDesc('id')
                ->paginate(10, ['*'], 'attendance_page')
                ->withQueryString()
                ->through(fn (AttendanceRecord $record): array => [
                    'id' => $record->id,
                    'date' => $record->attendance->attendance_date->toDateString(),
                    'academicYear' => $record->attendance->academicYear->name,
                    'class' => $record->attendance->section->schoolClass->name,
                    'section' => $record->attendance->section->name,
                    'status' => $record->status,
                    'remarks' => $record->remarks,
                ])
            : null;
        $examMarks = $canViewResults
            ? ExamMark::query()
                ->with(['examSubject.exam.academicYear:id,name', 'examSubject.exam.term:id,name', 'examSubject.subject:id,name'])
                ->where('student_id', $student->id)
                ->whereHas('examSubject.exam', fn ($query) => $query->where('status', 'published'))
                ->orderByDesc('id')
                ->paginate(10, ['*'], 'results_page')
                ->withQueryString()
                ->through(fn (ExamMark $mark): array => [
                    'id' => $mark->id,
                    'exam' => $mark->examSubject->exam->name,
                    'term' => $mark->examSubject->exam->term?->name,
                    'academicYear' => $mark->examSubject->exam->academicYear->name,
                    'subject' => $mark->examSubject->subject->name,
                    'date' => $mark->examSubject->exam->start_date->toDateString(),
                    'marks' => $mark->marks_obtained,
                    'maxMarks' => $mark->examSubject->max_marks,
                    'status' => $mark->status,
                ])
            : null;
        $invoices = $canViewFees
            ? Invoice::query()
                ->with(['academicYear:id,name', 'payments:id,invoice_id,receipt_number,amount,payment_date'])
                ->where('student_id', $student->id)
                ->orderByDesc('issued_at')
                ->orderByDesc('id')
                ->paginate(10, ['*'], 'fees_page')
                ->withQueryString()
                ->through(fn (Invoice $invoice): array => [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'academicYear' => $invoice->academicYear->name,
                    'issuedAt' => $invoice->issued_at->toDateString(),
                    'dueDate' => $invoice->due_date->toDateString(),
                    'status' => $invoice->status,
                    'total' => $invoice->total_amount,
                    'paid' => $invoice->paid_amount,
                    'payments' => $invoice->payments->map(fn ($payment): array => [
                        'receipt' => $payment->receipt_number,
                        'amount' => $payment->amount,
                        'date' => $payment->payment_date->toDateString(),
                    ])->values(),
                ])
            : null;
        $homeworkSubmissions = $canViewHomework
            ? HomeworkSubmission::query()
                ->with(['assignment.academicYear:id,name', 'assignment.subject:id,name'])
                ->where('student_id', $student->id)
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->paginate(10, ['*'], 'homework_page')
                ->withQueryString()
                ->through(fn (HomeworkSubmission $submission): array => [
                    'id' => $submission->id,
                    'title' => $submission->assignment->title,
                    'subject' => $submission->assignment->subject->name,
                    'academicYear' => $submission->assignment->academicYear->name,
                    'dueDate' => $submission->assignment->due_date->toDateString(),
                    'submittedAt' => $submission->submitted_at->toDateString(),
                    'score' => $submission->score,
                    'maxPoints' => $submission->assignment->max_points,
                    'gradedAt' => $submission->graded_at?->toDateString(),
                    'feedback' => $submission->feedback,
                ])
            : null;
        $eligibleParentAccounts = $canManageGuardianAccounts
            ? User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', 'Parent'))
                ->whereDoesntHave('guardians')
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $parent): array => [
                    'id' => $parent->id,
                    'name' => $parent->name,
                    'email' => $parent->email,
                ])
            : collect();
        $history = collect()
            ->merge($student->enrolments->map(fn ($enrolment): array => [
                'date' => $enrolment->enrolment_date->toDateString(),
                'type' => 'Enrolment',
                'title' => $enrolment->schoolClass->name.' · Section '.$enrolment->section->name,
                'detail' => $enrolment->academicYear->name.' · '.($enrolment->outcome ?: $enrolment->status),
            ]))
            ->merge($student->transfers->map(fn ($transfer): array => [
                'date' => $transfer->transfer_date->toDateString(),
                'type' => 'Transfer',
                'title' => $transfer->destination_school,
                'detail' => $transfer->academicYear->name,
            ]))
            ->merge($student->documents->map(fn ($document): array => [
                'date' => $document->created_at->toDateString(),
                'type' => 'Document',
                'title' => $document->original_name,
                'detail' => str_replace('_', ' ', $document->category),
            ]))
            ->merge($canViewNotes ? $notes->map(fn ($note): array => [
                'date' => $note->note_date->toDateString(),
                'type' => 'Note',
                'title' => $note->title,
                'detail' => $note->note_type,
            ]) : collect())
            ->merge($canViewAttendance ? AttendanceRecord::query()
                ->with(['attendance.academicYear:id,name', 'attendance.section.schoolClass:id,name'])
                ->where('student_id', $student->id)
                ->orderByDesc('attendance_id')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn (AttendanceRecord $record): array => [
                    'date' => $record->attendance->attendance_date->toDateString(),
                    'type' => 'Attendance',
                    'title' => ucfirst($record->status),
                    'detail' => $record->attendance->academicYear->name.' · '
                        .$record->attendance->section->schoolClass->name.' · '
                        .$record->attendance->section->name,
                ]) : collect())
            ->merge($canViewResults ? ExamMark::query()
                ->with(['examSubject.exam', 'examSubject.subject:id,name'])
                ->where('student_id', $student->id)
                ->whereHas('examSubject.exam', fn ($query) => $query->where('status', 'published'))
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn (ExamMark $mark): array => [
                    'date' => $mark->examSubject->exam->start_date->toDateString(),
                    'type' => 'Result',
                    'title' => $mark->examSubject->exam->name.' · '.$mark->examSubject->subject->name,
                    'detail' => $mark->marks_obtained.' / '.$mark->examSubject->max_marks,
                ]) : collect())
            ->merge($canViewFees ? Invoice::query()
                ->where('student_id', $student->id)
                ->orderByDesc('issued_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn (Invoice $invoice): array => [
                    'date' => $invoice->issued_at->toDateString(),
                    'type' => 'Fee invoice',
                    'title' => $invoice->invoice_number,
                    'detail' => $invoice->status.' · '.$invoice->paid_amount.' paid',
                ]) : collect())
            ->merge($canViewHomework ? HomeworkSubmission::query()
                ->with(['assignment.subject:id,name'])
                ->where('student_id', $student->id)
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn (HomeworkSubmission $submission): array => [
                    'date' => $submission->submitted_at->toDateString(),
                    'type' => 'Homework',
                    'title' => $submission->assignment->title,
                    'detail' => $submission->assignment->subject->name.' · '
                        .($submission->score ?? 'Not graded'),
                ]) : collect())
            ->sortByDesc('date')
            ->values()
            ->take(50);
        $auditEntries = $canViewAuditHistory
            ? $student->activities()
                ->with('actor:id,name')
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->paginate(20, ['*'], 'activity_page')
                ->withQueryString()
                ->through(fn (StudentActivity $activity): array => [
                    'id' => $activity->id,
                    'eventType' => $activity->event_type,
                    'summary' => $activity->summary,
                    'actor' => $activity->actor?->name ?? 'System',
                    'occurredAt' => $activity->occurred_at->toDateTimeString(),
                    'details' => $activity->details ?? [],
                ])
            : null;
        $canEnrol = $request->user()->can('enrol', $student);
        $years = $canEnrol
            ? AcademicYear::query()->whereNot('status', AcademicYearStatus::Locked)->orderByDesc('start_date')->get()
            : collect();
        $transferEnrolment = $student->enrolments->first(fn ($enrolment): bool => (
            $enrolment->status === 'active'
            && $enrolment->academicYear->status === AcademicYearStatus::Active
        ));

        return Inertia::render('Students/Show', [
            'student' => [
                'id' => $student->id,
                'admissionNumber' => $student->admission_number,
                'firstName' => $student->first_name,
                'middleName' => $student->middle_name,
                'lastName' => $student->last_name,
                'name' => $this->studentName($student),
                'dateOfBirth' => $student->date_of_birth->toDateString(),
                'gender' => $student->gender,
                'bloodGroup' => $student->blood_group,
                'religion' => $student->religion,
                'address' => $student->address,
                'photoUrl' => $student->photo_path ? route('students.photo', $student) : null,
                'admissionDate' => $student->admission_date->toDateString(),
                'status' => $student->status,
                'guardians' => $student->guardians->map(fn ($guardian): array => [
                    'id' => $guardian->id,
                    'name' => $guardian->name,
                    'phone' => $guardian->phone,
                    'email' => $guardian->email,
                    'userId' => $guardian->user_id,
                    'accountName' => $guardian->user?->name,
                    'occupation' => $guardian->occupation,
                    'address' => $guardian->address,
                    'relationship' => $guardian->pivot->relationship,
                    'isPrimary' => (bool) $guardian->pivot->is_primary,
                    'livesWithStudent' => (bool) $guardian->pivot->lives_with_student,
                ]),
                'enrolments' => $student->enrolments
                    ->sortByDesc(fn ($enrolment) => $enrolment->academicYear->start_date)
                    ->values()
                    ->map(fn ($enrolment): array => [
                        'id' => $enrolment->id,
                        'academicYear' => $enrolment->academicYear->name,
                        'class' => $enrolment->schoolClass->name,
                        'section' => $enrolment->section->name,
                        'date' => $enrolment->enrolment_date->toDateString(),
                        'status' => $enrolment->status,
                        'outcome' => $enrolment->outcome,
                        'outcomeReason' => $enrolment->promotionAsSource?->reason,
                    ]),
                'documents' => $student->documents->map(fn ($document): array => [
                    'id' => $document->id,
                    'category' => $document->category,
                    'name' => $document->original_name,
                    'size' => $document->size_bytes,
                    'uploadedAt' => $document->created_at->toDateString(),
                    'downloadUrl' => route('students.documents.download', [$student, $document]),
                ]),
                'notes' => $notes->map(fn ($note): array => [
                    'id' => $note->id,
                    'type' => $note->note_type,
                    'title' => $note->title,
                    'content' => $note->content,
                    'date' => $note->note_date->toDateString(),
                    'visibility' => $note->visibility,
                    'author' => $note->author->name,
                    'attachmentName' => $note->attachment_name,
                    'attachmentSize' => $note->attachment_size_bytes,
                    'showUrl' => route('students.notes.show', [$student, $note]),
                    'canEdit' => $user->can('update', $note),
                    'canDelete' => $user->can('delete', $note),
                    'attachmentUrl' => $note->attachment_path
                        ? route('students.notes.attachment', [$student, $note])
                        : null,
                ])->values(),
            ],
            'canEdit' => $request->user()->can('update', $student),
            'canDelete' => $request->user()->can('delete', $student),
            'canUploadDocuments' => $request->user()->can('manageDocuments', $student),
            'canEnrol' => $canEnrol,
            'canTransfer' => $request->user()->can('students.transfer')
                && $student->status === 'active'
                && $transferEnrolment !== null,
            'transferEnrolmentId' => $transferEnrolment?->id,
            'transfers' => $student->transfers->map(fn ($transfer): array => [
                'date' => $transfer->transfer_date->toDateString(),
                'destinationSchool' => $transfer->destination_school,
                'academicYear' => $transfer->academicYear->name,
                'reason' => $transfer->reason,
                'processedBy' => $transfer->processor->name,
            ])->values(),
            'canViewNotes' => $canViewNotes,
            'canCreateNotes' => $canViewNotes && $request->user()->can('student_notes.create'),
            'canViewAuditHistory' => $canViewAuditHistory,
            'auditEntries' => $auditEntries,
            'canManageGuardianAccounts' => $canManageGuardianAccounts,
            'eligibleParentAccounts' => $eligibleParentAccounts,
            'canViewAttendance' => $canViewAttendance,
            'attendanceRecords' => $attendanceRecords,
            'canViewResults' => $canViewResults,
            'examMarks' => $examMarks,
            'canViewFees' => $canViewFees,
            'invoices' => $invoices,
            'canViewHomework' => $canViewHomework,
            'homeworkSubmissions' => $homeworkSubmissions,
            'history' => $history,
            'academicYears' => $years->map(fn (AcademicYear $year): array => [
                'id' => $year->id,
                'name' => $year->name,
                'status' => $year->status->value,
            ]),
            'classes' => $canEnrol
                ? SchoolClass::query()
                    ->with(['academicYear:id,name', 'sections:id,class_id,name'])
                    ->whereHas('academicYear', fn ($query) => $query->whereNot('status', AcademicYearStatus::Locked))
                    ->orderBy('name')
                    ->get()
                    ->map(fn (SchoolClass $schoolClass): array => [
                        'id' => $schoolClass->id,
                        'name' => $schoolClass->name,
                        'academicYearId' => $schoolClass->academic_year_id,
                        'sections' => $schoolClass->sections->map(fn ($section): array => [
                            'id' => $section->id,
                            'name' => $section->name,
                        ]),
                    ])
                : [],
            'today' => today()->toDateString(),
        ]);
    }

    public function edit(Request $request, Student $student): Response
    {
        $student = Student::query()->visibleTo($request->user())->findOrFail($student->getKey());
        $this->authorize('update', $student);

        return Inertia::render('Students/Edit', [
            'student' => [
                'id' => $student->id,
                'admissionNumber' => $student->admission_number,
                'firstName' => $student->first_name,
                'middleName' => $student->middle_name,
                'lastName' => $student->last_name,
                'dateOfBirth' => $student->date_of_birth->toDateString(),
                'gender' => $student->gender,
                'bloodGroup' => $student->blood_group,
                'religion' => $student->religion,
                'address' => $student->address,
                'photoUrl' => $student->photo_path ? route('students.photo', $student) : null,
                'admissionDate' => $student->admission_date->toDateString(),
                'status' => $student->status,
                'guardians' => $student->guardians()
                    ->orderByPivot('is_primary', 'desc')
                    ->get()
                    ->map(fn ($guardian): array => [
                        'guardian_id' => $guardian->id,
                        'name' => $guardian->name,
                        'phone' => $guardian->phone,
                        'email' => $guardian->email,
                        'occupation' => $guardian->occupation,
                        'address' => $guardian->address,
                        'relationship' => $guardian->pivot->relationship,
                        'is_primary' => (bool) $guardian->pivot->is_primary,
                        'lives_with_student' => (bool) $guardian->pivot->lives_with_student,
                    ]),
            ],
        ]);
    }

    public function update(
        UpdateStudentRequest $request,
        Student $student,
        StudentProfileService $service,
    ): RedirectResponse {
        $service->update($student, $request->validated(), $request->user());

        return redirect()->route('students.show', $student)->with('status', 'Student profile updated.');
    }

    public function storeEnrolment(
        StoreEnrolmentRequest $request,
        Student $student,
        StudentProfileService $service,
    ): RedirectResponse {
        $service->enrol($student, $request->validated(), $request->user());

        return back()->with('status', 'Student enrolled for the academic year.');
    }

    public function destroy(Request $request, Student $student, StudentProfileService $service): RedirectResponse
    {
        $student = Student::query()->visibleTo($request->user())->findOrFail($student->getKey());
        $this->authorize('delete', $student);
    
        $service->delete($student, $request->user());
    
        return redirect()->route('students.index')->with('status', 'Student profile deleted.');
    }

    private function studentName(Student $student): string
    {
        return implode(' ', array_filter([
            $student->first_name,
            $student->middle_name,
            $student->last_name,
        ]));
    }
}
