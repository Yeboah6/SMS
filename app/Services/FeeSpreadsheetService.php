<?php

namespace App\Services;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class FeeSpreadsheetService
{
    public function __construct(private FeeService $fees) {}

    /**
     * @return array{created: int, updated: int}
     */
    public function importFeeHeads(Collection $rows, User $actor): array
    {
        return DB::transaction(function () use ($rows, $actor): array {
            $normalizedRows = $this->validatedRows($rows, [
                'code' => ['required', 'string', 'max:40', 'alpha_dash'],
                'name' => ['required', 'string', 'max:150'],
                'description' => ['nullable', 'string', 'max:2000'],
                'is_active' => ['sometimes', 'nullable', 'boolean'],
            ], function (array $row): array {
                $data = [
                    'code' => mb_strtoupper($this->text($row['code'] ?? null)),
                    'name' => $this->text($row['name'] ?? null),
                    'description' => $this->text($row['description'] ?? null) ?: null,
                ];
                $active = $row['is_active'] ?? null;
                if ($active !== null && $active !== '') {
                    $data['is_active'] = $active;
                }

                return $data;
            });

            $this->rejectDuplicateRows($normalizedRows, fn (array $row): string => $row['code']);

            $existingHeads = FeeHead::query()
                ->whereIn('code', array_column($normalizedRows, 'code'))
                ->lockForUpdate()
                ->get()
                ->keyBy('code');
            $created = 0;
            $updated = 0;

            foreach ($normalizedRows as $row) {
                $feeHead = $existingHeads->get($row['code']);
                $data = array_diff_key($row, ['_row' => true]);
                if ($feeHead) {
                    $this->fees->updateFeeHead($feeHead, $data, $actor);
                    $updated++;

                    continue;
                }

                $this->fees->createFeeHead($data, $actor);
                $created++;
            }

            return compact('created', 'updated');
        });
    }

    /**
     * @return array{created: int, updated: int}
     */
    public function importFeeStructures(Collection $rows, User $actor): array
    {
        return DB::transaction(function () use ($rows, $actor): array {
            $normalizedRows = $this->validatedRows($rows, [
                'academic_year' => ['required', 'string', 'max:100'],
                'class_name' => ['required', 'string', 'max:100'],
                'fee_head_code' => ['required', 'string', 'max:40', 'alpha_dash'],
                'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
                'due_date' => ['required', 'date_format:Y-m-d'],
            ], fn (array $row): array => [
                'academic_year' => $this->text($row['academic_year'] ?? null),
                'class_name' => $this->text($row['class_name'] ?? null),
                'fee_head_code' => mb_strtoupper($this->text($row['fee_head_code'] ?? null)),
                'amount' => $row['amount'] ?? null,
                'due_date' => $this->dateValue($row['due_date'] ?? null),
            ]);

            $yearNames = array_values(array_unique(array_map(
                fn (array $row): string => mb_strtolower($row['academic_year']),
                $normalizedRows,
            )));
            $academicYears = AcademicYear::query()
                ->whereIn(DB::raw('LOWER(name)'), $yearNames)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (AcademicYear $year): string => mb_strtolower($year->name));
            $classNames = array_values(array_unique(array_map(
                fn (array $row): string => mb_strtolower($row['class_name']),
                $normalizedRows,
            )));
            $classes = SchoolClass::query()
                ->whereIn('academic_year_id', $academicYears->modelKeys())
                ->whereIn(DB::raw('LOWER(name)'), $classNames)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (SchoolClass $schoolClass): string => $schoolClass->academic_year_id.'|'.mb_strtolower($schoolClass->name));
            $feeHeads = FeeHead::query()
                ->whereIn('code', array_column($normalizedRows, 'fee_head_code'))
                ->get()
                ->keyBy(fn (FeeHead $feeHead): string => mb_strtoupper($feeHead->code));

            $resolvedRows = [];
            $errors = [];
            $seenKeys = [];

            foreach ($normalizedRows as $row) {
                $year = $academicYears->get(mb_strtolower($row['academic_year']));
                $schoolClass = $year
                    ? $classes->get($year->id.'|'.mb_strtolower($row['class_name']))
                    : null;
                $feeHead = $feeHeads->get($row['fee_head_code']);
                $rowNumber = $row['_row'];

                if (! $year) {
                    $errors[] = "Row {$rowNumber}: academic year '{$row['academic_year']}' was not found.";

                    continue;
                }
                if ($year->status === AcademicYearStatus::Locked) {
                    $errors[] = "Row {$rowNumber}: academic year '{$year->name}' is locked.";

                    continue;
                }
                if (! $schoolClass) {
                    $errors[] = "Row {$rowNumber}: class '{$row['class_name']}' was not found in academic year '{$year->name}'.";

                    continue;
                }
                if (! $feeHead || ! $feeHead->is_active) {
                    $errors[] = "Row {$rowNumber}: active fee head '{$row['fee_head_code']}' was not found.";

                    continue;
                }

                $key = $year->id.'|'.$schoolClass->id.'|'.$feeHead->id;
                if (isset($seenKeys[$key])) {
                    $errors[] = "Row {$rowNumber}: this academic year, class, and fee head combination appears more than once.";

                    continue;
                }

                $dueDate = Carbon::parse($row['due_date']);
                if ($dueDate->lt($year->start_date) || $dueDate->gt($year->end_date)) {
                    $errors[] = "Row {$rowNumber}: due date must fall within academic year '{$year->name}'.";

                    continue;
                }

                $seenKeys[$key] = true;
                $resolvedRows[] = [
                    'key' => $key,
                    'academic_year' => $year,
                    'school_class' => $schoolClass,
                    'fee_head' => $feeHead,
                    'amount' => $row['amount'],
                    'due_date' => $row['due_date'],
                ];
            }

            if ($errors !== []) {
                throw ValidationException::withMessages(['file' => $errors]);
            }

            $existingStructures = FeeStructure::query()
                ->whereIn('academic_year_id', $academicYears->modelKeys())
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (FeeStructure $structure): string => $structure->academic_year_id.'|'.$structure->class_id.'|'.$structure->fee_head_id);
            $created = 0;
            $updated = 0;

            foreach ($resolvedRows as $row) {
                $data = [
                    'academic_year_id' => $row['academic_year']->id,
                    'class_id' => $row['school_class']->id,
                    'fee_head_id' => $row['fee_head']->id,
                    'amount' => $row['amount'],
                    'due_date' => $row['due_date'],
                ];
                $existingStructure = $existingStructures->get($row['key']);

                if ($existingStructure) {
                    $this->fees->updateStructure($existingStructure, $data, $actor);
                    $updated++;

                    continue;
                }

                $this->fees->createStructure($data, $actor);
                $created++;
            }

            return compact('created', 'updated');
        });
    }

    /**
     * @param  array<string, list<string>>  $rules
     * @param  callable(array<string, mixed>): array<string, mixed>  $normalize
     * @return list<array<string, mixed>>
     */
    private function validatedRows(Collection $rows, array $rules, callable $normalize): array
    {
        $normalizedRows = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $values = $row instanceof Collection ? $row->all() : (array) $row;
            if ($this->isBlankRow($values)) {
                continue;
            }

            $rowNumber = (int) $index + 2;
            $data = $normalize($values);
            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $errors[] = "Row {$rowNumber}: ".$validator->errors()->first().'.';

                continue;
            }

            $normalizedRows[] = [
                ...$validator->validated(),
                '_row' => $rowNumber,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => $errors]);
        }
        if ($normalizedRows === []) {
            throw ValidationException::withMessages(['file' => 'The spreadsheet must contain at least one data row.']);
        }

        return $normalizedRows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function rejectDuplicateRows(array $rows, callable $keyForRow): void
    {
        $seen = [];
        $errors = [];

        foreach ($rows as $row) {
            $key = $keyForRow($row);
            if (isset($seen[$key])) {
                $errors[] = "Row {$row['_row']}: '{$key}' appears more than once.";
            }

            $seen[$key] = true;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => $errors]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && (! is_string($value) || trim($value) !== '')) {
                return false;
            }
        }

        return true;
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function dateValue(mixed $value): string
    {
        if (is_numeric($value) && (float) $value > 0 && (float) $value < 2958466) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        return $this->text($value);
    }
}
