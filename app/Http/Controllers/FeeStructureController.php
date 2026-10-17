<?php

namespace App\Http\Controllers;

use App\Enums\AcademicYearStatus;
use App\Concerns\ManagesFeeYears;
use App\Http\Requests\StoreFeeStructureRequest;
use App\Models\AcademicYear;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Services\FeeService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Step 2 of the fee workflow: what each class pays in a given year. */
class FeeStructureController extends Controller
{
    use ManagesFeeYears;

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('fee_structures.view'), 403);

        $years = $this->academicYears();
        $year = $this->pickYear($request, $years);
        $open = $this->isOpen($year);

        $byClass = $year
            ? FeeStructure::query()
                ->with('feeHead:id,name')
                ->where('academic_year_id', $year->id)
                ->orderBy('fee_head_id')
                ->get()
                ->groupBy('class_id')
            : collect();

        return Inertia::render('FeeStructures/Index', [
            'academicYears' => $this->yearOptions($years),
            'selectedAcademicYear' => $year ? ['id' => $year->id, 'name' => $year->name, 'status' => $year->status->value] : null,
            'classes' => $year
                ? $year->classes()->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                    ->map(function (SchoolClass $schoolClass) use ($byClass): array {
                        $rows = $byClass->get($schoolClass->id, collect());

                        return [
                            'id' => $schoolClass->id,
                            'name' => $schoolClass->name,
                            'total' => (float) $rows->sum('amount'),
                            'structures' => $rows->map(fn (FeeStructure $structure): array => [
                                'id' => $structure->id,
                                'feeHeadName' => $structure->feeHead->name,
                                'amount' => (float) $structure->amount,
                                'dueDate' => $structure->due_date->toDateString(),
                            ])->values(),
                        ];
                    })->values()
                : [],
            'can' => [
                'create' => $user->can('fee_structures.create') && $open,
                'update' => $user->can('fee_structures.update') && $open,
                'delete' => $user->can('fee_structures.delete') && $open,
                'import' => $user->can('fee_structures.create') && $user->can('fee_structures.update'),
                'export' => $user->can('fee_structures.view') && $year !== null,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('fee_structures.create'), 403);

        $years = AcademicYear::query()
            ->where('status', '!=', AcademicYearStatus::Locked->value)
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'status']);
        $year = $this->pickYear($request, $years);
        abort_unless($year, 404);

        return Inertia::render('FeeStructures/Create', [
            'academicYear' => ['id' => $year->id, 'name' => $year->name],
            'classes' => $year->classes()->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                ->map(fn (SchoolClass $schoolClass): array => ['id' => $schoolClass->id, 'name' => $schoolClass->name])->values(),
            'feeHeads' => FeeHead::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'selectedClassId' => $request->integer('class_id') ?: null,
        ]);
    }

    public function store(StoreFeeStructureRequest $request, FeeService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->createStructure($data, $request->user());

        return to_route('fee-structures.index', ['academic_year_id' => $data['academic_year_id']])
            ->with('status', 'Fee structure created.');
    }

    public function edit(Request $request, FeeStructure $feeStructure): Response
    {
        abort_unless($request->user()->can('fee_structures.update'), 403);
        $feeStructure->load(['schoolClass:id,name', 'feeHead:id,name']);
        $year = AcademicYear::query()->findOrFail($feeStructure->academic_year_id);
        abort_unless($this->isOpen($year), 403);

        return Inertia::render('FeeStructures/Edit', [
            'academicYear' => ['id' => $year->id, 'name' => $year->name],
            'feeStructure' => [
                'id' => $feeStructure->id,
                'classId' => $feeStructure->class_id,
                'className' => $feeStructure->schoolClass->name,
                'feeHeadId' => $feeStructure->fee_head_id,
                'feeHeadName' => $feeStructure->feeHead->name,
                'amount' => (float) $feeStructure->amount,
                'dueDate' => $feeStructure->due_date->toDateString(),
            ],
        ]);
    }

    public function update(StoreFeeStructureRequest $request, FeeStructure $feeStructure, FeeService $service): RedirectResponse
    {
        $service->updateStructure($feeStructure, $request->validated(), $request->user());

        return to_route('fee-structures.index', ['academic_year_id' => $feeStructure->academic_year_id])
            ->with('status', 'Fee structure updated. Existing invoices remain unchanged.');
    }

    public function destroy(Request $request, FeeStructure $feeStructure): RedirectResponse
    {
        abort_unless($request->user()->can('fee_structures.delete'), 403);
        $yearId = $feeStructure->academic_year_id;
        abort_unless($this->isOpen(AcademicYear::query()->find($yearId)), 403);

        try {
            $feeStructure->delete();
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'feeStructure' => 'This fee structure is referenced by invoices and cannot be deleted.',
            ]);
        }

        return to_route('fee-structures.index', ['academic_year_id' => $yearId])
            ->with('status', 'Fee structure deleted. Existing invoices remain unchanged.');
    }
}