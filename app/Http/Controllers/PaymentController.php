<?php

namespace App\Http\Controllers;

use App\Concerns\DescribesInvoices;
use App\Concerns\ManagesFeeYears;
use App\Http\Requests\RecordFeePaymentRequest;
use App\Models\FeePayment;
use App\Models\Invoice;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Services\FeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Step 4 of the fee workflow. A payment is recorded against one invoice and produces a receipt.
 * Payments are never edited or deleted: that keeps receipts and balances trustworthy.
 */
class PaymentController extends Controller
{
    use DescribesInvoices, ManagesFeeYears;

    private const METHODS = ['cash', 'bank_transfer', 'card', 'mobile_money', 'cheque', 'other'];

    public function index(Request $request): Response
    {
        $this->authorizeStaff($request);

        $years = $this->academicYears();
        $allYears = $request->input('academic_year_id') === 'all';
        $year = $allYears ? null : $this->pickYear($request, $years);
        $search = $request->string('search')->trim()->toString();
        $method = $request->string('method')->toString();
        $from = $request->filled('from') ? $request->date('from')?->toDateString() : null;
        $to = $request->filled('to') ? $request->date('to')?->toDateString() : null;

        $query = FeePayment::query()
            ->when($year, fn ($query) => $query->whereHas('invoice', fn ($invoices) => $invoices->where('academic_year_id', $year->id)))
            ->when(in_array($method, self::METHODS, true), fn ($query) => $query->where('payment_method', $method))
            ->when($from, fn ($query) => $query->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('payment_date', '<=', $to))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('receipt_number', 'like', "%{$search}%")
                ->orWhere('transaction_reference', 'like', "%{$search}%")
                ->orWhereHas('invoice', fn ($invoices) => $invoices
                    ->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($students) => $students
                        ->where('admission_number', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")))));

        $collected = (float) (clone $query)->sum('amount');

        return Inertia::render('Payments/Index', [
            'payments' => $query
                ->with('invoice.student:id,first_name,middle_name,last_name,admission_number')
                ->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (FeePayment $payment): array => [
                    'id' => $payment->id,
                    'receiptNumber' => $payment->receipt_number,
                    'amount' => (float) $payment->amount,
                    'method' => $payment->payment_method,
                    'date' => $payment->payment_date->toDateString(),
                    'reference' => $payment->transaction_reference,
                    'invoiceId' => $payment->invoice_id,
                    'invoiceNumber' => $payment->invoice->invoice_number,
                    'studentName' => $this->fullName($payment->invoice->student),
                    'admissionNumber' => $payment->invoice->student->admission_number,
                ]),
            'academicYears' => $this->yearOptions($years),
            'selectedAcademicYearId' => $year?->id,
            'filters' => ['search' => $search, 'method' => $method, 'from' => $from ?? '', 'to' => $to ?? ''],
            'methods' => self::METHODS,
            'totalCollected' => $collected,
            'can' => ['export' => $request->user()->can('reports.financial.export')],
        ]);
    }

    /** Record a payment on an invoice, then show its receipt. */
    public function store(RecordFeePaymentRequest $request, Invoice $invoice, FeeService $service): RedirectResponse
    {
        $payment = $service->recordPayment($invoice, $request->validated(), $request->user());

        return to_route('payments.show', $payment)->with('status', "Payment recorded. Receipt {$payment->receipt_number}.");
    }

    /** The receipt. Staff can open any; students and parents only their own. */
    public function show(Request $request, FeePayment $payment): Response
    {
        $user = $request->user();
        $payment->load(['invoice.student:id,first_name,middle_name,last_name,admission_number', 'invoice.academicYear:id,name']);
        $invoice = $payment->invoice;

        $isPortal = $user->hasRole('Student') || $user->hasRole('Parent');
        if ($isPortal) {
            abort_unless($user->can('portal.fees.view'), 403);
            $studentIds = $user->hasRole('Student')
                ? Student::query()->where('user_id', $user->id)->get(['id'])->pluck('id')
                : Student::query()->forGuardianAccount($user)->get(['students.id'])->pluck('id');
            abort_unless($studentIds->contains($invoice->student_id), 404);
        } else {
            $this->authorizeStaff($request);
        }

        $total = (float) $invoice->total_amount;
        $paid = (float) $invoice->paid_amount;

        return Inertia::render('Payments/Show', [
            'receipt' => [
                'id' => $payment->id,
                'receiptNumber' => $payment->receipt_number,
                'amount' => (float) $payment->amount,
                'method' => $payment->payment_method,
                'date' => $payment->payment_date->toDateString(),
                'reference' => $payment->transaction_reference,
                'notes' => $payment->notes,
                'schoolName' => SchoolSetting::query()->where('singleton_key', 'primary')->value('school_name') ?? config('app.name'),
                'invoiceId' => $invoice->id,
                'invoiceNumber' => $invoice->invoice_number,
                'academicYear' => $invoice->academicYear->name,
                'studentId' => $invoice->student_id,
                'studentName' => $this->fullName($invoice->student),
                'admissionNumber' => $invoice->student->admission_number,
                'invoiceTotal' => $total,
                'invoicePaid' => $paid,
                'invoiceOutstanding' => max(0, $total - $paid),
            ],
            'isPortal' => $isPortal,
        ]);
    }

    private function authorizeStaff(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->can('fees.view') || $user->can('invoices.view'), 403);
    }
}