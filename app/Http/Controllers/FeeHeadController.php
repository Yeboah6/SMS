<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeeHeadRequest;
use App\Models\AcademicYear;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Services\FeeService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Step 1 of the fee workflow: the catalogue of charge types. */
class FeeHeadController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('fee_heads.view'), 403);

        $search = $request->string('search')->trim()->toString();
        $usage = FeeStructure::query()
            ->select('fee_head_id', DB::raw('count(*) as total'))
            ->groupBy('fee_head_id')
            ->pluck('total', 'fee_head_id');

        return Inertia::render('FeeHeads/Index', [
            'feeHeads' => FeeHead::query()
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")))
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'description', 'is_active'])
                ->map(fn (FeeHead $head): array => [
                    'id' => $head->id,
                    'code' => $head->code,
                    'name' => $head->name,
                    'description' => $head->description,
                    'isActive' => (bool) $head->is_active,
                    'structuresCount' => (int) ($usage[$head->id] ?? 0),
                ])->values(),
            'search' => $search,
            'can' => [
                'create' => $user->can('fee_heads.create'),
                'update' => $user->can('fee_heads.update'),
                'delete' => $user->can('fee_heads.delete'),
                'import' => $user->can('fee_heads.create') && $user->can('fee_heads.update'),
                'export' => $user->can('fee_heads.view'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('fee_heads.create'), 403);

        return Inertia::render('FeeHeads/Create');
    }

    public function store(StoreFeeHeadRequest $request, FeeService $service): RedirectResponse
    {
        $service->createFeeHead($request->validated(), $request->user());

        return to_route('fee-heads.index')->with('status', 'Fee head created.');
    }

    public function show(Request $request, FeeHead $feeHead): Response
    {
        $user = $request->user();
        abort_unless($user->can('fee_heads.view'), 403);

        $years = AcademicYear::query()->pluck('name', 'id');

        return Inertia::render('FeeHeads/Show', [
            'feeHead' => $this->payload($feeHead),
            'structures' => FeeStructure::query()
                ->with('schoolClass:id,name')
                ->where('fee_head_id', $feeHead->id)
                ->orderByDesc('academic_year_id')
                ->get()
                ->map(fn (FeeStructure $structure): array => [
                    'id' => $structure->id,
                    'academicYearId' => $structure->academic_year_id,
                    'academicYear' => $years[$structure->academic_year_id] ?? '',
                    'className' => $structure->schoolClass->name,
                    'amount' => (float) $structure->amount,
                    'dueDate' => $structure->due_date->toDateString(),
                ])->values(),
            'can' => [
                'update' => $user->can('fee_heads.update'),
                'delete' => $user->can('fee_heads.delete'),
            ],
        ]);
    }

    public function edit(Request $request, FeeHead $feeHead): Response
    {
        abort_unless($request->user()->can('fee_heads.update'), 403);

        return Inertia::render('FeeHeads/Edit', ['feeHead' => $this->payload($feeHead)]);
    }

    public function update(StoreFeeHeadRequest $request, FeeHead $feeHead, FeeService $service): RedirectResponse
    {
        $service->updateFeeHead($feeHead, $request->validated(), $request->user());

        return to_route('fee-heads.show', $feeHead)->with('status', 'Fee head updated.');
    }

    /** A fee head that was ever used is deactivated, not deleted. */
    public function destroy(Request $request, FeeHead $feeHead): RedirectResponse
    {
        abort_unless($request->user()->can('fee_heads.delete'), 403);

        $inUse = ValidationException::withMessages([
            'feeHead' => 'This fee head is used by fee structures or invoices. Deactivate it instead of deleting it.',
        ]);

        if (FeeStructure::query()->where('fee_head_id', $feeHead->id)->exists()) {
            throw $inUse;
        }

        try {
            $feeHead->delete();
        } catch (QueryException) {
            throw $inUse;
        }

        return to_route('fee-heads.index')->with('status', 'Fee head deleted.');
    }

    private function payload(FeeHead $head): array
    {
        return [
            'id' => $head->id,
            'code' => $head->code,
            'name' => $head->name,
            'description' => $head->description,
            'isActive' => (bool) $head->is_active,
        ];
    }
}