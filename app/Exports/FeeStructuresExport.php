<?php

namespace App\Exports;

use App\Models\FeeStructure;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FeeStructuresExport extends SafeStringExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private int $academicYearId) {}

    public function query(): Builder
    {
        return FeeStructure::query()
            ->with(['academicYear:id,name', 'schoolClass:id,name', 'feeHead:id,code'])
            ->where('academic_year_id', $this->academicYearId)
            ->orderBy('class_id')
            ->orderBy('fee_head_id');
    }

    public function headings(): array
    {
        return ['academic_year', 'class_name', 'fee_head_code', 'amount', 'due_date'];
    }

    public function map(mixed $structure): array
    {
        return [
            $structure->academicYear->name,
            $structure->schoolClass->name,
            $structure->feeHead->code,
            $structure->amount,
            $structure->due_date->toDateString(),
        ];
    }
}
