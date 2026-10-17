<?php

namespace App\Services;

use App\Models\AcademicYear;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /**
     * @return array{paymentCount: int, collected: float}
     */
    public function summary(
        AcademicYear $year,
        string $fromDate,
        string $toDate,
        ?int $classId,
    ): array {
        $summary = $this->payments($year, $fromDate, $toDate, $classId)
            ->selectRaw('COUNT(fee_payments.id) AS payment_count, COALESCE(SUM(fee_payments.amount), 0) AS collected')
            ->first();

        return [
            'paymentCount' => (int) ($summary->payment_count ?? 0),
            'collected' => (float) ($summary->collected ?? 0),
        ];
    }

    public function payments(
        AcademicYear $year,
        string $fromDate,
        string $toDate,
        ?int $classId,
    ): Builder {
        $toDateExclusive = Carbon::parse($toDate)->addDay()->toDateString();

        return DB::table('fee_payments')
            ->join('invoices', 'invoices.id', '=', 'fee_payments.invoice_id')
            ->join('students', 'students.id', '=', 'invoices.student_id')
            ->join('enrolments', function ($join): void {
                $join->on('enrolments.student_id', '=', 'invoices.student_id')
                    ->on('enrolments.academic_year_id', '=', 'invoices.academic_year_id');
            })
            ->join('classes', 'classes.id', '=', 'enrolments.class_id')
            ->where('invoices.academic_year_id', $year->id)
            ->where('fee_payments.payment_date', '>=', $fromDate)
            ->where('fee_payments.payment_date', '<', $toDateExclusive)
            ->when($classId !== null, fn (Builder $query): Builder => $query
                ->where('enrolments.class_id', $classId));
    }

    /**
     * @return array{
     *     invoiceCount: int,
     *     outstanding: float,
     *     overdueInvoiceCount: int,
     *     overdue: float
     * }
     */
    public function outstandingSummary(AcademicYear $year, string $asOfDate, ?int $classId): array
    {
        $summary = DB::query()
            ->fromSub($this->outstandingInvoices($year, $asOfDate, $classId), 'invoice_balances')
            ->selectRaw(
                'COUNT(*) AS invoice_count,
                COALESCE(SUM(outstanding_amount), 0) AS outstanding,
                COALESCE(SUM(is_overdue), 0) AS overdue_invoice_count,
                COALESCE(SUM(CASE WHEN is_overdue = 1 THEN outstanding_amount ELSE 0 END), 0) AS overdue',
            )
            ->first();

        return [
            'invoiceCount' => (int) ($summary->invoice_count ?? 0),
            'outstanding' => (float) ($summary->outstanding ?? 0),
            'overdueInvoiceCount' => (int) ($summary->overdue_invoice_count ?? 0),
            'overdue' => (float) ($summary->overdue ?? 0),
        ];
    }

    public function outstandingInvoices(AcademicYear $year, string $asOfDate, ?int $classId): Builder
    {
        $asOfDateExclusive = Carbon::parse($asOfDate)->addDay()->toDateString();

        $latestEnrolments = DB::table('enrolments')
            ->select('student_id')
            ->selectRaw('MAX(id) AS enrolment_id')
            ->where('academic_year_id', $year->id)
            ->whereIn('status', ['active', 'completed'])
            ->groupBy('student_id');

        $paymentsAsOf = DB::table('fee_payments')
            ->select('invoice_id')
            ->selectRaw('SUM(amount) AS paid_amount')
            ->where('payment_date', '<', $asOfDateExclusive)
            ->groupBy('invoice_id');

        return DB::table('invoices')
            ->join('students', 'students.id', '=', 'invoices.student_id')
            ->leftJoinSub($latestEnrolments, 'latest_enrolments', function (JoinClause $join): void {
                $join->on('latest_enrolments.student_id', '=', 'invoices.student_id');
            })
            ->leftJoin('enrolments', 'enrolments.id', '=', 'latest_enrolments.enrolment_id')
            ->leftJoin('classes', 'classes.id', '=', 'enrolments.class_id')
            ->leftJoinSub($paymentsAsOf, 'payments_as_of', function (JoinClause $join): void {
                $join->on('payments_as_of.invoice_id', '=', 'invoices.id');
            })
            ->where('invoices.academic_year_id', $year->id)
            ->where('invoices.issued_at', '<', $asOfDateExclusive)
            ->whereRaw('invoices.total_amount > COALESCE(payments_as_of.paid_amount, 0)')
            ->when($classId !== null, fn (Builder $query): Builder => $query
                ->where('enrolments.class_id', $classId))
            ->select([
                'invoices.id',
                'invoices.invoice_number',
                'invoices.due_date',
                'invoices.total_amount',
                'students.admission_number',
                'students.first_name',
                'students.middle_name',
                'students.last_name',
                'classes.name AS class_name',
            ])
            ->selectRaw(
                'COALESCE(payments_as_of.paid_amount, 0) AS paid_amount,
                invoices.total_amount - COALESCE(payments_as_of.paid_amount, 0) AS outstanding_amount,
                CASE WHEN invoices.due_date < ? THEN 1 ELSE 0 END AS is_overdue',
                [$asOfDate],
            )
            ->orderByDesc('is_overdue')
            ->orderBy('invoices.due_date')
            ->orderByDesc('outstanding_amount')
            ->orderBy('invoices.id');
    }
}
