<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Admission;
use App\Models\Book;
use App\Models\Exam;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\InventoryItem;
use App\Models\InventoryPurchase;
use App\Models\InventorySupplier;
use App\Models\Invoice;
use App\Models\Notice;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentSupplySale;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class GlobalSearchService
{
    private const RESULTS_PER_GROUP = 5;

    /**
     * @return list<array{
     *     label: string,
     *     viewAll: ?string,
     *     results: list<array{type: string, id: int, title: string, description: ?string, href: string}>
     * }>
     */
    public function search(User $user, string $term): array
    {
        $groups = [];

        if ($user->can('users.view')) {
            $this->addGroup($groups, 'Accounts', $this->accounts($term), route('users.index', ['search' => $term]));
        }

        if ($user->can('students.view')) {
            $this->addGroup($groups, 'Students', $this->students($user, $term), route('students.index', ['search' => $term]));
        }

        if ($user->can('staff.view')) {
            $this->addGroup($groups, 'Staff', $this->staff($term), route('staff.index', ['search' => $term]));
        }

        if ($user->can('academic_years.view')) {
            $this->addGroup($groups, 'Academic years', $this->academicYears($term), route('academic-years.index'));
        }

        if ($user->can('classes.view')) {
            $this->addGroup($groups, 'Classes & sections', $this->classesAndSections($user, $term), route('classes.index'));
        }

        if ($user->can('subjects.view')) {
            $this->addGroup($groups, 'Subjects', $this->subjects($user, $term), route('subjects.index', ['q' => $term]));
        }

        if ($user->can('admissions.view')) {
            $this->addGroup($groups, 'Admissions', $this->admissions($term), route('admissions.index', ['search' => $term]));
        }

        if ($user->can('exams.view') || $user->can('exams.create')) {
            $this->addGroup($groups, 'Exams', $this->exams($user, $term), route('exams.index'));
        }

        if (
            $user->can('notices.publish')
            || $user->can('notices.create')
            || $user->can('notices.view')
            || $user->can('portal.notices.view')
        ) {
            $this->addGroup($groups, 'Notices', $this->notices($user, $term), route('notices.index', ['search' => $term]));
        }

        if ($user->can('invoices.view') || $user->can('fees.view')) {
            $this->addGroup($groups, 'Invoices', $this->invoices($term), route('invoices.index', ['search' => $term]));
            $this->addGroup($groups, 'Payments', $this->payments($term), route('payments.index', ['search' => $term]));
        }

        if ($user->can('fee_heads.view')) {
            $this->addGroup($groups, 'Fee heads', $this->feeHeads($term), route('fee-heads.index', ['search' => $term]));
        }

        if ($user->can('library.view')) {
            $this->addGroup($groups, 'Library books', $this->books($term), route('library.books.index', ['search' => $term]));
        }

        if ($user->can('viewAny', InventoryItem::class)) {
            $this->addGroup($groups, 'Supplies', $this->inventoryItems($term), route('supplies.items.index', ['search' => $term]));
        }

        if ($user->can('viewAny', InventorySupplier::class)) {
            $this->addGroup($groups, 'Suppliers', $this->suppliers($term), route('supplies.suppliers.index', ['search' => $term]));
        }

        if ($user->can('viewAny', InventoryPurchase::class)) {
            $this->addGroup($groups, 'Purchases', $this->purchases($term), route('supplies.purchases.index', ['search' => $term]));
        }

        if ($user->can('viewAny', StudentSupplySale::class)) {
            $this->addGroup($groups, 'Sales', $this->sales($term), route('supplies.sales.index', ['search' => $term]));
        }

        return $groups;
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function accounts(string $term): array
    {
        $pattern = "%{$term}%";

        return User::query()
            ->with('roles:id,name')
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', $pattern)
                ->orWhere('email', 'like', $pattern)
                ->orWhereHas('roles', fn (Builder $roles) => $roles->where('name', 'like', $pattern)))
            ->orderBy('name')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $account): array => $this->result(
                'account',
                $account->id,
                $account->name,
                collect([$account->email, $account->roles->pluck('name')->join(', ')])->filter()->implode(' · ') ?: null,
                route('users.show', $account),
            ))
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function students(User $user, string $term): array
    {
        $pattern = "%{$term}%";

        return Student::query()
            ->visibleTo($user)
            ->where(fn (Builder $query) => $query
                ->where('admission_number', 'like', $pattern)
                ->orWhere('first_name', 'like', $pattern)
                ->orWhere('middle_name', 'like', $pattern)
                ->orWhere('last_name', 'like', $pattern)
                ->orWhereHas('guardians', fn (Builder $guardians) => $guardians
                    ->where('name', 'like', $pattern)
                    ->orWhere('phone', 'like', $pattern)))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'admission_number', 'first_name', 'middle_name', 'last_name'])
            ->map(fn (Student $student): array => $this->result(
                'student',
                $student->id,
                collect([$student->first_name, $student->middle_name, $student->last_name])->filter()->implode(' '),
                "Admission #{$student->admission_number}",
                route('students.show', $student),
            ))
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function staff(string $term): array
    {
        $pattern = "%{$term}%";

        return Staff::query()
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', $pattern)
                ->orWhere('staff_number', 'like', $pattern)
                ->orWhere('email', 'like', $pattern))
            ->orderBy('name')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'staff_number', 'name', 'email'])
            ->map(fn (Staff $member): array => $this->result(
                'staff',
                $member->id,
                $member->name,
                collect([$member->staff_number, $member->email])->filter()->implode(' · ') ?: null,
                route('staff.show', $member),
            ))
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function academicYears(string $term): array
    {
        return AcademicYear::query()
            ->where('name', 'like', "%{$term}%")
            ->orderByDesc('start_date')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'name', 'status'])
            ->map(fn (AcademicYear $year): array => $this->result(
                'academic-year',
                $year->id,
                $year->name,
                $year->status->value,
                route('academic-years.show', $year),
            ))
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function classes(User $user, string $term): array
    {
        $teacherStaffId = $this->teacherStaffId($user);

        return SchoolClass::query()
            ->when($teacherStaffId !== null, fn (Builder $query) => $query->whereHas(
                'sections.teacherClassAssignments',
                fn (Builder $assignments) => $assignments->where('staff_id', $teacherStaffId),
            ))
            ->where('name', 'like', "%{$term}%")
            ->orderBy('name')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'name'])
            ->map(fn (SchoolClass $schoolClass): array => $this->result(
                'class',
                $schoolClass->id,
                $schoolClass->name,
                'Class',
                route('classes.show', $schoolClass),
            ))
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function classesAndSections(User $user, string $term): array
    {
        $classes = $this->classes($user, $term);
        $sections = $this->sections($user, $term);
        $results = [];
        $count = max(count($classes), count($sections));

        for ($index = 0; $index < $count && count($results) < self::RESULTS_PER_GROUP; $index++) {
            if (isset($classes[$index])) {
                $results[] = $classes[$index];
            }

            if (isset($sections[$index]) && count($results) < self::RESULTS_PER_GROUP) {
                $results[] = $sections[$index];
            }
        }

        return $results;
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function sections(User $user, string $term): array
    {
        $teacherStaffId = $this->teacherStaffId($user);

        return Section::query()
            ->with('schoolClass:id,name')
            ->when($teacherStaffId !== null, fn (Builder $query) => $query->whereHas(
                'teacherClassAssignments',
                fn (Builder $assignments) => $assignments->where('staff_id', $teacherStaffId),
            ))
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhereHas('schoolClass', fn (Builder $classes) => $classes->where('name', 'like', "%{$term}%")))
            ->orderBy('name')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'class_id', 'name'])
            ->map(fn (Section $section): array => $this->result(
                'section',
                $section->id,
                $section->name,
                "{$section->schoolClass->name} · Section",
                route('sections.show', $section),
            ))
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function subjects(User $user, string $term): array
    {
        $teacherStaffId = $this->teacherStaffId($user);

        return Subject::query()
            ->when($teacherStaffId !== null, fn (Builder $query) => $query->whereHas(
                'teacherSubjectAssignments',
                fn (Builder $assignments) => $assignments->where('staff_id', $teacherStaffId),
            ))
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'name', 'code'])
            ->map(fn (Subject $subject): array => $this->result(
                'subject',
                $subject->id,
                $subject->name,
                $subject->code,
                route('subjects.show', $subject),
            ))
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function admissions(string $term): array
    {
        $pattern = "%{$term}%";

        return Admission::query()
            ->where(fn (Builder $query) => $query
                ->where('application_number', 'like', $pattern)
                ->orWhere('parent_name', 'like', $pattern)
                ->orWhere('parent_phone', 'like', $pattern)
                ->orWhere('child_first_name', 'like', $pattern)
                ->orWhere('child_last_name', 'like', $pattern))
            ->latest()
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'application_number', 'child_first_name', 'child_middle_name', 'child_last_name', 'status'])
            ->map(fn (Admission $admission): array => $this->result(
                'admission',
                $admission->id,
                collect([$admission->child_first_name, $admission->child_middle_name, $admission->child_last_name])->filter()->implode(' '),
                "{$admission->application_number} · ".str_replace('_', ' ', ucfirst($admission->status)),
                route('admissions.show', $admission),
            ))
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function exams(User $user, string $term): array
    {
        $teacherStaffId = $user->hasRole('Teacher') && ! $user->can('exams.create')
            ? (int) Staff::query()->where('user_id', $user->id)->where('status', 'active')->where('is_teacher', true)->value('id')
            : null;

        return Exam::query()
            ->with(['schoolClass:id,name', 'term:id,name'])
            ->when($teacherStaffId !== null, fn (Builder $query) => $query->whereHas(
                'subjects',
                fn (Builder $subjects) => $subjects->whereHas(
                    'subject.teacherSubjectAssignments',
                    fn (Builder $assignments) => $assignments
                        ->whereColumn('teacher_subject_assignments.academic_year_id', 'exams.academic_year_id')
                        ->where('teacher_subject_assignments.staff_id', $teacherStaffId)
                        ->whereHas('section', fn (Builder $sections) => $sections
                            ->whereColumn('sections.class_id', 'exams.class_id')
                            ->whereColumn('sections.academic_year_id', 'exams.academic_year_id')),
                ),
            ))
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('exam_type', 'like', "%{$term}%")
                ->orWhereHas('schoolClass', fn (Builder $classes) => $classes->where('name', 'like', "%{$term}%"))
                ->orWhereHas('term', fn (Builder $terms) => $terms->where('name', 'like', "%{$term}%")))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'class_id', 'term_id', 'name', 'status'])
            ->map(fn (Exam $exam): array => $this->result(
                'exam',
                $exam->id,
                $exam->name,
                collect([$exam->schoolClass->name, $exam->term?->name, ucfirst($exam->status)])->filter()->implode(' · '),
                route('exams.show', $exam),
            ))
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function notices(User $user, string $term): array
    {
        return Notice::query()
            ->visibleTo($user)
            ->when(! $user->can('notices.publish'), fn (Builder $query) => $query->where(
                fn (Builder $visible) => $visible->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', today()->toDateString()),
            ))
            ->where(fn (Builder $query) => $query
                ->where('title', 'like', "%{$term}%")
                ->orWhere('body', 'like', "%{$term}%"))
            ->orderByRaw("CASE WHEN status = 'draft' THEN 0 ELSE 1 END")
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'title', 'status'])
            ->map(fn (Notice $notice): array => $this->result(
                'notice',
                $notice->id,
                $notice->title,
                ucfirst($notice->status),
                route('notices.show', $notice),
            ))
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function invoices(string $term): array
    {
        $pattern = "%{$term}%";

        return Invoice::query()
            ->with('student:id,first_name,middle_name,last_name,admission_number')
            ->where(fn (Builder $query) => $query
                ->where('invoice_number', 'like', $pattern)
                ->orWhereHas('student', fn (Builder $students) => $students
                    ->where('admission_number', 'like', $pattern)
                    ->orWhere('first_name', 'like', $pattern)
                    ->orWhere('middle_name', 'like', $pattern)
                    ->orWhere('last_name', 'like', $pattern)))
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'student_id', 'invoice_number', 'status'])
            ->map(fn (Invoice $invoice): array => $this->result(
                'invoice',
                $invoice->id,
                $invoice->invoice_number,
                collect([
                    collect([
                        $invoice->student?->first_name,
                        $invoice->student?->middle_name,
                        $invoice->student?->last_name,
                    ])->filter()->implode(' '),
                    ucfirst($invoice->status),
                ])->filter()->implode(' · '),
                route('invoices.show', $invoice),
            ))
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function payments(string $term): array
    {
        $pattern = "%{$term}%";

        return FeePayment::query()
            ->with('invoice:id,student_id,invoice_number')
            ->where(fn (Builder $query) => $query
                ->where('receipt_number', 'like', $pattern)
                ->orWhere('transaction_reference', 'like', $pattern)
                ->orWhereHas('invoice', fn (Builder $invoices) => $invoices
                    ->where('invoice_number', 'like', $pattern)
                    ->orWhereHas('student', fn (Builder $students) => $students
                        ->where('admission_number', 'like', $pattern)
                        ->orWhere('first_name', 'like', $pattern)
                        ->orWhere('last_name', 'like', $pattern))))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'invoice_id', 'receipt_number', 'payment_date'])
            ->map(fn (FeePayment $payment): array => $this->result(
                'payment',
                $payment->id,
                $payment->receipt_number,
                collect([$payment->invoice?->invoice_number, $payment->payment_date?->toDateString()])->filter()->implode(' · '),
                route('payments.show', $payment),
            ))
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function feeHeads(string $term): array
    {
        $pattern = "%{$term}%";

        return FeeHead::query()
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', $pattern)
                ->orWhere('code', 'like', $pattern))
            ->orderBy('name')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'code', 'name'])
            ->map(fn (FeeHead $feeHead): array => $this->result(
                'fee-head',
                $feeHead->id,
                $feeHead->name,
                $feeHead->code,
                route('fee-heads.show', $feeHead),
            ))
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function books(string $term): array
    {
        $pattern = "%{$term}%";

        return Book::query()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->where('title', 'like', $pattern)
                ->orWhere('author', 'like', $pattern)
                ->orWhere('isbn', 'like', $pattern))
            ->orderBy('title')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'title', 'author', 'isbn'])
            ->map(fn (Book $book): array => $this->result(
                'book',
                $book->id,
                $book->title,
                collect([$book->author, $book->isbn])->filter()->implode(' · ') ?: null,
                route('library.books.show', $book),
            ))
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function inventoryItems(string $term): array
    {
        return InventoryItem::query()
            ->active()
            ->search($term)
            ->orderBy('name')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'name', 'sku'])
            ->map(fn (InventoryItem $item): array => $this->result(
                'inventory-item',
                $item->id,
                $item->name,
                $item->sku,
                route('supplies.items.show', $item),
            ))
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function suppliers(string $term): array
    {
        $pattern = "%{$term}%";

        return InventorySupplier::query()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', $pattern)
                ->orWhere('contact_person', 'like', $pattern))
            ->orderBy('name')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'name'])
            ->map(fn (InventorySupplier $supplier): array => $this->result(
                'supplier',
                $supplier->id,
                $supplier->name,
                'Supplier',
                route('supplies.suppliers.show', $supplier),
            ))
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function purchases(string $term): array
    {
        $pattern = "%{$term}%";

        return InventoryPurchase::query()
            ->with('supplier:id,name')
            ->where(fn (Builder $query) => $query
                ->where('purchase_number', 'like', $pattern)
                ->orWhere('reference', 'like', $pattern)
                ->orWhereHas('supplier', fn (Builder $suppliers) => $suppliers->where('name', 'like', $pattern)))
            ->latest('purchase_date')
            ->latest('id')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'supplier_id', 'purchase_number', 'status'])
            ->map(fn (InventoryPurchase $purchase): array => $this->result(
                'purchase',
                $purchase->id,
                $purchase->purchase_number,
                collect([$purchase->supplier?->name, ucfirst($purchase->status)])->filter()->implode(' · '),
                route('supplies.purchases.show', $purchase),
            ))
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, description: ?string, href: string}>
     */
    private function sales(string $term): array
    {
        $pattern = "%{$term}%";

        return StudentSupplySale::query()
            ->with('student:id,first_name,middle_name,last_name,admission_number')
            ->where(fn (Builder $query) => $query
                ->where('sale_number', 'like', $pattern)
                ->orWhereHas('student', fn (Builder $students) => $students
                    ->where('admission_number', 'like', $pattern)
                    ->orWhere('first_name', 'like', $pattern)
                    ->orWhere('middle_name', 'like', $pattern)
                    ->orWhere('last_name', 'like', $pattern)))
            ->latest('sold_at')
            ->latest('id')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'student_id', 'sale_number', 'status'])
            ->map(fn (StudentSupplySale $sale): array => $this->result(
                'sale',
                $sale->id,
                $sale->sale_number,
                collect([
                    collect([
                        $sale->student?->first_name,
                        $sale->student?->middle_name,
                        $sale->student?->last_name,
                    ])->filter()->implode(' '),
                    ucfirst(str_replace('_', ' ', $sale->status)),
                ])->filter()->implode(' · '),
                route('supplies.sales.show', $sale),
            ))
            ->all();
    }

    private function teacherStaffId(User $user): ?int
    {
        if (! $user->hasRole('Teacher') || $user->hasRole('Super Admin')) {
            return null;
        }

        return (int) Staff::query()->where('user_id', $user->id)->value('id');
    }

    /**
     * @param  list<array{label: string, viewAll: ?string, results: list<array{type: string, id: int, title: string, description: ?string, href: string}>}>  $groups
     * @param  list<array{type: string, id: int, title: string, description: ?string, href: string}>  $results
     */
    private function addGroup(array &$groups, string $label, array $results, ?string $viewAll): void
    {
        if ($results !== []) {
            $groups[] = [
                'label' => $label,
                'viewAll' => $viewAll,
                'results' => $results,
            ];
        }
    }

    /**
     * @return array{type: string, id: int, title: string, description: ?string, href: string}
     */
    private function result(string $type, int $id, string $title, ?string $description, string $href): array
    {
        return compact('type', 'id', 'title', 'description', 'href');
    }
}
