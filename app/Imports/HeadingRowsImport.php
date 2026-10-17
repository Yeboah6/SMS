<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class HeadingRowsImport implements ToCollection, WithHeadingRow
{
    private ?Collection $importedRows = null;

    public function collection(Collection $rows): void
    {
        $this->importedRows = $rows;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(): Collection
    {
        return $this->importedRows ?? collect();
    }
}
