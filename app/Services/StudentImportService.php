<?php

namespace App\Services;

use App\Http\Requests\StoreStudentRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Bulk-creates students (one guardian each) from a CSV. All rows import, or none do. */
class StudentImportService
{
    public const COLUMNS = [
        'admission_number',
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'gender',
        'admission_date',
        'status',
        'blood_group',
        'religion',
        'address',
        'guardian_name',
        'guardian_relationship',
        'guardian_phone',
        'guardian_email',
        'guardian_occupation',
        'guardian_address',
        'guardian_lives_with_student',
    ];

    private const REQUIRED_COLUMNS = [
        'first_name', 'last_name', 'date_of_birth', 'gender', 'admission_date', 'guardian_name', 'guardian_phone',
    ];

    private const MAX_ROWS = 500;

    public function __construct(private StudentProfileService $students) {}

    /** @return array{rows: list<array<string, mixed>>, errors: list<array{line: int, messages: list<string>}>} */
    public function read(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $firstLine = (string) fgets($handle);
        rewind($handle);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $header = fgetcsv($handle, 0, $delimiter) ?: [];
        $header = array_map(
            fn ($name): string => str_replace([' ', '-'], '_', strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $name)))),
            $header,
        );

        $missing = array_diff(self::REQUIRED_COLUMNS, $header);
        if ($missing !== []) {
            fclose($handle);

            return ['rows' => [], 'errors' => [[
                'line' => 1,
                'messages' => ['Missing column(s): '.implode(', ', $missing).'. Download the template for the expected headings.'],
            ]]];
        }

        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if ($cells === [null] || count(array_filter($cells, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);

                return ['rows' => [], 'errors' => [[
                    'line' => $line,
                    'messages' => ['A file can contain at most '.self::MAX_ROWS.' students. Split it into smaller files.'],
                ]]];
            }

            $row = [];
            foreach ($header as $index => $name) {
                $value = trim((string) ($cells[$index] ?? ''));
                $row[$name] = $value === '' ? null : $value;
            }
            $row['status'] ??= 'active';
            $row['guardian_relationship'] ??= 'Parent';
            $rows[] = ['line' => $line] + $row;
        }
        fclose($handle);

        return ['rows' => $rows, 'errors' => []];
    }

    /** @return list<array{line: int, messages: list<string>}> */
    public function validate(array $rows): array
    {
        $errors = [];
        $seen = [];

        foreach ($rows as $row) {
            $validator = Validator::make($row, [
                'admission_number' => ['nullable', 'string', 'max:50', 'regex:'.StoreStudentRequest::ADMISSION_NUMBER_FORMAT, Rule::unique('students', 'admission_number')],
                'first_name' => ['required', 'string', 'max:100'],
                'middle_name' => ['nullable', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
                'gender' => ['required', Rule::in(['female', 'male', 'other', 'prefer_not_to_say'])],
                'admission_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'status' => ['required', Rule::in(['active', 'inactive', 'graduated', 'transferred', 'left'])],
                'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
                'religion' => ['nullable', 'string', 'max:100'],
                'address' => ['nullable', 'string', 'max:1000'],
                'guardian_name' => ['required', 'string', 'max:255'],
                'guardian_relationship' => ['required', 'string', 'max:100'],
                'guardian_phone' => ['required', 'string', 'max:50'],
                'guardian_email' => ['nullable', 'email', 'max:255'],
                'guardian_occupation' => ['nullable', 'string', 'max:255'],
                'guardian_address' => ['nullable', 'string', 'max:1000'],
                'guardian_lives_with_student' => ['nullable', Rule::in(['yes', 'no', '1', '0', 'true', 'false'])],
            ], [], ['guardian_lives_with_student' => 'guardian lives with student']);

            $messages = $validator->errors()->all();

            $number = $row['admission_number'] ?? null;
            if ($number !== null) {
                $key = strtolower($number);
                if (isset($seen[$key])) {
                    $messages[] = "The admission number {$number} is repeated (first used on line {$seen[$key]}).";
                }
                $seen[$key] ??= $row['line'];
            }

            if ($messages !== []) {
                $errors[] = ['line' => $row['line'], 'messages' => $messages];
            }
        }

        return $errors;
    }

    /** Creates every row in one transaction. Blank admission numbers are generated in file order. */
    public function import(array $rows, User $actor): int
    {
        return DB::transaction(function () use ($rows, $actor): int {
            foreach ($rows as $row) {
                $this->students->create([
                    'admission_number' => $row['admission_number'] ?? null,
                    'first_name' => $row['first_name'],
                    'middle_name' => $row['middle_name'] ?? null,
                    'last_name' => $row['last_name'],
                    'date_of_birth' => $row['date_of_birth'],
                    'gender' => $row['gender'],
                    'blood_group' => $row['blood_group'] ?? null,
                    'religion' => $row['religion'] ?? null,
                    'address' => $row['address'] ?? null,
                    'photo' => null,
                    'admission_date' => $row['admission_date'],
                    'status' => $row['status'],
                    'guardians' => [[
                        'name' => $row['guardian_name'],
                        'phone' => $row['guardian_phone'],
                        'email' => $row['guardian_email'] ?? null,
                        'occupation' => $row['guardian_occupation'] ?? null,
                        'address' => $row['guardian_address'] ?? null,
                        'relationship' => $row['guardian_relationship'],
                        'is_primary' => true,
                        'lives_with_student' => in_array(strtolower((string) ($row['guardian_lives_with_student'] ?? '')), ['yes', '1', 'true'], true),
                    ]],
                ], $actor);
            }

            return count($rows);
        });
    }
}