<?php

namespace App\Exports;

use App\Models\FeeHead;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FeeHeadsExport extends SafeStringExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function query(): Builder
    {
        return FeeHead::query()->orderBy('code');
    }

    public function headings(): array
    {
        return ['code', 'name', 'description', 'is_active'];
    }

    public function map(mixed $feeHead): array
    {
        return [
            $feeHead->code,
            $feeHead->name,
            $feeHead->description,
            $feeHead->is_active ? 1 : 0,
        ];
    }
}
