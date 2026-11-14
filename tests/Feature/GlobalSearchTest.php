<?php

use App\Models\AcademicYear;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\InventoryPurchase;
use App\Models\InventorySupplier;
use App\Models\Invoice;
use App\Models\Notice;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentSupplySale;
use App\Models\User;
use App\Services\StudentProfileService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SuppliesPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function createGlobalSearchStudent(string $admissionNumber, string $firstName, string $lastName): Student
{
    return app(StudentProfileService::class)->create([
        'admission_number' => $admissionNumber,
        'first_name' => $firstName,
        'middle_name' => null,
        'last_name' => $lastName,
        'date_of_birth' => today()->subYears(10)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'blood_group' => null,
        'religion' => null,
        'address' => null,
        'admission_date' => today()->toDateString(),
        'status' => 'active',
        'guardians' => [],
    ]);
}

function createGlobalSearchAcademicYear(): AcademicYear
{
    return AcademicYear::query()->create([
        'name' => 'Global Search Year',
        'start_date' => '2025-09-01',
        'end_date' => '2026-07-31',
        'status' => 'draft',
    ]);
}

test('requires authentication and a two-character search query', function () {
    $this->getJson(route('global-search', ['q' => 'Ad']))
        ->assertUnauthorized();

    $viewer = User::factory()->create();

    $this->actingAs($viewer)->getJson(route('global-search', ['q' => 'A']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
});

test('searches accounts by name, email, and role while returning permitted people records', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $account = User::factory()->create([
        'name' => 'Avery Account',
        'email' => 'avery.account@example.test',
    ]);
    $account->assignRole('Accountant');
    createGlobalSearchStudent('SEARCH-AVERY', 'Avery', 'Student');

    $response = $this->actingAs($admin)->getJson(route('global-search', ['q' => 'Avery']));

    $response->assertOk()
        ->assertJsonFragment([
            'type' => 'account',
            'title' => 'Avery Account',
            'href' => route('users.show', $account),
        ])
        ->assertJsonFragment([
            'type' => 'student',
            'title' => 'Avery Student',
            'href' => route('students.show', Student::query()->where('admission_number', 'SEARCH-AVERY')->firstOrFail()),
        ]);

    $this->getJson(route('global-search', ['q' => 'avery.account@example.test']))
        ->assertOk()
        ->assertJsonFragment([
            'type' => 'account',
            'title' => 'Avery Account',
        ]);
});

test('finds accounts by assigned role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $account = User::factory()->create(['name' => 'Finance Account']);
    $account->assignRole('Accountant');

    $this->actingAs($admin)->getJson(route('global-search', ['q' => 'Accountant']))
        ->assertOk()
        ->assertJsonFragment([
            'type' => 'account',
            'title' => 'Finance Account',
            'description' => $account->email.' · Accountant',
        ]);
});

test('does not expose accounts or student records to portal users', function () {
    $studentUser = User::factory()->create();
    $studentUser->assignRole('Student');
    User::factory()->create(['name' => 'Private Search Account']);
    createGlobalSearchStudent('PRIVATE-SEARCH', 'Private', 'Student');

    $this->actingAs($studentUser)->getJson(route('global-search', ['q' => 'Private']))
        ->assertOk()
        ->assertExactJson(['groups' => []]);
});

test('limits notice results to the current audience and the dropdown result cap', function () {
    $student = User::factory()->create();
    $student->assignRole('Student');

    foreach (range(1, 6) as $index) {
        Notice::factory()->published()->create([
            'title' => "Quarterly update {$index}",
            'audience' => 'students',
        ]);
    }

    Notice::factory()->published()->create([
        'title' => 'Quarterly update for parents',
        'audience' => 'parents',
    ]);
    Notice::factory()->create([
        'title' => 'Quarterly update draft',
        'audience' => 'students',
    ]);
    Notice::factory()->published()->create([
        'title' => 'Quarterly update expired',
        'audience' => 'students',
        'expires_at' => today()->subDay(),
    ]);

    $response = $this->actingAs($student)->getJson(route('global-search', ['q' => 'Quarterly update']));

    $response->assertOk();
    expect($response->json('groups.0.label'))->toBe('Notices')
        ->and($response->json('groups.0.results'))->toHaveCount(5)
        ->and(collect($response->json('groups.0.results'))->pluck('title')->all())
        ->not->toContain('Quarterly update for parents')
        ->not->toContain('Quarterly update draft')
        ->not->toContain('Quarterly update expired');

    $publisher = User::factory()->create();
    $publisher->assignRole('Super Admin');

    $this->actingAs($publisher)->getJson(route('global-search', ['q' => 'Quarterly update expired']))
        ->assertOk()
        ->assertJsonFragment(['title' => 'Quarterly update expired']);
});

test('searches finance records without exposing payment amounts', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $year = createGlobalSearchAcademicYear();
    $student = createGlobalSearchStudent('FINANCE-SEARCH-STUDENT', 'Finance', 'Search');
    $feeHead = FeeHead::query()->create([
        'created_by' => $admin->id,
        'code' => 'FINANCE-SEARCH-FEE',
        'name' => 'Search fee',
    ]);
    $invoice = Invoice::query()->create([
        'academic_year_id' => $year->id,
        'student_id' => $student->id,
        'invoice_number' => 'FINANCE-SEARCH-INVOICE',
        'status' => 'unpaid',
        'total_amount' => 80,
        'paid_amount' => 0,
        'issued_at' => today()->toDateString(),
        'due_date' => today()->addWeek()->toDateString(),
    ]);
    $payment = FeePayment::query()->create([
        'invoice_id' => $invoice->id,
        'received_by' => $admin->id,
        'receipt_number' => 'FINANCE-SEARCH-RECEIPT',
        'payment_method' => 'cash',
        'amount' => 35,
        'payment_date' => today()->toDateString(),
    ]);

    $response = $this->actingAs($admin)->getJson(route('global-search', ['q' => 'FINANCE-SEARCH']));

    $response->assertOk()
        ->assertJsonFragment([
            'type' => 'fee-head',
            'id' => $feeHead->id,
            'title' => 'Search fee',
            'href' => route('fee-heads.show', $feeHead),
        ])
        ->assertJsonFragment([
            'type' => 'payment',
            'id' => $payment->id,
            'title' => 'FINANCE-SEARCH-RECEIPT',
            'href' => route('payments.show', $payment),
        ]);

    $paymentResult = collect($response->json('groups'))
        ->firstWhere('label', 'Payments')['results'][0];

    expect(array_keys($paymentResult))->toBe(['type', 'id', 'title', 'description', 'href']);
});

test('searches purchases and sales only for users who can view supplies', function () {
    $this->seed(SuppliesPermissionSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $year = createGlobalSearchAcademicYear();
    $student = createGlobalSearchStudent('SUPPLY-SEARCH-STUDENT', 'Supply', 'Search');
    $supplier = InventorySupplier::factory()->create();
    $purchase = InventoryPurchase::query()->create([
        'purchase_number' => 'SUPPLY-SEARCH-PURCHASE',
        'supplier_id' => $supplier->id,
        'purchase_date' => today()->toDateString(),
        'total_cost' => 120,
        'received_by' => $admin->id,
    ]);
    $sale = StudentSupplySale::query()->create([
        'sale_number' => 'SUPPLY-SEARCH-SALE',
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'subtotal' => 45,
        'total_amount' => 45,
        'sold_by' => $admin->id,
        'sold_at' => now(),
    ]);

    $response = $this->actingAs($admin)->getJson(route('global-search', ['q' => 'SUPPLY-SEARCH']));

    $response->assertOk()
        ->assertJsonFragment([
            'type' => 'purchase',
            'id' => $purchase->id,
            'title' => 'SUPPLY-SEARCH-PURCHASE',
            'href' => route('supplies.purchases.show', $purchase),
        ])
        ->assertJsonFragment([
            'type' => 'sale',
            'id' => $sale->id,
            'title' => 'SUPPLY-SEARCH-SALE',
            'href' => route('supplies.sales.show', $sale),
        ]);

    $studentUser = User::factory()->create();
    $studentUser->assignRole('Student');

    $this->actingAs($studentUser)->getJson(route('global-search', ['q' => 'SUPPLY-SEARCH']))
        ->assertExactJson(['groups' => []]);
});

test('includes sections when class matches fill the result group', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $year = createGlobalSearchAcademicYear();

    foreach (range(1, 5) as $index) {
        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'name' => "Search Cohort {$index}",
            'sort_order' => $index,
        ]);

        if ($index === 1) {
            Section::query()->create([
                'academic_year_id' => $year->id,
                'class_id' => $schoolClass->id,
                'name' => 'A',
                'capacity' => 30,
            ]);
        }
    }

    $response = $this->actingAs($admin)->getJson(route('global-search', ['q' => 'Search Cohort']));

    $response->assertOk();
    $group = collect($response->json('groups'))->firstWhere('label', 'Classes & sections');

    expect($group['results'])->toHaveCount(5)
        ->and(collect($group['results'])->pluck('type')->all())->toContain('section');
});
