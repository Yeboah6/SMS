<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Services\SupplyReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const REPORTS = [
        'sales' => 'Sales over time',
        'item' => 'Sales by item',
        'category' => 'Sales by category',
        'class' => 'Sales by class',
        'student' => 'Sales by student',
        'cashier' => 'Sales by cashier',
        'method' => 'Payments by method',
        'stock' => 'Stock movements',
        'low-stock' => 'Low and out-of-stock items',
        'outstanding' => 'Outstanding balances',
        'requirements' => 'Class requirement completion',
    ];

    public function index(Request $request, SupplyReportService $reports): Response
    {
        $user = $request->user();
        abort_unless($user->can('supplies.reports.view'), 403);

        $report = array_key_exists($request->string('report')->toString(), self::REPORTS) ? $request->string('report')->toString() : 'sales';
        $filters = $this->filters($request);
        [$from, $to] = $reports->period($filters);
        $year = $this->year($request);

        $data = match (true) {
            $report === 'sales' => $reports->sales($filters),
            in_array($report, SupplyReportService::BREAKDOWNS, true) => ['rows' => $reports->breakdown($report, $filters)],
            in_array($report, ['stock', 'low-stock'], true) => $reports->stock($filters),
            $report === 'outstanding' => $reports->outstanding($filters),
            default => ['rows' => $year ? $reports->requirementCompletion($year) : []],
        };

        return Inertia::render('Supplies/Reports/Index', [
            'report' => $report,
            'reports' => collect(self::REPORTS)->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])->values(),
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'granularity' => $filters['granularity'] ?? 'day', 'academic_year_id' => $year?->id],
            'granularities' => SupplyReportService::GRANULARITIES,
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name'])->map(fn ($item): array => ['id' => $item->id, 'name' => $item->name])->values(),
            'data' => $data,
            'can' => ['export' => $user->can('supplies.reports.export')],
        ]);
    }

    public function export(Request $request, SupplyReportService $reports): StreamedResponse
    {
        abort_unless($request->user()->can('supplies.reports.export'), 403);

        $report = $request->string('report')->toString();
        abort_unless(array_key_exists($report, self::REPORTS), 404);

        [$headings, $rows] = $reports->export($report, $this->filters($request), $this->year($request));

        return response()->streamDownload(function () use ($headings, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel opens it as UTF-8
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                fputcsv($out, array_map([$this, 'safeCell'], $row));
            }
            fclose($out);
        }, 'supplies-'.$report.'-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stops a spreadsheet from running a cell that starts with = + - or @ as a formula. */
    private function safeCell(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) ? "'".$value : $value;
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'granularity' => ['nullable', 'in:'.implode(',', SupplyReportService::GRANULARITIES)],
        ]);
    }

    private function year(Request $request): ?AcademicYear
    {
        return $request->filled('academic_year_id')
            ? AcademicYear::query()->findOrFail($request->integer('academic_year_id'))
            : AcademicYear::active()->first();
    }
}