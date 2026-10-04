<?php

namespace App\Services;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicYearService
{
    /**
     * @param  array{name: string, start_date: string, end_date: string}  $data
     */
    public function create(array $data): AcademicYear
    {
        return DB::transaction(fn (): AcademicYear => AcademicYear::query()->create([
            ...$data,
            'status' => AcademicYearStatus::Draft,
        ]));
    }

    /**
     * @param  array{name: string, start_date: string, end_date: string}  $data
     */
    public function update(AcademicYear $academicYear, array $data): AcademicYear
    {
        return DB::transaction(function () use ($academicYear, $data): AcademicYear {
            $academicYear = AcademicYear::query()->lockForUpdate()->findOrFail($academicYear->getKey());
            $this->ensureStatus($academicYear, AcademicYearStatus::Draft, 'Only draft academic years can be edited.');

            $academicYear->update($data);

            return $academicYear->refresh();
        });
    }

    public function activate(AcademicYear $academicYear): AcademicYear
    {
        return DB::transaction(function () use ($academicYear): AcademicYear {
            AcademicYear::query()->orderBy('id')->lockForUpdate()->get(['id']);
            $academicYear = AcademicYear::query()->lockForUpdate()->findOrFail($academicYear->getKey());
            $this->ensureStatus($academicYear, AcademicYearStatus::Draft, 'Only draft academic years can be activated.');

            if (AcademicYear::active()->exists()) {
                throw ValidationException::withMessages([
                    'academic_year' => 'Complete the current active academic year before activating another.',
                ]);
            }

            $academicYear->update(['status' => AcademicYearStatus::Active]);

            return $academicYear->refresh();
        });
    }

    public function complete(AcademicYear $academicYear): AcademicYear
    {
        return $this->transition(
            $academicYear,
            AcademicYearStatus::Active,
            AcademicYearStatus::Completed,
            'Only the active academic year can be completed.',
        );
    }

    public function lock(AcademicYear $academicYear): AcademicYear
    {
        return $this->transition(
            $academicYear,
            AcademicYearStatus::Completed,
            AcademicYearStatus::Locked,
            'Complete an academic year before locking it.',
        );
    }

    public function delete(AcademicYear $academicYear): void
    {
        DB::transaction(function () use ($academicYear): void {
            $academicYear = AcademicYear::query()->lockForUpdate()->findOrFail($academicYear->getKey());
            $this->ensureStatus($academicYear, AcademicYearStatus::Draft, 'Only draft academic years can be deleted.');
            $academicYear->delete();
        });
    }

    /**
     * @param  array{name: string, start_date: string, end_date: string}  $data
     */
    public function createTerm(AcademicYear $academicYear, array $data): Term
    {
        return DB::transaction(function () use ($academicYear, $data): Term {
            $academicYear = AcademicYear::query()->lockForUpdate()->findOrFail($academicYear->getKey());
            $this->ensureStatus($academicYear, AcademicYearStatus::Draft, 'Terms can only be changed while the year is in draft.');

            return $academicYear->terms()->create($data);
        });
    }

    private function transition(
        AcademicYear $academicYear,
        AcademicYearStatus $from,
        AcademicYearStatus $to,
        string $message,
    ): AcademicYear {
        return DB::transaction(function () use ($academicYear, $from, $to, $message): AcademicYear {
            $academicYear = AcademicYear::query()->lockForUpdate()->findOrFail($academicYear->getKey());
            $this->ensureStatus($academicYear, $from, $message);
            $academicYear->update(['status' => $to]);

            return $academicYear->refresh();
        });
    }

    private function ensureStatus(
        AcademicYear $academicYear,
        AcademicYearStatus $expected,
        string $message,
    ): void {
        if ($academicYear->status !== $expected) {
            throw ValidationException::withMessages(['academic_year' => $message]);
        }
    }
}
