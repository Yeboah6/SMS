<?php

namespace App\Http\Controllers;

use App\Concerns\DescribesInvoices;
use App\Concerns\ManagesFeeYears;
use App\Models\AcademicYear;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /fees is the entry point.
 *  - Staff see the workflow overview: fee heads, fee structures, invoices, payments.
 *  - Students and parents see their own invoices and receipts.
 */
class FeeController extends Controller
{
    use DescribesInvoices, ManagesFeeYears;

    public function index(Request $request): Response
    {
        $user = $request->user();

        if ($user->hasRole('Student') || $user->hasRole('Parent')) {
            return $this->portalIndex($request);
        }

        abort_unless($user->can('fees.view') || $user->can('invoices.view'), 403);

        return $this->overview($request);
    }

    private function overview(Request $request): Response
    {
        $user = $request->user();
        $years = $this->academicYears();
        $year = $this->pickYear($request, $years);

        $activeHeads = FeeHead::query()->where('is_active', true)->count();
        $classCount = $year ? $year->classes()->count() : 0;
        $pricedClasses = $year
            ? FeeStructure::query()->where('academic_year_id', $year->id)->distinct()->count('class_id')
            : 0;
        $stats = Invoice::query()
            ->where('academic_year_id', $year?->id ?? 0)
            ->selectRaw(
                'COUNT(*) as invoices, COALESCE(SUM(total_amount), 0) as billed, COALESCE(SUM(paid_amount), 0) as paid, '
                .'COALESCE(SUM(CASE WHEN paid_amount < total_amount AND due_date < ? THEN 1 ELSE 0 END), 0) as overdue',
                [today()->toDateString()],
            )
            ->first();

        $invoiceCount = (int) ($stats->invoices ?? 0);
        $billed = (float) ($stats->billed ?? 0);
        $paid = (float) ($stats->paid ?? 0);

        $steps = [
            [
                'key' => 'heads',
                'title' => 'Fee heads',
                'description' => 'The kinds of charge the school makes, such as tuition, boarding or exam fees. Set up once and reused every year.',
                'metric' => "{$activeHeads} active",
                'done' => $activeHeads > 0,
                'href' => '/fee-heads',
                'action' => $activeHeads > 0 ? 'Manage fee heads' : 'Add the first fee head',
            ],
            [
                'key' => 'structures',
                'title' => 'Fee structures',
                'description' => 'The amount and due date of each fee head for each class in the selected year.',
                'metric' => $classCount > 0 ? "{$pricedClasses} of {$classCount} classes priced" : 'No classes in this year',
                'done' => $classCount > 0 && $pricedClasses >= $classCount,
                'href' => '/fee-structures'.($year ? "?academic_year_id={$year->id}" : ''),
                'action' => $pricedClasses > 0 ? 'Review fee structures' : 'Price the first class',
            ],
            [
                'key' => 'invoices',
                'title' => 'Invoices',
                'description' => 'Generate an invoice for every enrolled student from their class fee structure. Safe to repeat: nothing is duplicated.',
                'metric' => "{$invoiceCount} issued",
                'done' => $invoiceCount > 0,
                'href' => $invoiceCount > 0
                    ? '/invoices'.($year ? "?academic_year_id={$year->id}" : '')
                    : '/invoices/generate'.($year ? "?academic_year_id={$year->id}" : ''),
                'action' => $invoiceCount > 0 ? 'Open the invoice register' : 'Generate the first invoices',
            ],
            [
                'key' => 'payments',
                'title' => 'Payments',
                'description' => 'Record each payment on its invoice. A receipt is issued automatically and the balance updates.',
                'metric' => $invoiceCount > 0 ? number_format($billed > 0 ? $paid / $billed * 100 : 0).'% of billed collected' : 'Nothing billed yet',
                'done' => $invoiceCount > 0 && $billed > 0 && $paid >= $billed,
                'href' => '/payments'.($year ? "?academic_year_id={$year->id}" : ''),
                'action' => 'Open the payments register',
            ],
        ];

        $current = collect($steps)->first(fn (array $step): bool => ! $step['done'])['key'] ?? null;

        return Inertia::render('Fees/Index', [
            'academicYears' => $this->yearOptions($years),
            'selectedAcademicYear' => $year ? ['id' => $year->id, 'name' => $year->name, 'status' => $year->status->value] : null,
            'steps' => collect($steps)->map(fn (array $step): array => $step + ['current' => $step['key'] === $current])->all(),
            'summary' => ['billed' => $billed, 'paid' => $paid, 'outstanding' => max(0, $billed - $paid)],
            'overdueCount' => (int) ($stats->overdue ?? 0),
            'can' => [
                'recordPayments' => $user->can('payments.create') && $this->isOpen($year),
                'generateInvoices' => $user->can('invoices.create') && $this->isOpen($year),
            ],
        ]);
    }

    private function portalIndex(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('portal.fees.view'), 403);

        $isParent = ! $user->hasRole('Student');
        $students = $isParent
            ? Student::query()->forGuardianAccount($user)->orderBy('first_name')->orderBy('last_name')->get(['students.id', 'first_name', 'last_name'])
            : Student::query()->where('user_id', $user->id)->get(['id', 'first_name', 'last_name']);
        $studentIds = $students->pluck('id');

        $selectedStudentId = null;
        if ($isParent && $request->filled('student_id')) {
            $selectedStudentId = $request->integer('student_id');
            abort_unless($students->contains('id', $selectedStudentId), 404);
            $studentIds = collect([$selectedStudentId]);
        }

        $years = AcademicYear::query()
            ->whereIn('id', Invoice::query()->whereIn('student_id', $studentIds)->select('academic_year_id'))
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'status']);

        $showAllYears = $request->input('academic_year_id') === 'all';
        $selectedYearId = $showAllYears
            ? null
            : ($request->filled('academic_year_id') ? $request->integer('academic_year_id') : $years->first()?->id);
        $selectedYear = $selectedYearId ? $years->firstWhere('id', $selectedYearId) : null;
        abort_if($selectedYearId && ! $selectedYear, 404);

        $query = Invoice::query()
            ->whereIn('student_id', $studentIds)
            ->when($selectedYear, fn ($query) => $query->where('academic_year_id', $selectedYear->id))
            ->with([
                'student:id,first_name,middle_name,last_name,admission_number',
                'academicYear:id,name',
                'items' => fn ($query) => $query->with('feeHead:id,name')->orderBy('id'),
                'payments' => fn ($query) => $query->orderByDesc('payment_date')->orderByDesc('id'),
            ])
            ->orderByDesc('issued_at')
            ->orderByDesc('id');

        $summary = (clone $query)->reorder()
            ->selectRaw('COALESCE(SUM(total_amount), 0) as billed, COALESCE(SUM(paid_amount), 0) as paid')
            ->first();
        $billed = (float) ($summary->billed ?? 0);
        $paid = (float) ($summary->paid ?? 0);

        return Inertia::render('Fees/MyFees', [
            'mode' => $isParent ? 'parent' : 'student',
            'invoices' => $query->paginate(20)->withQueryString()->through(fn (Invoice $invoice): array => $this->invoiceDetails($invoice)),
            'students' => $isParent
                ? $students->map(fn (Student $student): array => [
                    'id' => $student->id,
                    'name' => trim($student->first_name.' '.$student->last_name),
                ])->values()
                : [],
            'selectedStudentId' => $selectedStudentId,
            'academicYears' => $this->yearOptions($years),
            'selectedAcademicYearId' => $selectedYear?->id,
            'summary' => ['billed' => $billed, 'paid' => $paid, 'outstanding' => max(0, $billed - $paid)],
        ]);
    }
}