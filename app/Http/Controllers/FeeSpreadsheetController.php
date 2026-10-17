<?php

namespace App\Http\Controllers;

use App\Exports\FeeHeadsExport;
use App\Exports\FeeStructuresExport;
use App\Exports\InvoicesExport;
use App\Exports\PaymentsExport;
use App\Http\Requests\ExportFeeStructuresRequest;
use App\Http\Requests\ExportFinancialRecordsRequest;
use App\Http\Requests\ImportFeeHeadsRequest;
use App\Http\Requests\ImportFeeStructuresRequest;
use App\Imports\HeadingRowsImport;
use App\Services\FeeSpreadsheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FeeSpreadsheetController extends Controller
{
    public function importFeeHeads(
        ImportFeeHeadsRequest $request,
        FeeSpreadsheetService $service,
    ): RedirectResponse {
        $result = $service->importFeeHeads($this->importRows($request), $request->user());

        return back()->with('status', "{$result['created']} fee heads created and {$result['updated']} updated.");
    }

    public function importFeeStructures(
        ImportFeeStructuresRequest $request,
        FeeSpreadsheetService $service,
    ): RedirectResponse {
        $result = $service->importFeeStructures($this->importRows($request), $request->user());

        return back()->with('status', "{$result['created']} fee structures created and {$result['updated']} updated.");
    }

    public function exportFeeHeads(): BinaryFileResponse
    {
        return Excel::download(new FeeHeadsExport, 'fee-heads.xlsx');
    }

    public function exportFeeStructures(ExportFeeStructuresRequest $request): BinaryFileResponse
    {
        return Excel::download(
            new FeeStructuresExport($request->integer('academic_year_id')),
            'fee-structures.xlsx',
        );
    }

    public function exportInvoices(ExportFinancialRecordsRequest $request): BinaryFileResponse
    {
        return Excel::download(
            new InvoicesExport($request->filled('academic_year_id') ? $request->integer('academic_year_id') : null),
            'invoices.xlsx',
        );
    }

    public function exportPayments(ExportFinancialRecordsRequest $request): BinaryFileResponse
    {
        return Excel::download(
            new PaymentsExport($request->filled('academic_year_id') ? $request->integer('academic_year_id') : null),
            'payments.xlsx',
        );
    }

    /**
     * @return Collection<int, Collection<string, mixed>>
     */
    private function importRows(ImportFeeHeadsRequest|ImportFeeStructuresRequest $request): Collection
    {
        $sheets = Excel::toCollection(new HeadingRowsImport, $request->file('file'));
        if ($sheets->count() !== 1) {
            throw ValidationException::withMessages([
                'file' => 'Import a workbook with exactly one worksheet.',
            ]);
        }

        return $sheets->first() ?? collect();
    }
}
