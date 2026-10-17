<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinancialReportRequest;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Services\FinancialReportService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportController extends Controller
{
    public function index(
        FinancialReportRequest $request,
        FinancialReportService $service,
    ): Response {
        $context = $this->reportContext($request);
        $year = $context['academicYear'];
        $summary = $year
            ? $service->summary($year, $context['fromDate'], $context['toDate'], $context['classId'])
            : ['paymentCount' => 0, 'collected' => 0.0];
        $payments = $year
            ? $service->payments($year, $context['fromDate'], $context['toDate'], $context['classId'])
                ->select([
                    'fee_payments.id',
                    'fee_payments.receipt_number',
                    'fee_payments.payment_date',
                    'fee_payments.payment_method',
                    'fee_payments.amount',
                    'invoices.invoice_number',
                    'students.admission_number',
                    'students.first_name',
                    'students.middle_name',
                    'students.last_name',
                    'classes.name AS class_name',
                ])
                ->orderByDesc('fee_payments.payment_date')
                ->orderByDesc('fee_payments.id')
                ->paginate(50)
                ->withQueryString()
                ->through(fn (object $payment): array => $this->paymentData($payment))
            : new LengthAwarePaginator([], 0, 50);

        return Inertia::render('Reports/Financial', [
            'academicYears' => $context['academicYears']->map(fn (AcademicYear $academicYear): array => [
                'id' => $academicYear->id,
                'name' => $academicYear->name,
            ]),
            'selectedAcademicYearId' => $year?->id,
            'selectedClassId' => $context['classId'],
            'classes' => $context['classes']->map(fn (SchoolClass $schoolClass): array => [
                'id' => $schoolClass->id,
                'name' => $schoolClass->name,
            ]),
            'fromDate' => $context['fromDate'],
            'toDate' => $context['toDate'],
            'summary' => $summary,
            'payments' => $payments,
            'canExport' => $request->user()->can('reports.financial.export'),
        ]);
    }

    public function export(
        FinancialReportRequest $request,
        FinancialReportService $service,
    ): StreamedResponse {
        abort_unless($request->user()->can('reports.financial.export'), 403);

        $context = $this->reportContext($request);
        $year = $context['academicYear'];
        abort_if($year === null, 404);

        $payments = $service->payments($year, $context['fromDate'], $context['toDate'], $context['classId'])
            ->select([
                'fee_payments.id',
                'fee_payments.receipt_number',
                'fee_payments.payment_date',
                'fee_payments.payment_method',
                'fee_payments.amount',
                'invoices.invoice_number',
                'students.admission_number',
                'students.first_name',
                'students.middle_name',
                'students.last_name',
                'classes.name AS class_name',
            ])
            ->orderByDesc('fee_payments.payment_date')
            ->orderByDesc('fee_payments.id')
            ->cursor();

        return response()->streamDownload(function () use ($payments): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open the financial report output stream.');
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'Receipt number',
                'Payment date',
                'Student',
                'Admission number',
                'Class',
                'Invoice number',
                'Payment method',
                'Amount',
            ], ',', '"', '', "\n");

            foreach ($payments as $payment) {
                $data = $this->paymentData($payment);
                fputcsv($stream, [
                    $this->safeCsvCell($data['receiptNumber']),
                    $data['paymentDate'],
                    $this->safeCsvCell($data['studentName']),
                    $this->safeCsvCell($data['admissionNumber']),
                    $this->safeCsvCell($data['className']),
                    $this->safeCsvCell($data['invoiceNumber']),
                    $this->safeCsvCell($data['paymentMethod']),
                    number_format($data['amount'], 2, '.', ''),
                ], ',', '"', '', "\n");
            }

            fclose($stream);
        }, 'fee-collection-report-'.$year->start_date->year.'-'.$year->end_date->year.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function outstanding(
        FinancialReportRequest $request,
        FinancialReportService $service,
    ): Response {
        $context = $this->outstandingReportContext($request);
        $year = $context['academicYear'];
        $summary = $year
            ? $service->outstandingSummary($year, $context['asOfDate'], $context['classId'])
            : ['invoiceCount' => 0, 'outstanding' => 0.0, 'overdueInvoiceCount' => 0, 'overdue' => 0.0];
        $invoices = $year
            ? $service->outstandingInvoices($year, $context['asOfDate'], $context['classId'])
                ->paginate(50)
                ->withQueryString()
                ->through(fn (object $invoice): array => $this->outstandingInvoiceData($invoice, $context['asOfDate']))
            : new LengthAwarePaginator([], 0, 50);

        return Inertia::render('Reports/OutstandingFees', [
            'academicYears' => $context['academicYears']->map(fn (AcademicYear $academicYear): array => [
                'id' => $academicYear->id,
                'name' => $academicYear->name,
            ]),
            'selectedAcademicYearId' => $year?->id,
            'selectedClassId' => $context['classId'],
            'classes' => $context['classes']->map(fn (SchoolClass $schoolClass): array => [
                'id' => $schoolClass->id,
                'name' => $schoolClass->name,
            ]),
            'asOfDate' => $context['asOfDate'],
            'summary' => $summary,
            'invoices' => $invoices,
            'canExport' => $request->user()->can('reports.financial.export'),
        ]);
    }

    public function exportOutstanding(
        FinancialReportRequest $request,
        FinancialReportService $service,
    ): StreamedResponse {
        abort_unless($request->user()->can('reports.financial.export'), 403);

        $context = $this->outstandingReportContext($request);
        $year = $context['academicYear'];
        abort_if($year === null, 404);
        $invoices = $service->outstandingInvoices($year, $context['asOfDate'], $context['classId'])->cursor();
        $asOfDate = $context['asOfDate'];
        $filename = 'outstanding-fees-report-'.$year->start_date->year.'-'.$year->end_date->year.'.csv';

        return response()->streamDownload(function () use ($invoices, $asOfDate): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open the outstanding fees report output stream.');
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'Invoice number',
                'Student',
                'Admission number',
                'Class',
                'Due date',
                'Days overdue',
                'Total amount',
                'Paid as of date',
                'Outstanding balance',
            ], ',', '"', '', "\n");

            foreach ($invoices as $invoice) {
                $data = $this->outstandingInvoiceData($invoice, $asOfDate);
                fputcsv($stream, [
                    $this->safeCsvCell($data['invoiceNumber']),
                    $this->safeCsvCell($data['studentName']),
                    $this->safeCsvCell($data['admissionNumber']),
                    $this->safeCsvCell($data['className']),
                    $data['dueDate'],
                    $data['daysOverdue'],
                    number_format($data['totalAmount'], 2, '.', ''),
                    number_format($data['paidAmount'], 2, '.', ''),
                    number_format($data['outstandingAmount'], 2, '.', ''),
                ], ',', '"', '', "\n");
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{
     *     academicYears: Collection<int, AcademicYear>,
     *     academicYear: ?AcademicYear,
     *     classes: Collection<int, SchoolClass>,
     *     classId: ?int,
     *     fromDate: string,
     *     toDate: string
     * }
     */
    private function reportContext(FinancialReportRequest $request): array
    {
        $filters = $request->validated();
        $academicYears = AcademicYear::query()->orderByDesc('start_date')->get();
        $academicYear = isset($filters['academic_year_id'])
            ? $academicYears->firstWhere('id', (int) $filters['academic_year_id'])
            : AcademicYear::active()->first();
        $classes = $academicYear
            ? $academicYear->classes()->orderBy('sort_order')->orderBy('name')->get()
            : new Collection;
        $classId = isset($filters['class_id']) ? (int) $filters['class_id'] : null;

        if ($classId !== null && ! $classes->contains('id', $classId)) {
            abort(404);
        }

        $fromDate = $filters['from_date'] ?? $academicYear?->start_date->toDateString() ?? today()->toDateString();
        $toDate = $filters['to_date'] ?? $academicYear?->end_date->toDateString() ?? today()->toDateString();

        return [
            'academicYears' => $academicYears,
            'academicYear' => $academicYear,
            'classes' => $classes,
            'classId' => $classId,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ];
    }

    /**
     * @return array{
     *     academicYears: Collection<int, AcademicYear>,
     *     academicYear: ?AcademicYear,
     *     classes: Collection<int, SchoolClass>,
     *     classId: ?int,
     *     asOfDate: string
     * }
     */
    private function outstandingReportContext(FinancialReportRequest $request): array
    {
        $filters = $request->validated();
        $academicYears = AcademicYear::query()->orderByDesc('start_date')->get();
        $academicYear = isset($filters['academic_year_id'])
            ? $academicYears->firstWhere('id', (int) $filters['academic_year_id'])
            : AcademicYear::active()->first();
        $classes = $academicYear
            ? $academicYear->classes()->orderBy('sort_order')->orderBy('name')->get()
            : new Collection;
        $classId = isset($filters['class_id']) ? (int) $filters['class_id'] : null;

        if ($classId !== null && ! $classes->contains('id', $classId)) {
            abort(404);
        }

        return [
            'academicYears' => $academicYears,
            'academicYear' => $academicYear,
            'classes' => $classes,
            'classId' => $classId,
            'asOfDate' => $filters['as_of_date'] ?? today()->toDateString(),
        ];
    }

    /**
     * @return array{
     *     receiptNumber: string,
     *     paymentDate: string,
     *     studentName: string,
     *     admissionNumber: string,
     *     className: string,
     *     invoiceNumber: string,
     *     paymentMethod: string,
     *     amount: float
     * }
     */
    private function paymentData(object $payment): array
    {
        return [
            'receiptNumber' => $payment->receipt_number,
            'paymentDate' => Carbon::parse($payment->payment_date)->toDateString(),
            'studentName' => trim(implode(' ', array_filter([
                $payment->first_name,
                $payment->middle_name,
                $payment->last_name,
            ], fn (?string $name): bool => $name !== null && $name !== ''))),
            'admissionNumber' => $payment->admission_number,
            'className' => $payment->class_name,
            'invoiceNumber' => $payment->invoice_number,
            'paymentMethod' => $payment->payment_method,
            'amount' => (float) $payment->amount,
        ];
    }

    /**
     * @return array{
     *     invoiceNumber: string,
     *     studentName: string,
     *     admissionNumber: string,
     *     className: string,
     *     dueDate: string,
     *     daysOverdue: int,
     *     totalAmount: float,
     *     paidAmount: float,
     *     outstandingAmount: float
     * }
     */
    private function outstandingInvoiceData(object $invoice, string $asOfDate): array
    {
        $dueDate = Carbon::parse($invoice->due_date);
        $asOf = Carbon::parse($asOfDate);

        return [
            'invoiceNumber' => $invoice->invoice_number,
            'studentName' => trim(implode(' ', array_filter([
                $invoice->first_name,
                $invoice->middle_name,
                $invoice->last_name,
            ], fn (?string $name): bool => $name !== null && $name !== ''))),
            'admissionNumber' => $invoice->admission_number,
            'className' => $invoice->class_name ?? 'Unassigned',
            'dueDate' => $dueDate->toDateString(),
            'daysOverdue' => $dueDate->lt($asOf) ? (int) $dueDate->diffInDays($asOf) : 0,
            'totalAmount' => (float) $invoice->total_amount,
            'paidAmount' => (float) $invoice->paid_amount,
            'outstandingAmount' => (float) $invoice->outstanding_amount,
        ];
    }

    private function safeCsvCell(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1
            ? "'".$value
            : $value;
    }
}
