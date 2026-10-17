<?php

namespace App\Http\Controllers;

use App\Enums\AcademicYearStatus;
use App\Http\Requests\StoreAttendanceRequest;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceRecord;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    private const STATUSES = ['present', 'absent', 'late', 'excused'];

    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user->can('attendance.view')) {
            return $this->portalIndex($request);
        }

        $years = AcademicYear::query()->orderByDesc('start_date')->get();
        $year = $this->selectedYear($request, $years);
        $date = $this->attendanceDate($request, $year);
        $sections = $this->visibleSections($request, $year);

        $attendances = $year
            ? Attendance::query()
                ->where('academic_year_id', $year->id)
                ->whereDate('attendance_date', $date)
                ->whereIn('section_id', $sections->modelKeys())
                ->get(['id', 'section_id'])
                ->keyBy('section_id')
            : collect();
        $counts = $attendances->isEmpty()
            ? collect()
            : AttendanceRecord::query()
                ->whereIn('attendance_id', $attendances->pluck('id'))
                ->select('attendance_id', 'status', DB::raw('count(*) as total'))
                ->groupBy('attendance_id', 'status')
                ->get()
                ->groupBy('attendance_id');

        $registers = $sections->map(function (Section $section) use ($attendances, $counts): array {
            $attendance = $attendances->get($section->id);
            $byStatus = $attendance ? $counts->get($attendance->id, collect())->pluck('total', 'status') : collect();

            return [
                'sectionId' => $section->id,
                'className' => $section->schoolClass->name,
                'sectionName' => $section->name,
                'attendanceId' => $attendance?->id,
                'taken' => $attendance !== null,
                'present' => (int) $byStatus->get('present', 0),
                'absent' => (int) $byStatus->get('absent', 0),
                'late' => (int) $byStatus->get('late', 0),
                'excused' => (int) $byStatus->get('excused', 0),
            ];
        })->values();

        $open = $year && $year->status !== AcademicYearStatus::Locked;

        return Inertia::render('Attendance/Index', [
            'academicYears' => $years->map(fn (AcademicYear $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'status' => $item->status->value,
            ])->values(),
            'selectedAcademicYearId' => $year?->id,
            'yearStart' => $year?->start_date->toDateString(),
            'yearEnd' => $year?->end_date->toDateString(),
            'attendanceDate' => $date->toDateString(),
            'registers' => $registers,
            'summary' => [
                'sections' => $registers->count(),
                'taken' => $registers->where('taken', true)->count(),
                'missing' => $registers->where('taken', false)->count(),
            ],
            'yearLocked' => $year !== null && ! $open,
            'can' => [
                'mark' => $user->can('attendance.mark') && $open,
                'update' => $user->can('attendance.update') && $open,
            ],
        ]);
    }

    public function create(Request $request, AttendanceService $service): Response|RedirectResponse
    {
        abort_unless($request->user()->can('attendance.mark'), 403);

        $years = AcademicYear::query()->orderByDesc('start_date')->get();
        $year = $this->selectedYear($request, $years);
        abort_unless($year, 404);
        abort_if($year->status === AcademicYearStatus::Locked, 403);

        $date = $this->attendanceDate($request, $year);
        $section = $this->visibleSections($request, $year)->firstWhere('id', $request->integer('section_id'));
        abort_unless($section, 404);

        $existing = Attendance::query()
            ->where('academic_year_id', $year->id)
            ->where('section_id', $section->id)
            ->whereDate('attendance_date', $date)
            ->first();
        if ($existing) {
            return to_route('attendance.edit', $existing);
        }

        return Inertia::render('Attendance/Create', [
            'register' => $this->registerContext($year, $section, $date),
            'students' => $this->roster($service, $year, $section, $date, null),
        ]);
    }

    public function store(StoreAttendanceRequest $request, AttendanceService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->record($data, $request->user());

        $attendance = Attendance::query()
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('section_id', $data['section_id'])
            ->whereDate('attendance_date', $data['attendance_date'])
            ->first();

        return $attendance
            ? to_route('attendance.show', $attendance)->with('status', 'Attendance recorded.')
            : to_route('attendance.index', ['academic_year_id' => $data['academic_year_id'], 'date' => $data['attendance_date']])
                ->with('status', 'Attendance recorded.');
    }

    public function show(Request $request, Attendance $attendance): Response
    {
        $user = $request->user();
        abort_unless($user->can('attendance.view'), 403);

        $attendance->load([
            'academicYear:id,name,status',
            'section.schoolClass:id,name',
            'records.student:id,first_name,middle_name,last_name,admission_number',
        ]);
        $this->authorizeRegister($request, $attendance);

        $records = $attendance->records
            ->map(fn (AttendanceRecord $record): array => [
                'id' => $record->id,
                'studentId' => $record->student_id,
                'name' => $this->studentName($record->student),
                'admissionNumber' => $record->student->admission_number,
                'status' => $record->status,
                'remarks' => $record->remarks,
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
        $open = $attendance->academicYear->status !== AcademicYearStatus::Locked;

        return Inertia::render('Attendance/Show', [
            'register' => $this->registerContext($attendance->academicYear, $attendance->section, $attendance->attendance_date) + ['id' => $attendance->id],
            'records' => $records,
            'counts' => collect(self::STATUSES)->mapWithKeys(fn (string $status): array => [$status => $records->where('status', $status)->count()]),
            'can' => [
                'update' => $user->can('attendance.update') && $open,
                'delete' => $user->can('attendance.delete') && $open,
            ],
        ]);
    }

    public function edit(Request $request, Attendance $attendance, AttendanceService $service): Response
    {
        abort_unless($request->user()->can('attendance.update'), 403);

        $attendance->load(['academicYear', 'section.schoolClass:id,name', 'records:id,attendance_id,student_id,status,remarks']);
        abort_if($attendance->academicYear->status === AcademicYearStatus::Locked, 403);
        $this->authorizeRegister($request, $attendance);

        return Inertia::render('Attendance/Edit', [
            'register' => $this->registerContext($attendance->academicYear, $attendance->section, $attendance->attendance_date) + ['id' => $attendance->id],
            'students' => $this->roster($service, $attendance->academicYear, $attendance->section, $attendance->attendance_date, $attendance),
        ]);
    }

    public function destroy(Request $request, Attendance $attendance): RedirectResponse
    {
        abort_unless($request->user()->can('attendance.delete'), 403);

        $attendance->load('academicYear');
        abort_if($attendance->academicYear->status === AcademicYearStatus::Locked, 403);
        $this->authorizeRegister($request, $attendance);

        $yearId = $attendance->academic_year_id;
        $date = $attendance->attendance_date->toDateString();

        DB::transaction(function () use ($attendance): void {
            $attendance->records()->delete();
            $attendance->delete();
        });

        return to_route('attendance.index', ['academic_year_id' => $yearId, 'date' => $date])
            ->with('status', 'Attendance register deleted.');
    }

    // ------------------------------------------------------------------ helpers

    private function selectedYear(Request $request, Collection $years): ?AcademicYear
    {
        if ($request->filled('academic_year_id')) {
            $year = $years->firstWhere('id', $request->integer('academic_year_id'));
            abort_unless($year, 404);

            return $year;
        }

        return AcademicYear::active()->first();
    }

    private function attendanceDate(Request $request, ?AcademicYear $year): Carbon
    {
        $input = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? null;
        $date = $input ? Carbon::createFromFormat('Y-m-d', $input)->startOfDay() : today();

        if ($year && ! $input) {
            if ($date->lt($year->start_date)) {
                $date = $year->start_date->copy();
            } elseif ($date->gt($year->end_date)) {
                $date = $year->end_date->copy();
            }
        }

        if ($year && ($date->lt($year->start_date) || $date->gt($year->end_date))) {
            throw ValidationException::withMessages([
                'date' => 'The attendance date must be within the selected academic year.',
            ]);
        }

        return $date;
    }

    private function visibleSections(Request $request, ?AcademicYear $year): Collection
    {
        if (! $year) {
            return collect();
        }

        $user = $request->user();

        return Section::query()
            ->with('schoolClass:id,name')
            ->where('academic_year_id', $year->id)
            ->when($user->hasRole('Teacher') && ! $user->hasRole('Super Admin'), function ($query) use ($user, $year): void {
                $staffId = Staff::query()->where('user_id', $user->id)->value('id');
                $query->whereHas('teacherClassAssignments', fn ($assignments) => $assignments
                    ->forAcademicYear($year)
                    ->where('staff_id', $staffId ?? 0));
            })
            ->orderBy('class_id')
            ->orderBy('name')
            ->get();
    }

    private function authorizeRegister(Request $request, Attendance $attendance): void
    {
        abort_unless(
            $this->visibleSections($request, $attendance->academicYear)->contains('id', $attendance->section_id),
            404,
        );
    }

    private function registerContext(AcademicYear $year, Section $section, Carbon $date): array
    {
        return [
            'academicYearId' => $year->id,
            'academicYear' => $year->name,
            'sectionId' => $section->id,
            'className' => $section->schoolClass->name,
            'sectionName' => $section->name,
            'date' => $date->toDateString(),
        ];
    }

    private function roster(AttendanceService $service, AcademicYear $year, Section $section, Carbon $date, ?Attendance $attendance): Collection
    {
        $recorded = $attendance?->records->keyBy('student_id') ?? collect();

        return $service->roster($year, $section, $date)
            ->map(fn ($enrolment): array => [
                'id' => $enrolment->student_id,
                'name' => $this->studentName($enrolment->student),
                'admissionNumber' => $enrolment->student->admission_number,
                'status' => $recorded->get($enrolment->student_id)?->status ?? 'present',
                'remarks' => $recorded->get($enrolment->student_id)?->remarks ?? '',
            ])->values();
    }

    private function studentName(Student $student): string
    {
        return trim(implode(' ', array_filter([$student->first_name, $student->middle_name, $student->last_name])));
    }

    private function portalIndex(Request $request): Response
    {
        $user = $request->user();
        $isStudent = $user->hasRole('Student');
        $isParent = ! $isStudent && $user->hasRole('Parent');
        abort_unless(
            ($isStudent || $isParent) && $user->can('portal.attendance.view'),
            403,
        );

        $linkedStudents = $isStudent
            ? Student::query()->where('user_id', $user->id)->orderBy('id')->get()
            : Student::query()->forGuardianAccount($user)->orderBy('first_name')->orderBy('last_name')->get();
        $students = $linkedStudents;
        $selectedStudentId = null;

        if ($isParent && $request->filled('student_id')) {
            $selectedStudentId = $request->integer('student_id');
            abort_unless($linkedStudents->contains('id', $selectedStudentId), 404);
            $students = $linkedStudents->where('id', $selectedStudentId);
        }

        $records = AttendanceRecord::query()
            ->with([
                'student:id,first_name,middle_name,last_name,admission_number',
                'attendance.academicYear:id,name',
                'attendance.section.schoolClass:id,name',
            ])
            ->whereIn('student_id', $students->modelKeys())
            ->orderByDesc(
                Attendance::query()
                    ->select('attendance_date')
                    ->whereColumn('attendance.id', 'attendance_records.attendance_id'),
            )
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AttendanceRecord $record): array => [
                'id' => $record->id,
                'studentName' => $this->studentName($record->student),
                'admissionNumber' => $record->student->admission_number,
                'date' => $record->attendance->attendance_date->toDateString(),
                'academicYear' => $record->attendance->academicYear->name,
                'class' => $record->attendance->section->schoolClass->name,
                'section' => $record->attendance->section->name,
                'status' => $record->status,
            ]);

        return Inertia::render('Portal/Attendance/Index', [
            'mode' => $isStudent ? 'student' : 'parent',
            'students' => $isParent ? $linkedStudents->map(fn (Student $student): array => [
                'id' => $student->id,
                'name' => $this->studentName($student),
            ])->values() : [],
            'selectedStudentId' => $selectedStudentId,
            'records' => $records,
        ]);
    }
}