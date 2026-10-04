<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAcademicYearRequest;
use App\Http\Requests\StoreTermRequest;
use App\Http\Requests\UpdateAcademicYearRequest;
use App\Models\AcademicYear;
use App\Services\AcademicYearService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AcademicYear::class);

        return Inertia::render('AcademicYears/Index', [
            'years' => AcademicYear::query()
                ->withCount('terms')
                ->orderByDesc('start_date')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (AcademicYear $year): array => $this->yearSummary($year)),
            'can' => [
                'create' => $request->user()->can('academic_years.create'),
                'update' => $request->user()->can('academic_years.update'),
                'delete' => $request->user()->can('academic_years.delete'),
                'activate' => $request->user()->can('academic_years.activate'),
                'complete' => $request->user()->can('academic_years.complete'),
                'lock' => $request->user()->can('academic_years.lock'),
            ],
        ]);
    }

    public function show(AcademicYear $academicYear): Response
    {
        $this->authorize('view', $academicYear);

        return Inertia::render('AcademicYears/Show', [
            'year' => [
                ...$this->yearSummary($academicYear->load('terms')),
                'terms' => $academicYear->terms->map(fn ($term): array => [
                    'id' => $term->id,
                    'name' => $term->name,
                    'startDate' => $term->start_date->toDateString(),
                    'endDate' => $term->end_date->toDateString(),
                ]),
            ],
            'can' => [
                'update' => request()->user()->can('academic_years.update'),
                'delete' => request()->user()->can('academic_years.delete'),
                'activate' => request()->user()->can('academic_years.activate'),
                'complete' => request()->user()->can('academic_years.complete'),
                'lock' => request()->user()->can('academic_years.lock'),
            ],
        ]);
    }

    public function store(StoreAcademicYearRequest $request, AcademicYearService $service): RedirectResponse
    {
        $year = $service->create($request->validated());

        return to_route('academic-years.show', $year)->with('status', 'Academic year created as a draft.');
    }

    public function update(
        UpdateAcademicYearRequest $request,
        AcademicYear $academicYear,
        AcademicYearService $service,
    ): RedirectResponse {
        $this->authorize('update', $academicYear);
        $service->update($academicYear, $request->validated());

        return back()->with('status', 'Academic year updated.');
    }

    public function activate(AcademicYear $academicYear, AcademicYearService $service): RedirectResponse
    {
        $this->authorize('activate', $academicYear);
        $service->activate($academicYear);

        return back()->with('status', 'Academic year activated.');
    }

    public function complete(AcademicYear $academicYear, AcademicYearService $service): RedirectResponse
    {
        $this->authorize('complete', $academicYear);
        $service->complete($academicYear);

        return back()->with('status', 'Academic year completed.');
    }

    public function lock(AcademicYear $academicYear, AcademicYearService $service): RedirectResponse
    {
        $this->authorize('lock', $academicYear);
        $service->lock($academicYear);

        return back()->with('status', 'Academic year locked and set to read-only.');
    }

    public function destroy(AcademicYear $academicYear, AcademicYearService $service): RedirectResponse
    {
        $this->authorize('delete', $academicYear);
        $service->delete($academicYear);

        return to_route('academic-years.index')->with('status', 'Draft academic year deleted.');
    }

    public function storeTerm(
        StoreTermRequest $request,
        AcademicYear $academicYear,
        AcademicYearService $service,
    ): RedirectResponse {
        $this->authorize('update', $academicYear);
        $service->createTerm($academicYear, $request->validated());

        return back()->with('status', 'Academic term created.');
    }

    /**
     * @return array{id: int, name: string, status: string, startDate: string, endDate: string, termCount: int}
     */
    private function yearSummary(AcademicYear $year): array
    {
        return [
            'id' => $year->id,
            'name' => $year->name,
            'status' => $year->status->value,
            'startDate' => $year->start_date->toDateString(),
            'endDate' => $year->end_date->toDateString(),
            'termCount' => $year->terms_count ?? $year->terms->count(),
        ];
    }
}
