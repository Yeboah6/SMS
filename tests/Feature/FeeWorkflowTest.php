<?php

use App\Enums\AcademicYearStatus;
use App\Exports\FeeHeadsExport;
use App\Exports\InvoicesExport;
use App\Exports\PaymentsExport;
use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentActivity;
use App\Models\User;
use App\Services\AcademicYearService;
use App\Services\FeeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @return array{User, AcademicYear, SchoolClass, FeeHead, FeeStructure}
 */
function createFeeScenario(): array
{
    $accountant = User::factory()->create();
    $accountant->assignRole('Accountant');
    $year = app(AcademicYearService::class)->create([
        'name' => 'Fees Test Year',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonths(6)->toDateString(),
    ]);
    app(AcademicYearService::class)->activate($year);
    $schoolClass = $year->classes()->create(['name' => 'Fees Class']);
    $feeHead = app(FeeService::class)->createFeeHead([
        'code' => 'TUITION',
        'name' => 'Tuition',
        'description' => 'Annual tuition',
    ], $accountant);
    $structure = app(FeeService::class)->createStructure([
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'fee_head_id' => $feeHead->id,
        'amount' => 100,
        'due_date' => today()->addMonth()->toDateString(),
    ], $accountant);

    return [$accountant, $year, $schoolClass, $feeHead, $structure];
}

function createFeeStudent(
    AcademicYear $year,
    SchoolClass $schoolClass,
    string $admissionNumber,
    ?string $role = 'Student',
): array {
    $user = User::factory()->create();
    if ($role) {
        $user->assignRole($role);
    }
    $student = Student::query()->create([
        'user_id' => $user->id,
        'admission_number' => $admissionNumber,
        'first_name' => 'Jordan',
        'last_name' => 'Student',
        'date_of_birth' => today()->subYears(12)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'admission_date' => $year->start_date->toDateString(),
        'status' => 'active',
    ]);
    $section = $schoolClass->sections()->create([
        'academic_year_id' => $year->id,
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

    return [$user, $student, $section];
}

test('accountants can configure fee heads and year/class structures', function () {
    [$accountant, $year, $schoolClass, , $structure] = createFeeScenario();

    $this->actingAs($accountant)->post(route('fee-heads.store'), [
        'code' => 'TRANSPORT',
        'name' => 'Transport',
        'description' => 'School transport',
    ])->assertRedirect()
        ->assertSessionHas('status', 'Fee head created.');

    $transport = FeeHead::query()->where('code', 'TRANSPORT')->firstOrFail();
    $this->actingAs($accountant)->post(route('fee-structures.store'), [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'fee_head_id' => $transport->id,
        'amount' => '25.50',
        'due_date' => today()->addDays(10)->toDateString(),
    ])->assertRedirect()
        ->assertSessionHas('status', 'Fee structure created.');

    $this->actingAs($accountant)->put(route('fee-structures.update', $structure), [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'fee_head_id' => $structure->fee_head_id,
        'amount' => '110.00',
        'due_date' => today()->addDays(20)->toDateString(),
    ])->assertRedirect()
        ->assertSessionHas('status', 'Fee structure updated. Existing invoices remain unchanged.');

    expect($structure->fresh()->amount)->toBe('110.00');
    $this->assertDatabaseCount('fee_structures', 2);
});

test('accountants can import fee heads by code and update existing heads', function () {
    [$accountant, , , $feeHead] = createFeeScenario();
    $upload = UploadedFile::fake()->createWithContent(
        'fee-heads.csv',
        "code,name,description,is_active\nTUITION,Updated tuition,Revised annual tuition,0\nTRANSPORT,Transport,School transport,1\n",
    );

    $this->actingAs($accountant)->post(route('fee-heads.import'), ['file' => $upload])
        ->assertRedirect()
        ->assertSessionHas('status', '1 fee heads created and 1 updated.');

    expect($feeHead->fresh()->name)->toBe('Updated tuition')
        ->and($feeHead->fresh()->description)->toBe('Revised annual tuition')
        ->and($feeHead->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('fee_heads', [
        'code' => 'TRANSPORT',
        'name' => 'Transport',
        'created_by' => $accountant->id,
    ]);
    $this->assertDatabaseCount('fee_heads', 2);
});

test('invalid fee-head import rows reject the whole spreadsheet without partial writes', function () {
    [$accountant] = createFeeScenario();
    $upload = UploadedFile::fake()->createWithContent(
        'duplicate-fee-heads.csv',
        "code,name,description,is_active\nBOOKS,Books,Book charge,1\nBOOKS,Duplicate books,Duplicate row,1\n",
    );

    $this->actingAs($accountant)->post(route('fee-heads.import'), ['file' => $upload])
        ->assertSessionHasErrors('file');

    $this->assertDatabaseMissing('fee_heads', ['code' => 'BOOKS']);
    $this->assertDatabaseCount('fee_heads', 1);
});

test('accountants can import fee structures by academic year class and fee head', function () {
    [$accountant, $year, $schoolClass, $feeHead, $structure] = createFeeScenario();
    $transport = app(FeeService::class)->createFeeHead([
        'code' => 'TRANSPORT',
        'name' => 'Transport',
        'description' => 'School transport',
    ], $accountant);
    $dueDate = today()->addDays(10)->toDateString();
    $upload = UploadedFile::fake()->createWithContent(
        'fee-structures.csv',
        "academic_year,class_name,fee_head_code,amount,due_date\n".
        "{$year->name},{$schoolClass->name},TUITION,125.00,{$dueDate}\n".
        "{$year->name},{$schoolClass->name},TRANSPORT,25.50,{$dueDate}\n",
    );

    $this->actingAs($accountant)->post(route('fee-structures.import'), ['file' => $upload])
        ->assertRedirect()
        ->assertSessionHas('status', '1 fee structures created and 1 updated.');

    expect($structure->fresh()->amount)->toBe('125.00')
        ->and(FeeStructure::query()->where('fee_head_id', $transport->id)->value('amount'))->toBe('25.50');
    $this->assertDatabaseCount('fee_structures', 2);
});

test('fee structure imports reject locked years without changing existing structures', function () {
    [$accountant, $year, $schoolClass, , $structure] = createFeeScenario();
    $year->update(['status' => AcademicYearStatus::Locked]);
    $upload = UploadedFile::fake()->createWithContent(
        'locked-fee-structures.csv',
        "academic_year,class_name,fee_head_code,amount,due_date\n".
        "{$year->name},{$schoolClass->name},TUITION,125.00,".today()->addDays(10)->toDateString()."\n",
    );

    $this->actingAs($accountant)->post(route('fee-structures.import'), ['file' => $upload])
        ->assertSessionHasErrors('file');

    expect($structure->fresh()->amount)->toBe('100.00');
    $this->assertDatabaseCount('fee_structures', 1);
});

test('accountants can export financial workbooks and spreadsheet strings are bound as text', function () {
    [$accountant, $year] = createFeeScenario();
    Excel::fake();

    $this->actingAs($accountant)->get(route('fee-heads.export'))->assertOk();
    Excel::assertDownloaded('fee-heads.xlsx', fn ($export): bool => $export instanceof FeeHeadsExport);

    $this->actingAs($accountant)->get(route('fee-structures.export', ['academic_year_id' => $year->id]))->assertOk();
    Excel::assertDownloaded('fee-structures.xlsx');

    $this->actingAs($accountant)->get(route('invoices.export', ['academic_year_id' => $year->id]))->assertOk();
    Excel::assertDownloaded('invoices.xlsx', fn ($export): bool => $export instanceof InvoicesExport);

    $this->actingAs($accountant)->get(route('payments.export', ['academic_year_id' => $year->id]))->assertOk();
    Excel::assertDownloaded('payments.xlsx', fn (PaymentsExport $export): bool => ! in_array('notes', $export->headings(), true));

    $cell = (new Spreadsheet)->getActiveSheet()->getCell('A1');
    (new FeeHeadsExport)->bindValue($cell, '=HYPERLINK("https://example.test","Open")');

    expect($cell->getDataType())->toBe(DataType::TYPE_STRING)
        ->and($cell->getValue())->toBe('=HYPERLINK("https://example.test","Open")');
});

test('users without finance permissions cannot import or export fee data', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');
    $upload = UploadedFile::fake()->createWithContent('fee-heads.csv', "code,name\nBOOKS,Books\n");

    $this->actingAs($teacher)->post(route('fee-heads.import'), ['file' => $upload])->assertForbidden();
    $this->actingAs($teacher)->post(route('fee-structures.import'), ['file' => $upload])->assertForbidden();
    $this->actingAs($teacher)->get(route('fee-heads.export'))->assertForbidden();
    $this->actingAs($teacher)->get(route('invoices.export'))->assertForbidden();
    $this->actingAs($teacher)->get(route('payments.export'))->assertForbidden();
});

test('fee structures reject cross-year classes, duplicate definitions, and locked academic years', function () {
    [$accountant, $year, $schoolClass, $feeHead, $structure] = createFeeScenario();
    $otherYear = app(AcademicYearService::class)->create([
        'name' => 'Other Fees Year',
        'start_date' => today()->subYears(2)->toDateString(),
        'end_date' => today()->subYear()->toDateString(),
    ]);
    $otherClass = $otherYear->classes()->create(['name' => 'Other Fees Class']);

    $this->actingAs($accountant)->post(route('fee-structures.store'), [
        'academic_year_id' => $year->id,
        'class_id' => $otherClass->id,
        'fee_head_id' => $feeHead->id,
        'amount' => 50,
        'due_date' => today()->addDays(10)->toDateString(),
    ])->assertSessionHasErrors('class_id');

    $this->actingAs($accountant)->post(route('fee-structures.store'), [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'fee_head_id' => $feeHead->id,
        'amount' => 50,
        'due_date' => today()->addDays(10)->toDateString(),
    ])->assertSessionHasErrors('fee_head_id');

    $year->update(['status' => AcademicYearStatus::Locked]);
    $this->actingAs($accountant)->put(route('fee-structures.update', $structure), [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'fee_head_id' => $feeHead->id,
        'amount' => 120,
        'due_date' => today()->addDays(10)->toDateString(),
    ])->assertSessionHasErrors('academic_year_id');

    expect($structure->fresh()->amount)->toBe('100.00');
});

test('invoice generation creates a complete itemized invoice once for every enrolled student', function () {
    [$accountant, $year, $schoolClass, , $structure] = createFeeScenario();
    createFeeStudent($year, $schoolClass, 'FEE-STUDENT-001');
    createFeeStudent($year, $schoolClass, 'FEE-STUDENT-002');

    $this->actingAs($accountant)->post(route('invoices.generate'), [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
    ])->assertRedirect()
        ->assertSessionHas('status', '2 invoices created and 2 fee items added.');

    $invoice = Invoice::query()->firstOrFail();
    expect($invoice->invoice_number)->toStartWith('INV-')
        ->and($invoice->status)->toBe('unpaid')
        ->and($invoice->total_amount)->toBe('100.00');
    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $invoice->id,
        'fee_structure_id' => $structure->id,
        'description' => 'Tuition',
        'amount' => 100,
    ]);
    $this->assertDatabaseCount('student_activities', 2);
    $this->assertDatabaseHas('student_activities', [
        'student_id' => $invoice->student_id,
        'actor_id' => $accountant->id,
        'academic_year_id' => $year->id,
        'event_type' => 'fee.invoice_issued',
    ]);
    $structure->update(['amount' => 150]);

    $this->actingAs($accountant)->post(route('invoices.generate'), [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
    ])->assertRedirect()
        ->assertSessionHas('status', '0 invoices created and 0 fee items added.');

    $this->assertDatabaseCount('invoices', 2);
    $this->assertDatabaseCount('invoice_items', 2);
    $this->assertDatabaseCount('student_activities', 2);
    expect($invoice->fresh()->total_amount)->toBe('100.00')
        ->and($invoice->items()->value('amount'))->toBe('100.00');

    $this->actingAs($accountant)->get(route('fees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Fees/Index')
            ->where('summary.billed', 200)
            ->where('summary.paid', 0)
            ->where('summary.outstanding', 200)
            ->where('invoices.total', 2));
});

test('accountants can collect partial and full payments but cannot overpay an invoice', function () {
    [$accountant, $year, $schoolClass] = createFeeScenario();
    [, $student] = createFeeStudent($year, $schoolClass, 'FEE-STUDENT-003');
    app(FeeService::class)->generateInvoices($year->id, $schoolClass->id, $accountant);
    $invoice = Invoice::query()->where('student_id', $student->id)->firstOrFail();
    $paymentData = [
        'amount' => '30.25',
        'payment_method' => 'cash',
        'transaction_reference' => 'FEE-REF-001',
        'payment_date' => today()->toDateString(),
    ];

    $this->actingAs($accountant)->post(route('invoices.payments.store', $invoice), $paymentData)
        ->assertRedirect();
    expect($invoice->fresh()->paid_amount)->toBe('30.25')
        ->and($invoice->fresh()->status)->toBe('partially_paid');
    $this->assertDatabaseHas('fee_payments', [
        'invoice_id' => $invoice->id,
        'amount' => '30.25',
        'received_by' => $accountant->id,
    ]);
    $paymentActivity = StudentActivity::query()
        ->where('student_id', $student->id)
        ->where('event_type', 'fee.payment_recorded')
        ->firstOrFail();
    expect($paymentActivity->academic_year_id)->toBe($year->id)
        ->and($paymentActivity->details)->toMatchArray([
            'invoice_id' => $invoice->id,
            'amount' => '30.25',
            'payment_method' => 'cash',
        ])
        ->not->toHaveKey('notes');

    $this->actingAs($accountant)->post(route('invoices.payments.store', $invoice), [
        'amount' => '69.75',
        'payment_method' => 'bank_transfer',
        'transaction_reference' => 'FEE-REF-002',
        'payment_date' => today()->toDateString(),
    ])->assertRedirect();

    $this->actingAs($accountant)->post(route('invoices.payments.store', $invoice), [
        'amount' => '0.01',
        'payment_method' => 'cash',
        'payment_date' => today()->toDateString(),
    ])->assertSessionHasErrors('amount');

    expect($invoice->fresh()->paid_amount)->toBe('100.00')
        ->and($invoice->fresh()->status)->toBe('paid');
    $this->assertDatabaseCount('fee_payments', 2);
    expect(FeePayment::query()->where('invoice_id', $invoice->id)->pluck('receipt_number')->unique())->toHaveCount(2);
    expect(StudentActivity::query()
        ->where('student_id', $student->id)
        ->where('event_type', 'fee.payment_recorded')
        ->count())
        ->toBe(2);
});

test('locked years reject invoice generation and payment changes', function () {
    [$accountant, $year, $schoolClass] = createFeeScenario();
    [, $student] = createFeeStudent($year, $schoolClass, 'FEE-STUDENT-004');
    app(FeeService::class)->generateInvoices($year->id, $schoolClass->id, $accountant);
    $invoice = Invoice::query()->where('student_id', $student->id)->firstOrFail();
    $year->update(['status' => AcademicYearStatus::Locked]);

    $this->actingAs($accountant)->post(route('invoices.generate'), [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
    ])->assertSessionHasErrors('academic_year_id');

    $this->actingAs($accountant)->post(route('invoices.payments.store', $invoice), [
        'amount' => 20,
        'payment_method' => 'cash',
        'payment_date' => today()->toDateString(),
    ])->assertSessionHasErrors('academic_year_id');

    expect($invoice->fresh()->paid_amount)->toBe('0.00');
    $this->assertDatabaseCount('fee_payments', 0);
});

test('students and parents can see only the invoices associated with their linked students', function () {
    [$accountant, $year, $schoolClass] = createFeeScenario();
    [$studentUser, $student] = createFeeStudent($year, $schoolClass, 'FEE-STUDENT-005');
    [, $otherStudent] = createFeeStudent($year, $schoolClass, 'FEE-STUDENT-006');
    app(FeeService::class)->generateInvoices($year->id, $schoolClass->id, $accountant);

    $this->actingAs($studentUser)->get(route('fees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Fees/Index')
            ->where('mode', 'student')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.studentName', 'Jordan Student')
            ->where('invoices.data.0.totalAmount', 100));

    $parent = User::factory()->create();
    $parent->assignRole('Parent');
    $guardian = Guardian::query()->create([
        'user_id' => $parent->id,
        'name' => 'Jordan Guardian',
        'phone' => '555-0101',
        'email' => $parent->email,
    ]);
    $guardian->students()->attach($student->id, [
        'relationship' => 'parent',
        'is_primary' => true,
        'lives_with_student' => true,
    ]);

    $this->actingAs($parent)->get(route('fees.index'))
        ->assertInertia(fn ($page) => $page
            ->where('mode', 'parent')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.studentName', 'Jordan Student'));

    expect(Invoice::query()->where('student_id', $otherStudent->id)->exists())->toBeTrue();
});

test('unauthorized users cannot view fees or record payments and guests are redirected', function () {
    [$accountant, $year, $schoolClass] = createFeeScenario();
    [, $student] = createFeeStudent($year, $schoolClass, 'FEE-STUDENT-007');
    app(FeeService::class)->generateInvoices($year->id, $schoolClass->id, $accountant);
    $invoice = Invoice::query()->where('student_id', $student->id)->firstOrFail();
    $user = User::factory()->create();

    $this->get(route('fees.index'))->assertRedirect(route('login'));
    $this->actingAs($user)->get(route('fees.index'))->assertForbidden();
    $this->actingAs($user)->post(route('invoices.payments.store', $invoice), [
        'amount' => 20,
        'payment_method' => 'cash',
        'payment_date' => today()->toDateString(),
    ])->assertForbidden();
});
