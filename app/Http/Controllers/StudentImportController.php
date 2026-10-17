<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentImportService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    public function create(Request $request): Response
    {
        $this->authorizeImport($request);

        return Inertia::render('Students/Import', [
            'columns' => StudentImportService::COLUMNS,
            'report' => session('import_report'),
        ]);
    }

    public function template(Request $request): StreamedResponse
    {
        $this->authorizeImport($request);

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, StudentImportService::COLUMNS);
            fputcsv($out, ['', 'Ama', '', 'Mensah', '2015-04-12', 'female', '2026-09-01', 'active', '', '', '', 'Kofi Mensah', 'Father', '0241234567', '', '', '', 'yes']);
            fclose($out);
        }, 'students-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function store(Request $request, StudentImportService $importer): RedirectResponse
    {
        $this->authorizeImport($request);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $parsed = $importer->read($request->file('file'));
        $errors = $parsed['errors'] ?: $importer->validate($parsed['rows']);

        if ($errors !== []) {
            return to_route('students.import.create')->with('import_report', [
                'imported' => 0,
                'rowCount' => count($parsed['rows']),
                'errors' => $errors,
            ]);
        }

        try {
            $count = $importer->import($parsed['rows'], $request->user());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'file' => 'An admission number was taken while importing. Nothing was imported; please try again.',
            ]);
        }

        return to_route('students.index')->with('status', "Imported {$count} students.");
    }

    private function authorizeImport(Request $request): void
    {
        $this->authorize('create', Student::class);
        abort_unless($request->user()->can('guardians.create'), 403);
    }
}