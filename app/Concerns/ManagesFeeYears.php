<?php

namespace App\Concerns;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
 
trait ManagesFeeYears
{
    /** @return Collection<int, AcademicYear> */
    protected function academicYears(): Collection
    {
        return AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'status']);
    }
 
    /** The ?academic_year_id= year, otherwise the active one, otherwise the newest. */
    protected function pickYear(Request $request, Collection $years): ?AcademicYear
    {
        if ($request->filled('academic_year_id')) {
            $year = $years->firstWhere('id', $request->integer('academic_year_id'));
            abort_unless($year, 404);
 
            return $year;
        }
 
        return $years->firstWhere('id', AcademicYear::active()->value('id')) ?? $years->first();
    }
 
    protected function isOpen(?AcademicYear $year): bool
    {
        return $year !== null && $year->status !== AcademicYearStatus::Locked;
    }
 
    protected function yearOptions(Collection $years): array
    {
        return $years->map(fn (AcademicYear $year): array => [
            'id' => $year->id,
            'name' => $year->name,
            'status' => $year->status->value,
        ])->values()->all();
    }
}
 
