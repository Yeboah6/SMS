<?php

use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\FeePayment;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\AcademicYearService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @return array{User, AcademicYear, SchoolClass, SchoolClass, array{Student, Invoice}, array{Student, Invoice}, array{Student, Invoice}}
 */
function createFinancialReportScenario(): array
{
    $accountant = User::factory()->create();
    $accountant->assignRole('Accountant');

    $year = app(AcademicYearService::class)->create([
        'name' => 'Financial Report Year',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonths(6)->toDateString(),
    ]);
    app(AcademicYearService::class)->activate($year);

    $firstClass = $year->classes()->create(['name' => 'Financial Grade One']);
    $secondClass = $year->classes()->create(['name' => 'Financial Grade Two']);

    $first = createFinancialReportStudent($year, $firstClass, 'FIN-001', '=SUM(1,2)');
    $second = createFinancialReportStudent($year, $firstClass, 'FIN-002', 'Morgan');
    $otherClass = createFinancialReportStudent($year, $secondClass, 'FIN-003', 'Taylor');

    createFinancialReportPayment($first[1], $accountant, 'FIN-RECEIPT-001', 20.50, today()->toDateString());
    createFinancialReportPayment($second[1], $accountant, 'FIN-RECEIPT-002', 10, today()->subDay()->toDateString());
    createFinancialReportPayment($otherClass[1], $accountant, 'FIN-RECEIPT-003', 30, today()->toDateString());

    return [$accountant, $year, $firstClass, $secondClass, $first, $second, $otherClass];
}

/**
 * @return array{Student, Invoice}
 */
function createFinancialReportStudent(
    AcademicYear $year,
    SchoolClass $schoolClass,
    string $admissionNumber,
    string $firstName,
): array {
    $student = Student::query()->create([
        'admission_number' => $admissionNumber,
        'first_name' => $firstName,
        'last_name' => 'Learner',
        'date_of_birth' => '2018-01-01',
        'gender' => 'prefer_not_to_say',
        'admission_date' => $year->start_date->toDateString(),
        'status' => 'active',
    ]);
    $section = Section::query()->create([
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'name' => 'A-'.$admissionNumber,
    ]);
    Enrolment::query()->create([
        'academic_year_id' => $year->id,
        'student_id' => $student->id,
        'class_id' => $schoolClass->id,
        'section_id' => $section->id,
        'enrolment_date' => $year->start_date->toDateString(),
        'status' => 'active',
    ]);
    $invoice = Invoice::query()->create([
        'academic_year_id' => $year->id,
        'student_id' => $student->id,
        'invoice_number' => 'FIN-INV-'.$admissionNumber,
        'status' => 'unpaid',
        'total_amount' => 100,
        'paid_amount' => 0,
        'issued_at' => $year->start_date->toDateString(),
        'due_date' => $year->end_date->toDateString(),
    ]);

    return [$student, $invoice];
}

function createFinancialReportPayment(
    Invoice $invoice,
    User $accountant,
    string $receiptNumber,
    float $amount,
    string $paymentDate,
): void {
    FeePayment::query()->create([
        'invoice_id' => $invoice->id,
        'received_by' => $accountant->id,
        'receipt_number' => $receiptNumber,
        'payment_method' => 'bank_transfer',
        'amount' => $amount,
        'payment_date' => $paymentDate,
    ]);
}

test('accountants can filter collection totals and payments by academic year, class, and date', function () {
    [$accountant, $year, $schoolClass] = createFinancialReportScenario();

    $this->actingAs($accountant)->get(route('reports.financial.index', [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'from_date' => today()->toDateString(),
        'to_date' => today()->toDateString(),
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Financial')
            ->where('selectedAcademicYearId', $year->id)
            ->where('selectedClassId', $schoolClass->id)
            ->where('summary.paymentCount', 1)
            ->where('summary.collected', 20.5)
            ->where('payments.total', 1)
            ->where('payments.data.0.receiptNumber', 'FIN-RECEIPT-001')
            ->where('payments.data.0.amount', 20.5)
            ->where('payments.data.0.studentName', '=SUM(1,2) Learner'));
});

test('accountants can download formula-safe filtered collection csv exports', function () {
    [$accountant, $year, $schoolClass] = createFinancialReportScenario();

    $this->actingAs($accountant)->get(route('reports.financial.export', [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'from_date' => today()->toDateString(),
        'to_date' => today()->toDateString(),
    ]))
        ->assertDownload('fee-collection-report-'.$year->start_date->year.'-'.$year->end_date->year.'.csv')
        ->assertStreamedContent(
            "\xEF\xBB\xBF\"Receipt number\",\"Payment date\",Student,\"Admission number\",Class,\"Invoice number\",\"Payment method\",Amount\n".
            'FIN-RECEIPT-001,'.today()->toDateString().",\"'=SUM(1,2) Learner\",FIN-001,\"Financial Grade One\",FIN-INV-FIN-001,bank_transfer,20.50\n",
        );
});

test('principals and accountants can view the report while users without financial report access cannot', function () {
    [$accountant, $year] = createFinancialReportScenario();
    $principal = User::factory()->create();
    $principal->assignRole('Principal');

    $this->actingAs($principal)->get(route('reports.financial.index'))->assertOk();
    $this->actingAs($accountant)->get(route('reports.financial.export'))->assertDownload(
        'fee-collection-report-'.$year->start_date->year.'-'.$year->end_date->year.'.csv',
    );

    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');
    $this->actingAs($teacher)->get(route('reports.financial.index'))->assertForbidden();
    $this->actingAs($teacher)->get(route('reports.financial.export'))->assertForbidden();
});

test('financial report filters reject invalid date ranges and classes from another year', function () {
    [$accountant, $year, , $otherClass] = createFinancialReportScenario();

    $this->actingAs($accountant)->get(route('reports.financial.index', [
        'academic_year_id' => $year->id,
        'from_date' => today()->toDateString(),
        'to_date' => today()->subDay()->toDateString(),
    ]))->assertSessionHasErrors('to_date');

    $otherYear = app(AcademicYearService::class)->create([
        'name' => 'Another Financial Year',
        'start_date' => today()->subYears(2)->toDateString(),
        'end_date' => today()->subYear()->toDateString(),
    ]);
    $unrelatedClass = $otherYear->classes()->create(['name' => 'Unrelated Class']);

    $this->actingAs($accountant)->get(route('reports.financial.index', [
        'academic_year_id' => $year->id,
        'class_id' => $unrelatedClass->id,
    ]))->assertNotFound();

    expect($otherClass->academic_year_id)->toBe($year->id);
});

test('accountants can review outstanding and overdue balances using payments dated by the selected date', function () {
    [$accountant, $year, $schoolClass, , [, $firstInvoice]] = createFinancialReportScenario();
    $secondInvoice = Invoice::query()->where('invoice_number', 'FIN-INV-FIN-002')->firstOrFail();
    $firstInvoice->update(['due_date' => today()->subDay()->toDateString()]);
    $secondInvoice->update(['due_date' => today()->toDateString()]);
    createFinancialReportPayment($firstInvoice, $accountant, 'FIN-RECEIPT-FUTURE', 40, today()->addDay()->toDateString());

    $this->actingAs($accountant)->get(route('reports.financial.outstanding', [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'as_of_date' => today()->toDateString(),
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/OutstandingFees')
            ->where('selectedAcademicYearId', $year->id)
            ->where('selectedClassId', $schoolClass->id)
            ->where('summary.invoiceCount', 2)
            ->where('summary.outstanding', 169.5)
            ->where('summary.overdueInvoiceCount', 1)
            ->where('summary.overdue', 79.5)
            ->where('invoices.total', 2)
            ->where('invoices.data.0.invoiceNumber', $firstInvoice->invoice_number)
            ->where('invoices.data.0.studentName', '=SUM(1,2) Learner')
            ->where('invoices.data.0.paidAmount', 20.5)
            ->where('invoices.data.0.outstandingAmount', 79.5)
            ->where('invoices.data.0.daysOverdue', 1)
            ->where('invoices.data.1.daysOverdue', 0));
});

test('outstanding fee report excludes invoices issued after the selected date', function () {
    [$accountant, $year, $schoolClass, , [, $firstInvoice], [, $secondInvoice]] = createFinancialReportScenario();
    $secondInvoice->update(['issued_at' => today()->addDay()->toDateString()]);

    $this->actingAs($accountant)->get(route('reports.financial.outstanding', [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'as_of_date' => today()->toDateString(),
    ]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.invoiceCount', 1)
            ->where('summary.outstanding', 79.5)
            ->where('invoices.total', 1)
            ->where('invoices.data.0.invoiceNumber', $firstInvoice->invoice_number));
});

test('accountants can export formula-safe outstanding invoice balances', function () {
    [$accountant, $year, $schoolClass, , [, $firstInvoice]] = createFinancialReportScenario();
    $firstInvoice->update(['due_date' => today()->subDay()->toDateString()]);

    $this->actingAs($accountant)->get(route('reports.financial.outstanding.export', [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'as_of_date' => today()->toDateString(),
    ]))
        ->assertDownload('outstanding-fees-report-'.$year->start_date->year.'-'.$year->end_date->year.'.csv')
        ->assertStreamedContent(
            "\xEF\xBB\xBF\"Invoice number\",Student,\"Admission number\",Class,\"Due date\",\"Days overdue\",\"Total amount\",\"Paid as of date\",\"Outstanding balance\"\n".
            "FIN-INV-FIN-001,\"'=SUM(1,2) Learner\",FIN-001,\"Financial Grade One\",".today()->subDay()->toDateString().",1,100.00,20.50,79.50\n".
            'FIN-INV-FIN-002,"Morgan Learner",FIN-002,"Financial Grade One",'.today()->addMonths(6)->toDateString().",0,100.00,10.00,90.00\n",
        );
});

test('outstanding fee report filters reject invalid dates and classes from another year', function () {
    [$accountant, $year] = createFinancialReportScenario();
    $otherYear = app(AcademicYearService::class)->create([
        'name' => 'Unrelated Outstanding Year',
        'start_date' => today()->subYears(2)->toDateString(),
        'end_date' => today()->subYear()->toDateString(),
    ]);
    $unrelatedClass = $otherYear->classes()->create(['name' => 'Unrelated Outstanding Class']);

    $this->actingAs($accountant)->get(route('reports.financial.outstanding', [
        'academic_year_id' => $year->id,
        'as_of_date' => 'not-a-date',
    ]))->assertSessionHasErrors('as_of_date');

    $this->actingAs($accountant)->get(route('reports.financial.outstanding', [
        'academic_year_id' => $year->id,
        'class_id' => $unrelatedClass->id,
    ]))->assertNotFound();
});

test('only financial report viewers can access outstanding balances', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');

    $this->actingAs($teacher)->get(route('reports.financial.outstanding'))->assertForbidden();
    $this->actingAs($teacher)->get(route('reports.financial.outstanding.export'))->assertForbidden();
});
