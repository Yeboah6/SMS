<?php

namespace App\Concerns;

use App\Models\FeePayment;
use App\Models\Invoice;
use App\Models\Student;
 
trait DescribesInvoices
{
    /** paid | partially_paid | unpaid. Overdue is a separate flag (a balance past its due date). */
    protected function invoiceStatus(float $total, float $paid): string
    {
        return $paid >= $total ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid');
    }
 
    /** Requires the student and academicYear relations. */
    protected function invoiceSummary(Invoice $invoice): array
    {
        $total = (float) $invoice->total_amount;
        $paid = (float) $invoice->paid_amount;
        $outstanding = max(0, $total - $paid);
 
        return [
            'id' => $invoice->id,
            'invoiceNumber' => $invoice->invoice_number,
            'academicYear' => $invoice->academicYear->name,
            'studentId' => $invoice->student_id,
            'studentName' => $this->fullName($invoice->student),
            'admissionNumber' => $invoice->student->admission_number,
            'issuedAt' => $invoice->issued_at->toDateString(),
            'dueDate' => $invoice->due_date->toDateString(),
            'totalAmount' => $total,
            'paidAmount' => $paid,
            'outstandingAmount' => $outstanding,
            'status' => $this->invoiceStatus($total, $paid),
            'overdue' => $outstanding > 0 && $invoice->due_date->lt(today()),
        ];
    }
 
    /** Also requires items.feeHead and payments. */
    protected function invoiceDetails(Invoice $invoice): array
    {
        return $this->invoiceSummary($invoice) + [
            'items' => $invoice->items->map(fn ($item): array => [
                'description' => $item->description,
                'amount' => (float) $item->amount,
                'dueDate' => $item->due_date->toDateString(),
            ])->values()->all(),
            'payments' => $invoice->payments->map(fn (FeePayment $payment): array => [
                'id' => $payment->id,
                'receiptNumber' => $payment->receipt_number,
                'amount' => (float) $payment->amount,
                'method' => $payment->payment_method,
                'date' => $payment->payment_date->toDateString(),
                'reference' => $payment->transaction_reference,
            ])->values()->all(),
        ];
    }
 
    protected function fullName(Student $student): string
    {
        return trim(implode(' ', array_filter([
            $student->first_name,
            $student->middle_name,
            $student->last_name,
        ])));
    }
}
 
