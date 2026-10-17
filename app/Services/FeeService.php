<?php

namespace App\Services;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FeeService
{
    public function __construct(private StudentActivityService $activities) {}

    /**
     * @param  array{code: string, name: string, description?: ?string, is_active?: bool}  $data
     */
    public function createFeeHead(array $data, User $actor): FeeHead
    {
        $this->authorize($actor, 'fee_heads.create');

        return FeeHead::query()->create([
            ...$data,
            'created_by' => $actor->id,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * @param  array{code: string, name: string, description?: ?string, is_active?: bool}  $data
     */
    public function updateFeeHead(FeeHead $feeHead, array $data, User $actor): FeeHead
    {
        $this->authorize($actor, 'fee_heads.update');

        $feeHead->update($data);

        return $feeHead->refresh();
    }

    /**
     * @param  array{academic_year_id: int, class_id: int, fee_head_id: int, amount: numeric-string|int|float, due_date: string}  $data
     */
    public function createStructure(array $data, User $actor): FeeStructure
    {
        $this->authorize($actor, 'fee_structures.create');

        return DB::transaction(function () use ($data): FeeStructure {
            [$year, $class, $feeHead] = $this->resolveStructureScope($data);

            $this->ensureYearEditable($year);
            $this->validateDueDate($data['due_date'], $year);

            return FeeStructure::query()->create([
                ...$data,
                'academic_year_id' => $year->id,
                'class_id' => $class->id,
                'fee_head_id' => $feeHead->id,
            ]);
        });
    }

    /**
     * @param  array{academic_year_id: int, class_id: int, fee_head_id: int, amount: numeric-string|int|float, due_date: string}  $data
     */
    public function updateStructure(
        FeeStructure $feeStructure,
        array $data,
        User $actor,
    ): FeeStructure {
        $this->authorize($actor, 'fee_structures.update');

        return DB::transaction(function () use ($feeStructure, $data): FeeStructure {
            $structure = FeeStructure::query()->lockForUpdate()->findOrFail($feeStructure->id);
            [$year, $class, $feeHead] = $this->resolveStructureScope($data);

            $this->ensureYearEditable($year);
            if ($year->id !== $structure->academic_year_id) {
                throw ValidationException::withMessages([
                    'academic_year_id' => 'A fee structure cannot be moved to a different academic year.',
                ]);
            }
            $this->validateDueDate($data['due_date'], $year);

            $structure->update([
                ...$data,
                'class_id' => $class->id,
                'fee_head_id' => $feeHead->id,
            ]);

            return $structure->refresh();
        });
    }

    /**
     * @return array{invoices_created: int, items_created: int}
     */
    public function generateInvoices(int $yearId, int $classId, User $actor): array
    {
        $this->authorize($actor, 'invoices.create');

        return DB::transaction(function () use ($yearId, $classId, $actor): array {
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($yearId);
            $this->ensureYearEditable($year);

            $class = $year->classes()->lockForUpdate()->find($classId);
            if (! $class) {
                throw ValidationException::withMessages([
                    'class_id' => 'Select a class in the selected academic year.',
                ]);
            }

            $structures = FeeStructure::query()
                ->with('feeHead:id,name')
                ->where('academic_year_id', $year->id)
                ->where('class_id', $class->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($structures->isEmpty()) {
                throw ValidationException::withMessages([
                    'class_id' => 'Create at least one fee structure for this class before generating invoices.',
                ]);
            }

            $invoicesCreated = 0;
            $itemsCreated = 0;
            $enrolments = Enrolment::query()
                ->where('academic_year_id', $year->id)
                ->where('class_id', $class->id)
                ->whereIn('status', ['active', 'completed'])
                ->orderBy('student_id')
                ->with('student:id')
                ->get();

            foreach ($enrolments as $enrolment) {
                $result = $this->createInvoiceForEnrolment($year, $enrolment, $structures, $actor);
                $invoicesCreated += $result['invoices_created'];
                $itemsCreated += $result['items_created'];
            }

            return [
                'invoices_created' => $invoicesCreated,
                'items_created' => $itemsCreated,
            ];
        });
    }

    public function assignStudentFees(int $yearId, int $classId, int $studentId, User $actor): Invoice
    {
        $this->authorize($actor, 'invoices.create');

        return DB::transaction(function () use ($yearId, $classId, $studentId, $actor): Invoice {
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($yearId);
            $this->ensureYearEditable($year);

            $class = $year->classes()->lockForUpdate()->find($classId);
            if (! $class) {
                throw ValidationException::withMessages([
                    'class_id' => 'Select a class in the selected academic year.',
                ]);
            }

            $enrolment = Enrolment::query()
                ->with('student:id')
                ->where('academic_year_id', $year->id)
                ->where('class_id', $class->id)
                ->where('student_id', $studentId)
                ->whereIn('status', ['active', 'completed'])
                ->first();
            if (! $enrolment || ! Student::query()->whereKey($studentId)->exists()) {
                throw ValidationException::withMessages([
                    'class_id' => 'The student must be enrolled in the selected class and academic year.',
                ]);
            }

            $structures = FeeStructure::query()
                ->with('feeHead:id,name')
                ->where('academic_year_id', $year->id)
                ->where('class_id', $class->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($structures->isEmpty()) {
                throw ValidationException::withMessages([
                    'assign_fees' => 'There are no configured fees for this class and academic year.',
                ]);
            }

            $this->createInvoiceForEnrolment($year, $enrolment, $structures, $actor);

            return Invoice::query()
                ->where('academic_year_id', $year->id)
                ->where('student_id', $studentId)
                ->firstOrFail();
        });
    }

    /**
     * @param  array{amount: numeric-string|int|float, payment_method: string, transaction_reference?: ?string, payment_date: string, notes?: ?string}  $data
     */
    public function recordPayment(Invoice $invoice, array $data, User $actor): FeePayment
    {
        $this->authorize($actor, 'payments.create');

        return DB::transaction(function () use ($invoice, $data, $actor): FeePayment {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($lockedInvoice->academic_year_id);
            $this->ensureYearEditable($year);

            $amount = $this->minorUnits($data['amount']);
            $total = $this->minorUnits($lockedInvoice->total_amount);
            $paid = $this->minorUnits($lockedInvoice->paid_amount);
            if ($amount < 1 || $amount > $total - $paid) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment must be greater than zero and cannot exceed the outstanding balance.',
                ]);
            }

            $payment = FeePayment::query()->create([
                'invoice_id' => $lockedInvoice->id,
                'received_by' => $actor->id,
                'receipt_number' => 'TEMP-'.Str::uuid(),
                'payment_method' => $data['payment_method'],
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'],
                'notes' => $data['notes'] ?? null,
            ]);
            $payment->update([
                'receipt_number' => sprintf('RCPT-%06d-%08d', $year->id, $payment->id),
            ]);

            $newPaidAmount = $paid + $amount;
            $lockedInvoice->update([
                'paid_amount' => $this->decimalAmount($newPaidAmount),
                'status' => $this->invoiceStatus($total, $newPaidAmount),
            ]);

            $student = Student::query()->findOrFail($lockedInvoice->student_id);
            $this->activities->record(
                $student,
                $actor,
                'fee.payment_recorded',
                'Payment of '.$this->decimalAmount($amount).' recorded for invoice '.$lockedInvoice->invoice_number.'.',
                $year,
                [
                    'invoice_id' => $lockedInvoice->id,
                    'invoice_number' => $lockedInvoice->invoice_number,
                    'payment_id' => $payment->id,
                    'receipt_number' => $payment->receipt_number,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'payment_date' => $payment->payment_date->toDateString(),
                    'invoice_status' => $lockedInvoice->status,
                ],
            );

            return $payment->refresh();
        });
    }

    /**
     * @param  array{academic_year_id: int, class_id: int, fee_head_id: int, amount: numeric-string|int|float, due_date: string}  $data
     * @return array{AcademicYear, SchoolClass, FeeHead}
     */
    private function resolveStructureScope(array $data): array
    {
        $year = AcademicYear::query()->lockForUpdate()->findOrFail($data['academic_year_id']);
        $class = $year->classes()->lockForUpdate()->find($data['class_id']);
        if (! $class) {
            throw ValidationException::withMessages([
                'class_id' => 'Select a class in the selected academic year.',
            ]);
        }

        $feeHead = FeeHead::query()->where('is_active', true)->find($data['fee_head_id']);
        if (! $feeHead) {
            throw ValidationException::withMessages([
                'fee_head_id' => 'Select an active fee head.',
            ]);
        }

        return [$year, $class, $feeHead];
    }

    private function validateDueDate(string $dueDate, AcademicYear $year): void
    {
        $date = Carbon::parse($dueDate);
        if ($date->lt($year->start_date) || $date->gt($year->end_date)) {
            throw ValidationException::withMessages([
                'due_date' => 'The due date must fall within the selected academic year.',
            ]);
        }
    }

    private function ensureYearEditable(AcademicYear $year): void
    {
        if ($year->status === AcademicYearStatus::Locked) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'Fees cannot be changed in a locked academic year.',
            ]);
        }
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException('You are not allowed to manage this fee operation.');
        }
    }

    /**
     * @param  Collection<int, FeeStructure>  $structures
     * @return array{invoices_created: int, items_created: int}
     */
    private function createInvoiceForEnrolment(
        AcademicYear $year,
        Enrolment $enrolment,
        Collection $structures,
        User $actor,
    ): array {
        $invoice = Invoice::query()->firstOrCreate(
            [
                'academic_year_id' => $year->id,
                'student_id' => $enrolment->student_id,
            ],
            [
                'invoice_number' => sprintf('INV-%06d-%08d', $year->id, $enrolment->student_id),
                'status' => 'unpaid',
                'total_amount' => 0,
                'paid_amount' => 0,
                'issued_at' => today(),
                'due_date' => $structures->min('due_date'),
            ],
        );
        $invoiceWasCreated = $invoice->wasRecentlyCreated;
        $itemsCreated = 0;

        foreach ($structures as $structure) {
            $item = InvoiceItem::query()->firstOrCreate(
                [
                    'invoice_id' => $invoice->id,
                    'fee_structure_id' => $structure->id,
                ],
                [
                    'fee_head_id' => $structure->fee_head_id,
                    'description' => $structure->feeHead->name,
                    'amount' => $structure->amount,
                    'due_date' => $structure->due_date,
                ],
            );

            if ($item->wasRecentlyCreated) {
                $itemsCreated++;
                $invoice->increment('total_amount', $item->amount);
                if ($item->due_date->lt($invoice->due_date)) {
                    $invoice->update(['due_date' => $item->due_date]);
                }
            }
        }

        $invoice->refresh()->update([
            'status' => $this->invoiceStatus(
                $this->minorUnits($invoice->total_amount),
                $this->minorUnits($invoice->paid_amount),
            ),
        ]);

        if ($invoiceWasCreated) {
            $this->activities->record(
                $enrolment->student,
                $actor,
                'fee.invoice_issued',
                'Invoice '.$invoice->invoice_number.' issued.',
                $year,
                [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'total_amount' => $invoice->total_amount,
                    'due_date' => $invoice->due_date->toDateString(),
                ],
            );
        }

        return [
            'invoices_created' => $invoice->wasRecentlyCreated ? 1 : 0,
            'items_created' => $itemsCreated,
        ];
    }

    private function minorUnits(int|float|string $amount): int
    {
        $normalized = is_float($amount) ? number_format($amount, 2, '.', '') : (string) $amount;
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return ((int) $whole * 100) + (int) $fraction;
    }

    private function decimalAmount(int $minorUnits): string
    {
        return sprintf('%d.%02d', intdiv($minorUnits, 100), $minorUnits % 100);
    }

    private function invoiceStatus(int $total, int $paid): string
    {
        if ($paid >= $total) {
            return 'paid';
        }

        return $paid > 0 ? 'partially_paid' : 'unpaid';
    }
}
