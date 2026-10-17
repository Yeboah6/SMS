<?php

namespace App\Http\Controllers;

use App\Enums\AcademicYearStatus;
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
    public function index(Request $request, PromotionService $service): Response
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
        $recentPromotions = Promotion::query()
            ->with(['student:id,first_name,middle_name,last_name,admission_number', 'sourceAcademicYear:id,name', 'targetAcademicYear:id,name', 'processor:id,name'])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (Promotion $promotion): array => [
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
            ]);

        return Inertia::render('Promotions/Index', [
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
            'recentPromotions' => $recentPromotions,
            'canOverride' => $request->user()->can('students.promote.override'),
        ]);
    }

    public function store(
        StorePromotionRequest $request,
        PromotionService $service,
    ): RedirectResponse {
        $service->process($request->validated(), $request->user());

        return back()->with('status', 'Student promotion decisions recorded.');
    }
}
