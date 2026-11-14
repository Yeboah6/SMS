<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSupplyRequirement;
use App\Models\Enrolment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Services\SupplyReportService;
use App\Services\SupplyRequirementService;
use App\Services\SupplySaleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** These tests build a class, section and enrolments. If your tables need more columns, add them in classWithStudents(). */
class SupplyRequirementTest extends TestCase
{
    use CreatesSupplyFixtures, RefreshDatabase;

    private AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSupplies();
        $this->year = $this->activeYear();
    }

    /** @return array{0: SchoolClass, 1: list<Student>} */
    private function classWithStudents(int $count): array
    {
        try {
            $class = SchoolClass::query()->create(['academic_year_id' => $this->year->id, 'name' => 'Class 4', 'sort_order' => 4]);
            $section = Section::query()->create(['academic_year_id' => $this->year->id, 'class_id' => $class->id, 'name' => 'A']);
            $students = [];
            for ($i = 0; $i < $count; $i++) {
                $student = $this->student();
                Enrolment::query()->create(['academic_year_id' => $this->year->id, 'student_id' => $student->id, 'class_id' => $class->id, 'section_id' => $section->id, 'enrolment_date' => '2026-09-01', 'status' => 'active']);
                $students[] = $student;
            }
        } catch (QueryException $exception) {
            $this->markTestSkipped('Adjust classWithStudents() to your class, section and enrolment columns: '.$exception->getMessage());
        }

        return [$class, $students];
    }

    public function test_requirements_show_complete_partial_and_outstanding(): void
    {
        [$class, [$ama, $kofi, $esi]] = $this->classWithStudents(3);
        $book = $this->item(50, 10.00);
        $actor = $this->actor();
        $service = app(SupplyRequirementService::class);
        $requirement = $service->create(['academic_year_id' => $this->year->id, 'class_id' => $class->id, 'item_id' => $book->id, 'quantity_per_student' => 4, 'is_required' => true], $actor);

        $buy = fn (Student $student, int $quantity) => app(SupplySaleService::class)->create(['student_id' => $student->id, 'items' => [['item_id' => $book->id, 'quantity' => $quantity]]], $actor);
        $buy($ama, 4);   // complete
        $buy($kofi, 1);  // partial
        // Esi: nothing

        $result = $service->completion($this->year, $class);
        $byName = collect($result['students'])->keyBy('studentId');

        $this->assertSame('complete', $byName[$ama->id]['lines'][$requirement->id]['status']);
        $this->assertSame('partial', $byName[$kofi->id]['lines'][$requirement->id]['status']);
        $this->assertSame(3, $byName[$kofi->id]['lines'][$requirement->id]['outstanding']);
        $this->assertSame('outstanding', $byName[$esi->id]['lines'][$requirement->id]['status']);
        $this->assertSame(['complete' => 1, 'external' => 0, 'partial' => 1, 'outstanding' => 1], $result['summary'][$requirement->id]);
    }

    public function test_interactive_completion_students_are_paginated_and_keep_class_filters(): void
    {
        [$class] = $this->classWithStudents(16);
        $actor = $this->actor();
        app(SupplyRequirementService::class)->create([
            'academic_year_id' => $this->year->id,
            'class_id' => $class->id,
            'item_id' => $this->item(50)->id,
            'quantity_per_student' => 1,
            'is_required' => true,
        ], $actor);

        $this->actingAs($actor)->get(route('supplies.requirements.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $class->id,
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Supplies/Requirements/Index')
                ->where('completion.students.total', 16)
                ->where('completion.students.per_page', 15)
                ->has('completion.students.data', 15)
                ->has('completion.students.links')
                ->where('completion.summary.'.ClassSupplyRequirement::query()->where('class_id', $class->id)->value('id').'.outstanding', 16));

        $this->actingAs($actor)->get(route('supplies.requirements.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $class->id,
            'page' => 2,
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('completion.students.current_page', 2)
                ->has('completion.students.data', 1)
                ->where('completion.students.total', 16));
    }

    public function test_returned_and_cancelled_purchases_do_not_count(): void
    {
        [$class, [$ama]] = $this->classWithStudents(1);
        $book = $this->item(50, 10.00);
        $actor = $this->actor();
        $service = app(SupplyRequirementService::class);
        $requirement = $service->create(['academic_year_id' => $this->year->id, 'class_id' => $class->id, 'item_id' => $book->id, 'quantity_per_student' => 2, 'is_required' => true], $actor);

        $sale = app(SupplySaleService::class)->create(['student_id' => $ama->id, 'items' => [['item_id' => $book->id, 'quantity' => 2]]], $actor);
        app(SupplySaleService::class)->returnItems($sale, ['items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => 1]], 'reason' => 'Return'], $actor);
        $this->assertSame('partial', $service->completion($this->year, $class)['students'][0]['lines'][$requirement->id]['status']);

        app(SupplySaleService::class)->cancel($sale->fresh(), ['reason' => 'Cancel'], $actor);
        $this->assertSame('outstanding', $service->completion($this->year, $class)['students'][0]['lines'][$requirement->id]['status']);
    }

    public function test_a_requirement_can_be_marked_as_bought_elsewhere_when_enabled(): void
    {
        [$class, [$ama]] = $this->classWithStudents(1);
        $book = $this->item(5, 10.00);
        $actor = $this->actor();
        $service = app(SupplyRequirementService::class);
        $requirement = $service->create(['academic_year_id' => $this->year->id, 'class_id' => $class->id, 'item_id' => $book->id, 'quantity_per_student' => 1, 'is_required' => true], $actor);

        $service->markExternal($requirement, $ama, 'Bought at the market', $actor);

        $line = $service->completion($this->year, $class)['students'][0];
        $this->assertSame('external', $line['lines'][$requirement->id]['status']);
        $this->assertTrue($line['complete']);

        $service->unmarkExternal($requirement, $ama, $actor);
        $this->assertSame('outstanding', $service->completion($this->year, $class)['students'][0]['lines'][$requirement->id]['status']);
    }

    public function test_external_fulfilment_is_refused_when_the_school_has_not_enabled_it(): void
    {
        config(['supplies.allow_external_fulfilment' => false]);
        [$class, [$ama]] = $this->classWithStudents(1);
        $requirement = app(SupplyRequirementService::class)->create(['academic_year_id' => $this->year->id, 'class_id' => $class->id, 'item_id' => $this->item(5)->id, 'quantity_per_student' => 1, 'is_required' => true], $this->actor());

        $this->expectException(ValidationException::class);
        app(SupplyRequirementService::class)->markExternal($requirement, $ama, null, $this->actor());
    }

    public function test_external_fulfilment_needs_its_own_permission(): void
    {
        [$class, [$ama]] = $this->classWithStudents(1);
        $requirement = app(SupplyRequirementService::class)->create(['academic_year_id' => $this->year->id, 'class_id' => $class->id, 'item_id' => $this->item(5)->id, 'quantity_per_student' => 1, 'is_required' => true], $this->actor());

        $this->expectException(AuthorizationException::class);
        app(SupplyRequirementService::class)->markExternal($requirement, $ama, null, $this->actor(['supplies.requirements.view']));
    }

    public function test_the_same_item_cannot_be_required_twice_for_a_class_and_term(): void
    {
        [$class] = $this->classWithStudents(1);
        $book = $this->item(5);
        $actor = $this->actor();
        $service = app(SupplyRequirementService::class);
        $service->create(['academic_year_id' => $this->year->id, 'class_id' => $class->id, 'item_id' => $book->id, 'quantity_per_student' => 1, 'is_required' => true], $actor);

        $this->expectException(ValidationException::class);
        $service->create(['academic_year_id' => $this->year->id, 'class_id' => $class->id, 'item_id' => $book->id, 'quantity_per_student' => 2, 'is_required' => false], $actor);
    }

    public function test_class_completion_report_gives_a_percentage(): void
    {
        [$class, [$ama, $kofi]] = $this->classWithStudents(2);
        $book = $this->item(50, 10.00);
        $actor = $this->actor();
        app(SupplyRequirementService::class)->create(['academic_year_id' => $this->year->id, 'class_id' => $class->id, 'item_id' => $book->id, 'quantity_per_student' => 1, 'is_required' => true], $actor);
        app(SupplySaleService::class)->create(['student_id' => $ama->id, 'items' => [['item_id' => $book->id, 'quantity' => 1]]], $actor);

        $rows = app(SupplyReportService::class)->requirementCompletion($this->year);

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['students']);
        $this->assertSame(1, $rows[0]['complete']);
        $this->assertEquals(50.0, $rows[0]['percent']);
    }
}
