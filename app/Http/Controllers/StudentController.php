<?php

namespace App\Http\Controllers;

use App\Enums\AcademicYearStatus;
use App\Http\Requests\StoreEnrolmentRequest;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
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
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Student::class);

        return Inertia::render('Students/Form', ['student' => null]);
    }

    public function store(StoreStudentRequest $request, StudentProfileService $service): RedirectResponse
    {
        $student = $service->create($request->validated());

        return redirect()->route('students.show', $student)->with('status', 'Student profile created.');
    }

    public function show(Request $request, Student $student): Response
    {
        $student = Student::query()->visibleTo($request->user())->findOrFail($student->getKey());
        $this->authorize('view', $student);
        $student->load([
            'guardians' => fn ($query) => $query->orderByPivot('is_primary', 'desc')->orderBy('name'),
            'enrolments.academicYear',
            'enrolments.schoolClass',
            'enrolments.section',
            'documents' => fn ($query) => $query->latest(),
        ]);
        $canEnrol = $request->user()->can('enrol', $student);
        $years = $canEnrol
            ? AcademicYear::query()->whereNot('status', AcademicYearStatus::Locked)->orderByDesc('start_date')->get()
            : collect();

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
                    ]),
                'documents' => $student->documents->map(fn ($document): array => [
                    'id' => $document->id,
                    'category' => $document->category,
                    'name' => $document->original_name,
                    'size' => $document->size_bytes,
                    'uploadedAt' => $document->created_at->toDateString(),
                    'downloadUrl' => route('students.documents.download', [$student, $document]),
                ]),
            ],
            'canEdit' => $request->user()->can('update', $student),
            'canUploadDocuments' => $request->user()->can('manageDocuments', $student),
            'canEnrol' => $canEnrol,
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

        return Inertia::render('Students/Form', [
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
        $service->update($student, $request->validated());

        return redirect()->route('students.show', $student)->with('status', 'Student profile updated.');
    }

    public function storeEnrolment(
        StoreEnrolmentRequest $request,
        Student $student,
        StudentProfileService $service,
    ): RedirectResponse {
        $service->enrol($student, $request->validated());

        return back()->with('status', 'Student enrolled for the academic year.');
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
