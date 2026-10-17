<?php

namespace App\Exports;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InvoicesExport extends SafeStringExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private ?int $academicYearId = null) {}

    public function query(): Builder
    {
        return Invoice::query()
            ->with(['academicYear:id,name', 'student:id,admission_number,first_name,middle_name,last_name'])
            ->when($this->academicYearId, fn (Builder $query) => $query->where('academic_year_id', $this->academicYearId))
            ->orderByDesc('issued_at')
            ->orderByDesc('id');
    }

    public function headings(): array
    {
        return [
            'invoice_number',
            'academic_year',
            'admission_number',
            'student_name',
            'issued_at',
            'due_date',
            'total_amount',
            'paid_amount',
            'outstanding_amount',
            'status',
        ];
    }

    public function map(mixed $invoice): array
    {
        return [
            $invoice->invoice_number,
            $invoice->academicYear->name,
            $invoice->student->admission_number,
            trim(implode(' ', array_filter([
                $invoice->student->first_name,
                $invoice->student->middle_name,
                $invoice->student->last_name,
            ]))),
            $invoice->issued_at->toDateString(),
            $invoice->due_date->toDateString(),
            $invoice->total_amount,
            $invoice->paid_amount,
            number_format(max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount), 2, '.', ''),
            $invoice->status,
        ];
    }
}
