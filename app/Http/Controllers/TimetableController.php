<?php

namespace App\Http\Controllers;

use App\Enums\AcademicYearStatus;
use App\Http\Requests\StoreTimetableSlotRequest;
use App\Http\Requests\UpdateTimetableSlotRequest;
use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TeacherSubjectAssignment;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Services\TimetableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TimetableController extends Controller
{
    private const DAYS = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    // ------------------------------------------------------------------ index

    public function index(Request $request): Response
    {
        $user = $request->user();
        [$mode, $staff] = $this->resolveMode($request);
        $isManager = $mode === 'manager';
        $isTeacher = $mode === 'teacher';
        $isStudent = $mode === 'student';
        $isParent = $mode === 'parent';

        $activeYear = AcademicYear::active()->first();
        if ($isManager) {
            $years = AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'status']);
        } elseif ($isTeacher) {
            $years = AcademicYear::query()
                ->where(function ($query) use ($staff, $activeYear): void {
                    $query->where('id', $activeYear?->id ?? 0)
                        ->orWhereHas('timetableSlots.teachingAssignment', fn ($assignments) => $assignments
                            ->where('staff_id', $staff?->id ?? 0));
                })
                ->orderByDesc('start_date')
                ->get(['id', 'name', 'status']);
        } else {
            $years = $activeYear ? collect([$activeYear]) : collect();
        }

        $academicYear = $activeYear;
        if (($isManager || $isTeacher) && $request->filled('academic_year_id')) {
            $academicYear = $years->firstWhere('id', $request->integer('academic_year_id'));
            abort_unless($academicYear, 404);
        } elseif ($isManager || $isTeacher) {
            $academicYear = $years->firstWhere('id', $activeYear?->id) ?? $years->first();
        }

        $portalStudents = collect();
        $sectionIds = null;
        $selectedStudentId = null;

        if ($isStudent) {
            $sectionIds = $this->studentSectionIds($user, $academicYear);
        } elseif ($isParent) {
            $portalStudents = $this->parentStudents($user, $academicYear);

            if ($request->filled('student_id')) {
                $selectedStudentId = $request->integer('student_id');
                abort_unless($portalStudents->contains('id', $selectedStudentId), 404);
                $sectionIds = $portalStudents->where('id', $selectedStudentId)->pluck('sectionId');
            } else {
                $sectionIds = $portalStudents->pluck('sectionId')->unique()->values();
            }
        }

        $slots = TimetableSlot::query()
            ->where('academic_year_id', $academicYear?->id ?? 0)
            ->when($isTeacher, fn ($query) => $query->whereHas(
                'teachingAssignment',
                fn ($assignments) => $assignments->where('staff_id', $staff?->id ?? 0),
            ))
            ->when($sectionIds !== null, fn ($query) => $query->whereHas(
                'teachingAssignment',
                fn ($assignments) => $assignments->whereIn('section_id', $sectionIds),
            ))
            ->with($this->slotRelations())
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (TimetableSlot $slot): array => $this->slotPayload($slot));

        return Inertia::render('Timetable/Index', [
            'mode' => $mode,
            'years' => $years->map(fn (AcademicYear $year): array => [
                'id' => $year->id,
                'name' => $year->name,
                'status' => $year->status->value,
            ])->values(),
            'selectedAcademicYearId' => $academicYear?->id,
            'selectedStudentId' => $selectedStudentId,
            'students' => $portalStudents,
            'slots' => $slots,
            'canManage' => $this->canManage($isManager, $academicYear),
            'days' => self::DAYS,
        ]);
    }

    // ----------------------------------------------------------------- create

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('timetable.manage'), 403);

        $year = $request->filled('academic_year_id')
            ? AcademicYear::query()->findOrFail($request->integer('academic_year_id'))
            : AcademicYear::active()->first();
        abort_unless($year, 404);
        abort_if($year->status === AcademicYearStatus::Locked, 403);

        return Inertia::render('Timetable/Create', [
            'academicYear' => ['id' => $year->id, 'name' => $year->name],
            'assignments' => $this->assignmentOptions($year),
            'days' => self::DAYS,
        ]);
    }

    public function store(StoreTimetableSlotRequest $request, TimetableService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->create($data, $request->user());

        return redirect()
            ->route('timetable.index', ['academic_year_id' => $data['academic_year_id']])
            ->with('status', 'Timetable period added.');
    }

    // ------------------------------------------------------------------- show

    public function show(Request $request, TimetableSlot $timetableSlot): Response
    {
        $user = $request->user();
        [$mode, $staff] = $this->resolveMode($request);

        $timetableSlot->load($this->slotRelations());
        $year = AcademicYear::query()->findOrFail($timetableSlot->academic_year_id);
        $assignment = $timetableSlot->teachingAssignment;

        if ($mode === 'teacher') {
            abort_unless($staff && $assignment->staff_id === $staff->id, 403);
        } elseif ($mode === 'student' || $mode === 'parent') {
            abort_unless($year->id === AcademicYear::active()->value('id'), 403);
            $sectionIds = $mode === 'student'
                ? $this->studentSectionIds($user, $year)
                : $this->parentStudents($user, $year)->pluck('sectionId');
            abort_unless($sectionIds->contains($assignment->section_id), 403);
        }

        return Inertia::render('Timetable/Show', [
            'mode' => $mode,
            'slot' => $this->slotPayload($timetableSlot) + ['yearName' => $year->name],
            'canManage' => $this->canManage($mode === 'manager', $year),
        ]);
    }

    // ------------------------------------------------------------------- edit

    public function edit(Request $request, TimetableSlot $timetableSlot): Response
    {
        abort_unless($request->user()->can('timetable.manage'), 403);

        $year = AcademicYear::query()->findOrFail($timetableSlot->academic_year_id);
        abort_if($year->status === AcademicYearStatus::Locked, 403);

        $timetableSlot->load($this->slotRelations());

        return Inertia::render('Timetable/Edit', [
            'academicYear' => ['id' => $year->id, 'name' => $year->name],
            'slot' => $this->slotPayload($timetableSlot),
            'assignments' => $this->assignmentOptions($year),
            'days' => self::DAYS,
        ]);
    }

    public function update(
        UpdateTimetableSlotRequest $request,
        TimetableSlot $timetableSlot,
        TimetableService $service,
    ): RedirectResponse {
        $service->update($timetableSlot, $request->validated(), $request->user());

        return redirect()
            ->route('timetable.show', $timetableSlot)
            ->with('status', 'Timetable period updated.');
    }

    // ---------------------------------------------------------------- destroy

    public function destroy(
        Request $request,
        TimetableSlot $timetableSlot,
        TimetableService $service,
    ): RedirectResponse {
        $yearId = $timetableSlot->academic_year_id;
        $service->delete($timetableSlot, $request->user());

        return redirect()
            ->route('timetable.index', ['academic_year_id' => $yearId])
            ->with('status', 'Timetable period removed.');
    }

    // ---------------------------------------------------------------- helpers

    /** @return array{0: string, 1: ?Staff} [mode, teacher staff record] */
    private function resolveMode(Request $request): array
    {
        $user = $request->user();
        $isManager = $user->can('timetable.manage');
        $isTeacher = ! $isManager && $user->hasRole('Teacher') && $user->can('timetable.view');
        $isStudent = ! $isManager && ! $isTeacher
            && $user->hasRole('Student')
            && $user->can('portal.timetable.view');
        $isParent = ! $isManager && ! $isTeacher && ! $isStudent
            && $user->hasRole('Parent')
            && $user->can('portal.timetable.view');
        abort_unless($isManager || $isTeacher || $isStudent || $isParent, 403);

        $staff = $isTeacher
            ? Staff::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->where('is_teacher', true)
                ->first()
            : null;

        $mode = $isManager ? 'manager' : ($isTeacher ? 'teacher' : ($isParent ? 'parent' : 'student'));

        return [$mode, $staff];
    }

    private function canManage(bool $isManager, ?AcademicYear $year): bool
    {
        return $isManager && $year !== null && $year->status !== AcademicYearStatus::Locked;
    }

    private function studentSectionIds(User $user, ?AcademicYear $year): Collection
    {
        $student = Student::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        $enrolment = $student && $year
            ? Enrolment::query()
                ->where('academic_year_id', $year->id)
                ->where('student_id', $student->id)
                ->where('status', 'active')
                ->first()
            : null;

        return collect([$enrolment?->section_id ?? 0]);
    }

    private function parentStudents(User $user, ?AcademicYear $year): Collection
    {
        return Enrolment::query()
            ->with('student:id,first_name,last_name')
            ->where('academic_year_id', $year?->id ?? 0)
            ->where('status', 'active')
            ->whereHas('student', fn ($students) => $students->forGuardianAccount($user))
            ->orderBy('student_id')
            ->get()
            ->unique('student_id')
            ->map(fn (Enrolment $enrolment): array => [
                'id' => $enrolment->student_id,
                'name' => trim($enrolment->student->first_name.' '.$enrolment->student->last_name),
                'sectionId' => $enrolment->section_id,
            ])
            ->values();
    }

    private function assignmentOptions(AcademicYear $year): Collection
    {
        return TeacherSubjectAssignment::query()
            ->with(['section.schoolClass:id,name', 'subject:id,name', 'staff:id,name,status,is_teacher'])
            ->where('academic_year_id', $year->id)
            ->whereHas('staff', fn ($query) => $query->where('status', 'active')->where('is_teacher', true))
            ->orderBy('section_id')
            ->orderBy('subject_id')
            ->get()
            ->map(fn (TeacherSubjectAssignment $assignment): array => [
                'id' => $assignment->id,
                'label' => $assignment->section->schoolClass->name.' · '.$assignment->section->name
                    .' · '.$assignment->subject->name.' · '.$assignment->staff->name,
            ])
            ->values();
    }

    private function slotRelations(): array
    {
        return [
            'teachingAssignment.section.schoolClass:id,name',
            'teachingAssignment.subject:id,name',
            'teachingAssignment.staff:id,name',
        ];
    }

    private function slotPayload(TimetableSlot $slot): array
    {
        $assignment = $slot->teachingAssignment;

        return [
            'id' => $slot->id,
            'teacherSubjectAssignmentId' => $slot->teacher_subject_assignment_id,
            'dayOfWeek' => $slot->day_of_week,
            'dayName' => self::DAYS[$slot->day_of_week],
            'startsAt' => substr($slot->starts_at, 0, 5),
            'endsAt' => substr($slot->ends_at, 0, 5),
            'room' => $slot->room,
            'sectionId' => $assignment->section_id,
            'section' => $assignment->section->schoolClass->name.' · '.$assignment->section->name,
            'subject' => $assignment->subject->name,
            'teacher' => $assignment->staff->name,
        ];
    }
}