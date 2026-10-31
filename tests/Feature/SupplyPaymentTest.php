<?php

namespace Tests\Feature;

use App\Models\StudentSupplyPayment;
use App\Models\StudentSupplySale;
use App\Services\SupplyPaymentService;
use App\Services\SupplySaleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Feature\CreatesSupplyFixtures;
use Tests\TestCase;

class SupplyPaymentTest extends TestCase
{
    use CreatesSupplyFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSupplies();
        $this->activeYear();
    }

    private function sale(float $price = 10.00, int $quantity = 5, ?array $payment = null): StudentSupplySale
    {
        return app(SupplySaleService::class)->create([
            'student_id' => $this->student()->id,
            'items' => [['item_id' => $this->item(50, $price)->id, 'quantity' => $quantity]],
            'payment' => $payment,
        ], $this->actor());
    }

    public function test_partial_payments_update_the_balance_and_status(): void
    {
        $sale = $this->sale(); // 50.00
        $payments = app(SupplyPaymentService::class);
        $actor = $this->actor();

        $first = $payments->record($sale, ['amount' => 20, 'payment_method' => 'mobile_money', 'transaction_reference' => 'MM-1'], $actor);
        $sale->refresh();
        $this->assertSame('partial', $sale->payment_status);
        $this->assertEquals('30.00', number_format($sale->balance(), 2, '.', ''));
        $this->assertMatchesRegularExpression('/^SUPR-\d{8}$/', $first->receipt_number);

        $payments->record($sale, ['amount' => 30, 'payment_method' => 'cash'], $actor);
        $this->assertSame('paid', $sale->fresh()->payment_status);
        $this->assertEquals(0.0, $sale->fresh()->balance());
    }

    public function test_a_payment_cannot_exceed_the_balance(): void
    {
        $sale = $this->sale();

        $this->expectException(ValidationException::class);
        app(SupplyPaymentService::class)->record($sale, ['amount' => 50.01, 'payment_method' => 'cash'], $this->actor());
    }

    public function test_payments_must_use_a_configured_method(): void
    {
        $sale = $this->sale();

        $this->expectException(ValidationException::class);
        app(SupplyPaymentService::class)->record($sale, ['amount' => 10, 'payment_method' => 'crypto'], $this->actor());
    }

    public function test_when_partial_payment_is_off_the_whole_balance_is_required(): void
    {
        config(['supplies.allow_partial_payment' => false]);
        $sale = $this->sale();
        $payments = app(SupplyPaymentService::class);

        try {
            $payments->record($sale, ['amount' => 10, 'payment_method' => 'cash'], $this->actor());
            $this->fail('A partial payment should be refused.');
        } catch (ValidationException) {
        }

        $payments->record($sale, ['amount' => 50, 'payment_method' => 'cash'], $this->actor());
        $this->assertSame('paid', $sale->fresh()->payment_status);
    }

    public function test_with_partial_payment_off_a_sale_must_be_paid_in_full_at_the_till(): void
    {
        config(['supplies.allow_partial_payment' => false]);

        $this->expectException(ValidationException::class);
        $this->sale(10, 5, ['amount' => 20, 'payment_method' => 'cash']);
    }

    public function test_a_transaction_reference_can_only_be_used_once(): void
    {
        $payments = app(SupplyPaymentService::class);
        $actor = $this->actor();
        $payments->record($this->sale(), ['amount' => 10, 'payment_method' => 'mobile_money', 'transaction_reference' => 'TXN-9'], $actor);

        $this->expectException(ValidationException::class);
        $payments->record($this->sale(), ['amount' => 10, 'payment_method' => 'mobile_money', 'transaction_reference' => 'TXN-9'], $actor);
    }

    public function test_submitting_the_same_payment_twice_records_it_once(): void
    {
        $sale = $this->sale();
        $payments = app(SupplyPaymentService::class);
        $actor = $this->actor();
        $data = ['amount' => 25, 'payment_method' => 'cash', 'idempotency_key' => 'form-abc-123'];

        $first = $payments->record($sale, $data, $actor);
        $second = $payments->record($sale, $data, $actor);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, StudentSupplyPayment::query()->count());
        $this->assertEquals('25.00', $sale->fresh()->paid_amount);
    }

    public function test_an_idempotency_key_cannot_be_reused_on_another_sale(): void
    {
        $payments = app(SupplyPaymentService::class);
        $actor = $this->actor();
        $payments->record($this->sale(), ['amount' => 5, 'payment_method' => 'cash', 'idempotency_key' => 'shared-key'], $actor);

        $this->expectException(ValidationException::class);
        $payments->record($this->sale(), ['amount' => 5, 'payment_method' => 'cash', 'idempotency_key' => 'shared-key'], $actor);
    }

    public function test_payments_are_immutable_but_the_receipt_number_is_set_once(): void
    {
        $payment = app(SupplyPaymentService::class)->record($this->sale(), ['amount' => 10, 'payment_method' => 'cash'], $this->actor());
        $this->assertNotNull($payment->receipt_number);

        $this->expectException(LogicException::class);
        $payment->update(['amount' => 1]);
    }

    public function test_payments_cannot_be_deleted(): void
    {
        $payment = app(SupplyPaymentService::class)->record($this->sale(), ['amount' => 10, 'payment_method' => 'cash'], $this->actor());

        $this->expectException(LogicException::class);
        $payment->delete();
    }

    public function test_recording_a_payment_needs_the_permission(): void
    {
        $this->expectException(AuthorizationException::class);
        app(SupplyPaymentService::class)->record($this->sale(), ['amount' => 5, 'payment_method' => 'cash'], $this->actor(['supplies.sales.view']));
    }

    public function test_payment_taken_at_the_till_is_linked_to_the_sale(): void
    {
        $sale = $this->sale(10, 5, ['amount' => 50, 'payment_method' => 'cash', 'idempotency_key' => 'till-1']);

        $this->assertSame('paid', $sale->payment_status);
        $this->assertSame(1, $sale->payments()->count());
        $this->assertNotNull($sale->payments->first()->recorded_by);
    }
}
