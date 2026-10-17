<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\MarkExternalFulfilmentRequest;
use App\Http\Requests\StoreRequirementRequest;
use App\Models\AcademicYear;
use App\Models\ClassSupplyRequirement;
use App\Models\InventoryItem;
use App\Models\Student;
use App\Services\SupplyRequirementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RequirementController extends Controller
{
    public function index(Request $request, SupplyRequirementService $service): Response
    {
        $this->authorize('viewAny', ClassSupplyRequirement::class);
        $user = $request->user();

        $years = AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'status']);
        $year = $request->filled('academic_year_id') ? $years->firstWhere('id', $request->integer('academic_year_id')) : ($years->firstWhere('id', AcademicYear::active()->value('id')) ?? $years->first());
        abort_if($request->filled('academic_year_id') && ! $year, 404);

        $classes = $year ? $year->classes()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']) : collect();
        $counts = $year ? ClassSupplyRequirement::query()->where('academic_year_id', $year->id)->selectRaw('class_id, COUNT(*) as total')->groupBy('class_id')->pluck('total', 'class_id') : collect();
        $class = $request->filled('class_id') ? $classes->firstWhere('id', $request->integer('class_id')) : null;
        abort_if($request->filled('class_id') && ! $class, 404);

        return Inertia::render('Supplies/Requirements/Index', [
            'academicYears' => $years->map(fn ($item): array => ['id' => $item->id, 'name' => $item->name, 'status' => $item->status->value])->values(),
            'selectedAcademicYearId' => $year?->id,
            'classes' => $classes->map(fn ($item): array => ['id' => $item->id, 'name' => $item->name, 'requirements' => (int) ($counts[$item->id] ?? 0)])->values(),
            'selectedClassId' => $class?->id,
            'completion' => $year && $class ? $service->completion($year, $class) : null,
            'can' => [
                'manage' => $user->can('manage', ClassSupplyRequirement::class),
                'external' => (bool) config('supplies.allow_external_fulfilment') && $user->can('supplies.requirements.fulfil_external'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('manage', ClassSupplyRequirement::class);

        $year = AcademicYear::query()->findOrFail($request->integer('academic_year_id') ?: AcademicYear::active()->value('id'));

        return Inertia::render('Supplies/Requirements/Create', [
            'academicYear' => ['id' => $year->id, 'name' => $year->name],
            'classes' => $year->classes()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'terms' => $year->terms()->orderBy('start_date')->get(['id', 'name']),
            'items' => $this->items(),
            'selectedClassId' => $request->integer('class_id') ?: null,
        ]);
    }

    public function store(StoreRequirementRequest $request, SupplyRequirementService $service): RedirectResponse
    {
        $requirement = $service->create($request->validated(), $request->user());

        return to_route('supplies.requirements.index', ['academic_year_id' => $requirement->academic_year_id, 'class_id' => $requirement->class_id])->with('status', 'Requirement added.');
    }

    public function edit(ClassSupplyRequirement $requirement): Response
    {
        $this->authorize('manage', $requirement);
        $requirement->load(['schoolClass:id,name', 'academicYear:id,name']);

        return Inertia::render('Supplies/Requirements/Edit', [
            'requirement' => [
                'id' => $requirement->id,
                'academicYearId' => $requirement->academic_year_id,
                'academicYear' => $requirement->academicYear->name,
                'classId' => $requirement->class_id,
                'className' => $requirement->schoolClass->name,
                'termId' => $requirement->term_id,
                'itemId' => $requirement->item_id,
                'quantity' => $requirement->quantity_per_student,
                'isRequired' => $requirement->is_required,
                'notes' => $requirement->notes,
            ],
            'terms' => $requirement->academicYear->terms()->orderBy('start_date')->get(['id', 'name']),
            'items' => $this->items(),
        ]);
    }

    public function update(StoreRequirementRequest $request, ClassSupplyRequirement $requirement, SupplyRequirementService $service): RedirectResponse
    {
        $service->update($requirement, $request->validated(), $request->user());

        return to_route('supplies.requirements.index', ['academic_year_id' => $requirement->academic_year_id, 'class_id' => $requirement->class_id])->with('status', 'Requirement updated.');
    }

    public function destroy(Request $request, ClassSupplyRequirement $requirement, SupplyRequirementService $service): RedirectResponse
    {
        $this->authorize('manage', $requirement);
        $service->delete($requirement, $request->user());

        return to_route('supplies.requirements.index', ['academic_year_id' => $requirement->academic_year_id, 'class_id' => $requirement->class_id])->with('status', 'Requirement removed.');
    }

    public function markExternal(MarkExternalFulfilmentRequest $request, ClassSupplyRequirement $requirement, SupplyRequirementService $service): RedirectResponse
    {
        $service->markExternal($requirement, Student::query()->findOrFail($request->validated('student_id')), $request->validated('notes'), $request->user());

        return back()->with('status', 'Marked as bought elsewhere.');
    }

    public function unmarkExternal(MarkExternalFulfilmentRequest $request, ClassSupplyRequirement $requirement, SupplyRequirementService $service): RedirectResponse
    {
        $service->unmarkExternal($requirement, Student::query()->findOrFail($request->validated('student_id')), $request->user());

        return back()->with('status', 'External marking removed.');
    }

    private function items()
    {
        return InventoryItem::query()->active()->orderBy('name')->get(['id', 'sku', 'name', 'unit', 'selling_price'])
            ->map(fn (InventoryItem $item): array => ['id' => $item->id, 'sku' => $item->sku, 'name' => $item->name, 'unit' => $item->unit, 'price' => (float) $item->selling_price]);
    }
}