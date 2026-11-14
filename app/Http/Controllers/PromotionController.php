<?php

namespace App\Http\Controllers;

use App\Enums\AcademicYearStatus;
use App\Http\Requests\ReversePromotionRequest;
use App\Http\Requests\StorePromotionRequest;
use App\Models\AcademicYear;
use App\Models\Promotion;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('students.promote'), 403);

        $years = AcademicYear::query()
            ->orderByDesc('start_date')
            ->get();
        $status = $request->string('status')->toString();
        $search = $request->string('search')->trim()->toString();
        $sourceYearId = $request->integer('source_year_id');
        $targetYearId = $request->integer('target_year_id');

        $promotions = Promotion::query()
            ->with([
                'student:id,first_name,middle_name,last_name,admission_number',
                'sourceAcademicYear:id,name',
                'targetAcademicYear:id,name',
                'processor:id,name',
            ])
            ->when($search !== '', fn ($query) => $query->whereHas('student', fn ($students) => $students
                ->where('admission_number', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('middle_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")))
            ->when($sourceYearId > 0, fn ($query) => $query->where('source_academic_year_id', $sourceYearId))
            ->when($targetYearId > 0, fn ($query) => $query->where('target_academic_year_id', $targetYearId))
            ->when($status === 'reversed', fn ($query) => $query->whereNotNull('reversed_at'))
            ->when($status === 'recorded', fn ($query) => $query->whereNull('reversed_at'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Promotion $promotion): array => [
                'id' => $promotion->id,
                'student' => trim(implode(' ', array_filter([
                    $promotion->student->first_name,
                    $promotion->student->middle_name,
                    $promotion->student->last_name,
                ]))),
                'admissionNumber' => $promotion->student->admission_number,
                'sourceYear' => $promotion->sourceAcademicYear->name,
                'targetYear' => $promotion->targetAcademicYear->name,
                'outcome' => $promotion->outcome,
                'reason' => $promotion->reason,
                'processedBy' => $promotion->processor->name,
                'processedAt' => $promotion->created_at->toDateString(),
                'reversedAt' => $promotion->reversed_at?->toDateString(),
            ]);

        return Inertia::render('Promotions/Index', [
            'promotions' => $promotions,
            'sourceYears' => $years->map(fn (AcademicYear $year): array => [
                'id' => $year->id,
                'name' => $year->name,
            ]),
            'targetYears' => $years->map(fn (AcademicYear $year): array => [
                'id' => $year->id,
                'name' => $year->name,
            ]),
            'filters' => [
                'search' => $search,
                'sourceYearId' => $sourceYearId ?: '',
                'targetYearId' => $targetYearId ?: '',
                'status' => $status,
            ],
        ]);
    }

    public function create(Request $request, PromotionService $service): Response
    {
        abort_unless($request->user()->can('students.promote'), 403);

        $sourceYears = AcademicYear::query()
            ->where('status', AcademicYearStatus::Completed)
            ->orderByDesc('start_date')
            ->get();
        $sourceYear = $sourceYears->firstWhere('id', $request->integer('source_year_id'));
        $targetYears = AcademicYear::query()
            ->whereIn('status', [AcademicYearStatus::Draft, AcademicYearStatus::Active])
            ->when($sourceYear, fn ($query) => $query->where('start_date', '>', $sourceYear->end_date))
            ->orderBy('start_date')
            ->get();
        $targetYear = $targetYears->firstWhere('id', $request->integer('target_year_id'));
        $classes = $targetYear
            ? SchoolClass::query()
                ->with('sections:id,class_id,name')
                ->where('academic_year_id', $targetYear->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
            : collect();
        $students = $sourceYear && $targetYear
            ? $service->eligibleStudents($sourceYear, $targetYear)
                ->paginate(50)
                ->withQueryString()
                ->through(fn (Student $student): array => [
                    'id' => $student->id,
                    'name' => trim(implode(' ', array_filter([
                        $student->first_name,
                        $student->middle_name,
                        $student->last_name,
                    ]))),
                    'admissionNumber' => $student->admission_number,
                    'class' => $student->enrolments->first()?->schoolClass->name,
                    'section' => $student->enrolments->first()?->section->name,
                ])
            : Student::query()->whereRaw('1 = 0')->paginate(50);

        return Inertia::render('Promotions/Create', [
            'sourceYears' => $sourceYears->map(fn (AcademicYear $year): array => [
                'id' => $year->id,
                'name' => $year->name,
            ]),
            'targetYears' => $targetYears->map(fn (AcademicYear $year): array => [
                'id' => $year->id,
                'name' => $year->name,
                'status' => $year->status->value,
            ]),
            'selectedSourceYearId' => $sourceYear?->id,
            'selectedTargetYearId' => $targetYear?->id,
            'classes' => $classes->map(fn (SchoolClass $schoolClass): array => [
                'id' => $schoolClass->id,
                'name' => $schoolClass->name,
                'sections' => $schoolClass->sections->map(fn ($section): array => [
                    'id' => $section->id,
                    'name' => $section->name,
                ])->values(),
            ]),
            'students' => $students,
            'canOverride' => $request->user()->can('students.promote.override'),
        ]);
    }

    public function show(Promotion $promotion, Request $request, PromotionService $service): Response
    {
        abort_unless($request->user()->can('students.promote'), 403);

        $promotion->load([
            'student:id,first_name,middle_name,last_name,admission_number',
            'sourceAcademicYear:id,name',
            'targetAcademicYear:id,name',
            'sourceClass:id,name',
            'sourceSection:id,name',
            'targetClass:id,name',
            'targetSection:id,name',
            'processor:id,name',
            'reverser:id,name',
            'sourceEnrolment:id,status,outcome,exit_date',
            'targetEnrolment:id,status,outcome,exit_date',
        ]);
        $blockReason = $service->reversalBlockReason($promotion);

        return Inertia::render('Promotions/Show', [
            'promotion' => [
                'id' => $promotion->id,
                'student' => trim(implode(' ', array_filter([
                    $promotion->student->first_name,
                    $promotion->student->middle_name,
                    $promotion->student->last_name,
                ]))),
                'studentId' => $promotion->student_id,
                'admissionNumber' => $promotion->student->admission_number,
                'sourceYear' => $promotion->sourceAcademicYear->name,
                'targetYear' => $promotion->targetAcademicYear->name,
                'sourceClass' => $promotion->sourceClass->name,
                'sourceSection' => $promotion->sourceSection->name,
                'targetClass' => $promotion->targetClass?->name,
                'targetSection' => $promotion->targetSection?->name,
                'outcome' => $promotion->outcome,
                'reason' => $promotion->reason,
                'processedBy' => $promotion->processor->name,
                'processedAt' => $promotion->created_at->toDateTimeString(),
                'reversedAt' => $promotion->reversed_at?->toDateTimeString(),
                'reversedBy' => $promotion->reverser?->name,
                'reversalReason' => $promotion->reversal_reason,
                'sourceStatus' => $promotion->sourceEnrolment?->status,
                'targetStatus' => $promotion->targetEnrolment?->status,
                'reversalBlockReason' => $blockReason,
            ],
            'canReverse' => $request->user()->can('students.promote.override') && $blockReason === null,
        ]);
    }

    public function store(
        StorePromotionRequest $request,
        PromotionService $service,
    ): RedirectResponse {
        $service->process($request->validated(), $request->user());

        return to_route('promotions.index')->with('status', 'Student promotion decisions recorded.');
    }

    public function reverse(
        ReversePromotionRequest $request,
        Promotion $promotion,
        PromotionService $service,
    ): RedirectResponse {
        $service->reverse($promotion, $request->validated('reason'), $request->user());

        return to_route('promotions.show', $promotion)->with('status', 'Promotion reversed and recorded in the student history.');
    }
}
