<?php

namespace App\Exports;

use App\Models\FeePayment;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaymentsExport extends SafeStringExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private ?int $academicYearId = null) {}

    public function query(): Builder
    {
        return FeePayment::query()
            ->with([
                'invoice.academicYear:id,name',
                'invoice.student:id,admission_number,first_name,middle_name,last_name',
            ])
            ->when(
                $this->academicYearId,
                fn (Builder $query) => $query->whereHas(
                    'invoice',
                    fn (Builder $invoiceQuery) => $invoiceQuery->where('academic_year_id', $this->academicYearId),
                ),
            )
            ->orderByDesc('payment_date')
            ->orderByDesc('id');
    }

    public function headings(): array
    {
        return [
            'receipt_number',
            'invoice_number',
            'academic_year',
            'admission_number',
            'student_name',
            'amount',
            'payment_method',
            'payment_date',
            'transaction_reference',
        ];
    }

    public function map(mixed $payment): array
    {
        $student = $payment->invoice->student;

        return [
            $payment->receipt_number,
            $payment->invoice->invoice_number,
            $payment->invoice->academicYear->name,
            $student->admission_number,
            trim(implode(' ', array_filter([
                $student->first_name,
                $student->middle_name,
                $student->last_name,
            ]))),
            $payment->amount,
            $payment->payment_method,
            $payment->payment_date->toDateString(),
            $payment->transaction_reference,
        ];
    }
}
