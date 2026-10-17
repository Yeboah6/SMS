<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\StudentSupplyPayment;
use App\Models\StudentSupplySale;
use App\Models\User;
use App\Services\HandlesMoney;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payments and refunds against a sale. Rows are immutable. A double submit is harmless because every form carries
 * an idempotency key, and a transaction reference can be used only once.
 */
class SupplyPaymentService
{
    use HandlesMoney;

    public function __construct(private StudentActivityService $activities) {}

    /**
     * @param  array{amount: numeric-string|float|int, payment_method: string, transaction_reference?: ?string, idempotency_key?: ?string, paid_at?: ?string, notes?: ?string}  $data
     */
    public function record(StudentSupplySale $sale, array $data, User $actor): StudentSupplyPayment
    {
        if (! $actor->can('supplies.payments.create')) {
            throw new AuthorizationException('You are not allowed to record payments.');
        }

        return DB::transaction(function () use ($sale, $data, $actor): StudentSupplyPayment {
            $sale = StudentSupplySale::query()->lockForUpdate()->findOrFail($sale->id);

            if (in_array($sale->status, ['cancelled', 'returned'], true)) {
                throw ValidationException::withMessages(['amount' => 'This sale is closed and cannot take payments.']);
            }

            $key = $data['idempotency_key'] ?? null;
            if ($key && $existing = StudentSupplyPayment::query()->where('idempotency_key', $key)->first()) {
                if ($existing->sale_id !== $sale->id || $existing->type !== StudentSupplyPayment::PAYMENT) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This submission key belongs to another transaction.']);
                }

                return $existing; // the same submission arriving twice: no second payment
            }

            $this->assertMethod($data['payment_method'] ?? '', 'payment_method');
            $amount = $this->cents($data['amount'] ?? 0);
            $balance = $this->cents($sale->netDue()) - $this->cents($sale->netPaid());

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
            }
            if ($amount > $balance) {
                throw ValidationException::withMessages(['amount' => 'The amount is more than the outstanding balance of '.$this->money($balance).'.']);
            }
            if (! config('supplies.allow_partial_payment') && $amount !== $balance) {
                throw ValidationException::withMessages(['amount' => 'Partial payments are not allowed. Pay the full balance of '.$this->money($balance).'.']);
            }

            $payment = $this->createRow($sale, StudentSupplyPayment::PAYMENT, $amount, $data['payment_method'], $data['transaction_reference'] ?? null, $key, $actor, $data['paid_at'] ?? null, $data['notes'] ?? null, 'SUPR');
            $this->syncTotals($sale);

            $this->audit($sale, $actor, 'supplies.payment_recorded', "Payment {$payment->receipt_number} of {$this->money($amount)} for supply sale {$sale->sale_number}.", ['payment_id' => $payment->id]);

            return $payment;
        });
    }

    /**
     * Pays money back. Called inside the caller's transaction (returns and cancellations), with the sale locked.
     */
    public function refund(StudentSupplySale $sale, int $amountCents, ?string $method, ?string $reference, string $reason, User $actor): StudentSupplyPayment
    {
        $this->assertMethod($method ?? '', 'refund_method');

        $refundable = $this->cents($sale->paid_amount) - $this->cents($sale->refunded_amount);
        if ($amountCents <= 0 || $amountCents > $refundable) {
            throw ValidationException::withMessages(['refund_method' => 'The refund is more than the money paid on this sale.']);
        }

        $refund = $this->createRow($sale, StudentSupplyPayment::REFUND, $amountCents, $method, $reference, null, $actor, null, $reason, 'SUPF');

        $this->audit($sale, $actor, 'supplies.refund_paid', "Refund {$refund->receipt_number} of {$this->money($amountCents)} for supply sale {$sale->sale_number}.", ['payment_id' => $refund->id]);

        return $refund;
    }

    /** Recomputes the sale's payment totals and status from its payment rows. The sale must be locked. */
    public function syncTotals(StudentSupplySale $sale): StudentSupplySale
    {
        $totals = StudentSupplyPayment::query()
            ->where('sale_id', $sale->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END), 0) as paid, COALESCE(SUM(CASE WHEN type = 'refund' THEN amount ELSE 0 END), 0) as refunded")
            ->first();

        $sale->paid_amount = $totals->paid;
        $sale->refunded_amount = $totals->refunded;
        $sale->payment_status = $this->paymentStatus($sale);
        $sale->save();

        return $sale;
    }

    public function paymentStatus(StudentSupplySale $sale): string
    {
        if ($sale->status === 'cancelled' || $this->cents($sale->netDue()) === 0) {
            return 'void';
        }

        if ($this->cents($sale->balance()) <= 0) {
            return 'paid';
        }

        return $this->cents($sale->netPaid()) > 0 ? 'partial' : 'unpaid';
    }

    private function createRow(
        StudentSupplySale $sale,
        string $type,
        int $amountCents,
        string $method,
        ?string $reference,
        ?string $idempotencyKey,
        User $actor,
        ?string $paidAt,
        ?string $notes,
        string $receiptPrefix,
    ): StudentSupplyPayment {
        if ($reference && StudentSupplyPayment::query()->where('transaction_reference', $reference)->exists()) {
            throw ValidationException::withMessages(['transaction_reference' => 'This transaction reference has already been used.']);
        }

        $row = StudentSupplyPayment::query()->create([
            'sale_id' => $sale->id,
            'type' => $type,
            'amount' => $this->money($amountCents),
            'payment_method' => $method,
            'transaction_reference' => $reference ?: null,
            'idempotency_key' => $idempotencyKey ?: null,
            'recorded_by' => $actor->id,
            'paid_at' => $paidAt ?: now(),
            'notes' => $notes,
        ]);
        $row->update(['receipt_number' => sprintf('%s-%08d', $receiptPrefix, $row->id)]);

        return $row;
    }

    private function assertMethod(string $method, string $field): void
    {
        if (! array_key_exists($method, config('supplies.payment_methods', []))) {
            throw ValidationException::withMessages([$field => 'Choose a valid payment method.']);
        }
    }

    private function audit(StudentSupplySale $sale, User $actor, string $event, string $summary, array $details): void
    {
        $year = AcademicYear::query()->find($sale->academic_year_id);
        $student = $sale->student()->first();

        if ($year && $student) {
            $this->activities->record($student, $actor, $event, $summary, $year, ['sale_id' => $sale->id] + $details);
        }
    }
}