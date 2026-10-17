<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\ClassSupplyExternalFulfilment;
use App\Models\ClassSupplyRequirement;
use App\Models\Enrolment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentSupplySaleItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** What each class should have, and how far each student has got. Buying from the school is never assumed. */
class SupplyRequirementService
{
    public function create(array $data, User $actor): ClassSupplyRequirement
    {
        if (! $actor->can('supplies.requirements.manage')) {
            throw new AuthorizationException('You are not allowed to manage class supply requirements.');
        }

        $this->assertNotDuplicate($data);

        return ClassSupplyRequirement::query()->create([...Arr::only($data, ['academic_year_id', 'class_id', 'term_id', 'item_id', 'quantity_per_student', 'is_required', 'notes']), 'created_by' => $actor->id]);
    }

    public function update(ClassSupplyRequirement $requirement, array $data, User $actor): ClassSupplyRequirement
    {
        if (! $actor->can('supplies.requirements.manage')) {
            throw new AuthorizationException('You are not allowed to manage class supply requirements.');
        }

        $merged = [...$requirement->only(['academic_year_id', 'class_id', 'term_id', 'item_id']), ...Arr::only($data, ['term_id', 'item_id'])];
        $this->assertNotDuplicate($merged, $requirement->id);

        $requirement->update(Arr::only($data, ['term_id', 'item_id', 'quantity_per_student', 'is_required', 'notes']));

        return $requirement->refresh();
    }

    public function delete(ClassSupplyRequirement $requirement, User $actor): void
    {
        if (! $actor->can('supplies.requirements.manage')) {
            throw new AuthorizationException('You are not allowed to manage class supply requirements.');
        }

        $requirement->delete(); // external markings go with it (cascade); sales are untouched
    }

    public function markExternal(ClassSupplyRequirement $requirement, Student $student, ?string $notes, User $actor): ClassSupplyExternalFulfilment
    {
        $this->assertExternalAllowed($actor);

        return ClassSupplyExternalFulfilment::query()->updateOrCreate(
            ['requirement_id' => $requirement->id, 'student_id' => $student->id],
            ['marked_by' => $actor->id, 'notes' => $notes],
        );
    }

    public function unmarkExternal(ClassSupplyRequirement $requirement, Student $student, User $actor): void
    {
        $this->assertExternalAllowed($actor);

        ClassSupplyExternalFulfilment::query()->where('requirement_id', $requirement->id)->where('student_id', $student->id)->delete();
    }

    /**
     * Required vs bought for every active student of a class in a year (or one student).
     * Quantities bought count sales that were not cancelled, net of returns; a term-specific requirement only
     * counts purchases made during that term.
     *
     * @return array{requirements: list<array<string, mixed>>, students: list<array<string, mixed>>, summary: array<int, array<string, int>>}
     */
    public function completion(AcademicYear $year, SchoolClass $class, ?int $onlyStudentId = null): array
    {
        $requirements = ClassSupplyRequirement::query()
            ->with(['item', 'term'])
            ->where('academic_year_id', $year->id)
            ->where('class_id', $class->id)
            ->orderByDesc('is_required')
            ->orderBy('item_id')
            ->get();

        $enrolments = Enrolment::query()
            ->with('student:id,first_name,middle_name,last_name,admission_number')
            ->where('academic_year_id', $year->id)
            ->where('class_id', $class->id)
            ->where('status', 'active')
            ->when($onlyStudentId, fn ($query) => $query->where('student_id', $onlyStudentId))
            ->get()
            ->unique('student_id')
            ->values();

        $studentIds = $enrolments->pluck('student_id');
        $yearWide = $this->purchased($studentIds, $year->id);

        $termWide = [];
        foreach ($requirements->whereNotNull('term_id')->pluck('term')->unique('id') as $term) {
            $termWide[$term->id] = $this->purchased($studentIds, $year->id, $term->start_date->copy()->startOfDay(), $term->end_date->copy()->endOfDay());
        }

        $external = ClassSupplyExternalFulfilment::query()
            ->whereIn('requirement_id', $requirements->pluck('id'))
            ->whereIn('student_id', $studentIds)
            ->get()
            ->groupBy('requirement_id')
            ->map(fn ($rows) => $rows->pluck('student_id')->all());

        $summary = $requirements->mapWithKeys(fn ($requirement): array => [$requirement->id => ['complete' => 0, 'external' => 0, 'partial' => 0, 'outstanding' => 0]])->all();

        $students = $enrolments->map(function (Enrolment $enrolment) use ($requirements, $yearWide, $termWide, $external, &$summary): array {
            $studentId = $enrolment->student_id;
            $lines = [];
            $complete = true;

            foreach ($requirements as $requirement) {
                $required = $requirement->quantity_per_student;
                $bought = (int) ($requirement->term_id
                    ? ($termWide[$requirement->term_id][$studentId][$requirement->item_id] ?? 0)
                    : ($yearWide[$studentId][$requirement->item_id] ?? 0));
                $isExternal = in_array($studentId, $external->get($requirement->id, []), true);
                $status = $isExternal ? 'external' : ($bought >= $required ? 'complete' : ($bought > 0 ? 'partial' : 'outstanding'));

                $summary[$requirement->id][$status]++;
                if ($requirement->is_required && ! in_array($status, ['complete', 'external'], true)) {
                    $complete = false;
                }

                $lines[$requirement->id] = ['required' => $required, 'purchased' => $bought, 'outstanding' => max(0, $required - $bought), 'status' => $status];
            }

            $student = $enrolment->student;

            return [
                'studentId' => $studentId,
                'name' => trim(implode(' ', array_filter([$student->first_name, $student->middle_name, $student->last_name]))),
                'admissionNumber' => $student->admission_number,
                'lines' => $lines,
                'complete' => $complete,
            ];
        })->all();

        return [
            'requirements' => $requirements->map(fn (ClassSupplyRequirement $requirement): array => [
                'id' => $requirement->id,
                'itemId' => $requirement->item_id,
                'itemName' => $requirement->item->name,
                'sku' => $requirement->item->sku,
                'unit' => $requirement->item->unit,
                'price' => (float) $requirement->item->selling_price,
                'quantity' => $requirement->quantity_per_student,
                'isRequired' => $requirement->is_required,
                'termId' => $requirement->term_id,
                'termName' => $requirement->term?->name,
            ])->all(),
            'students' => $students,
            'summary' => $summary,
        ];
    }

    // ------------------------------------------------------------------ helpers

    /** @return array<int, array<int, int>> studentId => itemId => units kept */
    private function purchased($studentIds, int $yearId, $from = null, $to = null): array
    {
        if ($studentIds->isEmpty()) {
            return [];
        }

        $rows = StudentSupplySaleItem::query()
            ->join('student_supply_sales as s', 's.id', '=', 'student_supply_sale_items.sale_id')
            ->whereIn('s.student_id', $studentIds)
            ->where('s.academic_year_id', $yearId)
            ->where('s.status', '!=', 'cancelled')
            ->when($from && $to, fn ($query) => $query->whereBetween('s.sold_at', [$from, $to]))
            ->groupBy('s.student_id', 'student_supply_sale_items.item_id')
            ->selectRaw('s.student_id as student_id, student_supply_sale_items.item_id as item_id, SUM(student_supply_sale_items.quantity - student_supply_sale_items.returned_quantity) as qty')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->student_id][$row->item_id] = (int) $row->qty;
        }

        return $map;
    }

    /** A NULL term never collides in the unique index, so "same item, same class, no term" is checked here. */
    private function assertNotDuplicate(array $data, ?int $ignoreId = null): void
    {
        $exists = ClassSupplyRequirement::query()
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('class_id', $data['class_id'])
            ->where('item_id', $data['item_id'])
            ->when($data['term_id'] ?? null, fn ($query, $termId) => $query->where('term_id', $termId), fn ($query) => $query->whereNull('term_id'))
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['item_id' => 'This class already has a requirement for that item in the same term.']);
        }
    }

    private function assertExternalAllowed(User $actor): void
    {
        if (! config('supplies.allow_external_fulfilment')) {
            throw ValidationException::withMessages(['requirement' => 'Marking requirements as bought elsewhere is not enabled.']);
        }
        if (! $actor->can('supplies.requirements.fulfil_external')) {
            throw new AuthorizationException('You are not allowed to mark requirements as externally fulfilled.');
        }
    }
}