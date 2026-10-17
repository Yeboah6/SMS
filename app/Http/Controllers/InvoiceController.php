<?php

namespace App\Http\Controllers;

use App\Concerns\DescribesInvoices;
use App\Concerns\ManagesFeeYears;
use App\Http\Requests\GenerateInvoicesRequest;
use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Services\FeeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Step 3 of the fee workflow. Invoices are generated from fee structures, never typed in by hand,
 * so there is no create/edit/delete: index, show and the generate pages cover the lifecycle.
 */
class InvoiceController extends Controller
{
    use DescribesInvoices, ManagesFeeYears;

    private const STATUSES = ['unpaid', 'partially_paid', 'paid', 'overdue'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeStaff($request);

        $years = $this->academicYears();
        $year = $this->pickYear($request, $years);
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $today = today()->toDateString();

        $yearQuery = Invoice::query()->where('academic_year_id', $year?->id ?? 0);

        $stats = (clone $yearQuery)->selectRaw(
            'COUNT(*) as total, COALESCE(SUM(total_amount), 0) as billed, COALESCE(SUM(paid_amount), 0) as paid, '
            .'COALESCE(SUM(CASE WHEN paid_amount >= total_amount THEN 1 ELSE 0 END), 0) as paid_count, '
            .'COALESCE(SUM(CASE WHEN paid_amount > 0 AND paid_amount < total_amount THEN 1 ELSE 0 END), 0) as partial_count, '
            .'COALESCE(SUM(CASE WHEN paid_amount <= 0 AND paid_amount < total_amount THEN 1 ELSE 0 END), 0) as unpaid_count, '
            .'COALESCE(SUM(CASE WHEN paid_amount < total_amount AND due_date < ? THEN 1 ELSE 0 END), 0) as overdue_count',
            [$today],
        )->first();

        $invoices = $this->applyStatus((clone $yearQuery), $status)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('invoice_number', 'like', "%{$search}%")
                ->orWhereHas('student', fn ($students) => $students
                    ->where('admission_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%"))))
            ->with(['student:id,first_name,middle_name,last_name,admission_number', 'academicYear:id,name'])
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Invoice $invoice): array => $this->invoiceSummary($invoice));

        $billed = (float) ($stats->billed ?? 0);
        $paid = (float) ($stats->paid ?? 0);

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'academicYears' => $this->yearOptions($years),
            'selectedAcademicYearId' => $year?->id,
            'filters' => ['search' => $search, 'status' => in_array($status, self::STATUSES, true) ? $status : ''],
            'counts' => [
                'all' => (int) ($stats->total ?? 0),
                'unpaid' => (int) ($stats->unpaid_count ?? 0),
                'partially_paid' => (int) ($stats->partial_count ?? 0),
                'paid' => (int) ($stats->paid_count ?? 0),
                'overdue' => (int) ($stats->overdue_count ?? 0),
            ],
            'summary' => ['billed' => $billed, 'paid' => $paid, 'outstanding' => max(0, $billed - $paid)],
            'can' => [
                'generate' => $user->can('invoices.create') && $this->isOpen($year),
                'export' => $user->can('reports.financial.export'),
            ],
        ]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $user = $request->user();
        $this->authorizeStaff($request);

        $invoice->load([
            'student:id,first_name,middle_name,last_name,admission_number',
            'academicYear:id,name,status',
            'items' => fn ($query) => $query->with('feeHead:id,name')->orderBy('id'),
            'payments' => fn ($query) => $query->orderByDesc('payment_date')->orderByDesc('id'),
        ]);
        $details = $this->invoiceDetails($invoice);

        return Inertia::render('Invoices/Show', [
            'invoice' => $details,
            'can' => [
                'recordPayment' => $user->can('payments.create')
                    && $this->isOpen($invoice->academicYear)
                    && $details['outstandingAmount'] > 0,
            ],
            'today' => today()->toDateString(),
        ]);
    }

    /** The "generate invoices" page, with a preview of what will happen. */
    public function generateForm(Request $request): Response
    {
        abort_unless($request->user()->can('invoices.create'), 403);

        $years = $this->academicYears()->filter(fn (AcademicYear $year): bool => $this->isOpen($year))->values();
        $year = $this->pickYear($request, $years);
        abort_unless($year, 404);

        $classes = $year->classes()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $class = $request->filled('class_id') ? $classes->firstWhere('id', $request->integer('class_id')) : null;
        abort_if($request->filled('class_id') && ! $class, 404);

        return Inertia::render('Invoices/Generate', [
            'academicYears' => $this->yearOptions($years),
            'selectedAcademicYearId' => $year->id,
            'classes' => $classes->map(fn (SchoolClass $schoolClass): array => ['id' => $schoolClass->id, 'name' => $schoolClass->name])->values(),
            'selectedClassId' => $class?->id,
            'preview' => $class ? $this->preview($year, $class) : null,
        ]);
    }

    public function generate(GenerateInvoicesRequest $request, FeeService $service): RedirectResponse
    {
        $result = $service->generateInvoices(
            $request->integer('academic_year_id'),
            $request->integer('class_id'),
            $request->user(),
        );

        return to_route('invoices.index', ['academic_year_id' => $request->integer('academic_year_id')])->with(
            'status',
            "{$result['invoices_created']} invoices created and {$result['items_created']} fee items added.",
        );
    }

    private function preview(AcademicYear $year, SchoolClass $class): array
    {
        $structures = FeeStructure::query()
            ->with('feeHead:id,name')
            ->where('academic_year_id', $year->id)
            ->where('class_id', $class->id)
            ->orderBy('fee_head_id')
            ->get();

        $enrolled = Enrolment::query()
            ->where('academic_year_id', $year->id)
            ->where('class_id', $class->id)
            ->where('status', 'active')
            ->pluck('student_id');
        $invoiced = $enrolled->isEmpty()
            ? 0
            : Invoice::query()->where('academic_year_id', $year->id)->whereIn('student_id', $enrolled)->count();

        return [
            'fees' => $structures->map(fn (FeeStructure $structure): array => [
                'name' => $structure->feeHead->name,
                'amount' => (float) $structure->amount,
                'dueDate' => $structure->due_date->toDateString(),
            ])->values(),
            'perStudentTotal' => (float) $structures->sum('amount'),
            'enrolledStudents' => $enrolled->count(),
            'alreadyInvoiced' => $invoiced,
            'withoutInvoice' => max(0, $enrolled->count() - $invoiced),
        ];
    }

    private function applyStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'paid' => $query->whereColumn('paid_amount', '>=', 'total_amount'),
            'partially_paid' => $query->where('paid_amount', '>', 0)->whereColumn('paid_amount', '<', 'total_amount'),
            'unpaid' => $query->where('paid_amount', '<=', 0)->whereColumn('paid_amount', '<', 'total_amount'),
            'overdue' => $query->whereColumn('paid_amount', '<', 'total_amount')->whereDate('due_date', '<', today()),
            default => $query,
        };
    }

    private function authorizeStaff(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->can('invoices.view') || $user->can('fees.view'), 403);
    }
}