<?php

namespace Tests\Feature;

use App\Models\StudentSupplyPayment;
use App\Models\StudentSupplySale;
use App\Services\SupplyPaymentService;
use App\Services\SupplySaleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\CreatesSupplyFixtures;
use Tests\TestCase;

class SupplyRefundTest extends TestCase
{
    use CreatesSupplyFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSupplies();
        $this->activeYear();
    }

    /** @return array{0: StudentSupplySale, 1: \App\Models\InventoryItem} */
    private function paidSale(float $price = 10.00, int $quantity = 5, float $paid = 50.00, array $extra = []): array
    {
        $item = $this->item(20, $price);
        $sale = app(SupplySaleService::class)->create([
            'student_id' => $this->student()->id,
            'items' => [['item_id' => $item->id, 'quantity' => $quantity]],
            'payment' => $paid > 0 ? ['amount' => $paid, 'payment_method' => 'cash', 'idempotency_key' => 'k-'.uniqid()] : null,
            ...$extra,
        ], $this->actor());

        return [$sale, $item];
    }

    public function test_returning_goods_restocks_and_refunds_the_overpayment(): void
    {
        [$sale, $item] = $this->paidSale(); // 5 x 10 = 50, paid 50, stock 15

        $sale = app(SupplySaleService::class)->returnItems($sale, [
            'items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => 2]],
            'reason' => 'Wrong edition', 'refund_method' => 'cash',
        ], $this->actor());

        $this->assertSame(17, $item->fresh()->quantity_on_hand);
        $this->assertEquals('20.00', $sale->returned_amount);
        $this->assertEquals('20.00', $sale->refunded_amount);
        $this->assertSame('partially_returned', $sale->status);
        $this->assertSame('paid', $sale->payment_status);
        $this->assertEquals(0.0, $sale->balance());
        $this->assertDatabaseHas('student_supply_payments', ['sale_id' => $sale->id, 'type' => 'refund', 'amount' => '20.00']);
        $this->assertDatabaseHas('inventory_stock_movements', ['item_id' => $item->id, 'type' => 'sale_return', 'quantity_change' => 2]);
    }

    public function test_a_return_without_a_refund_method_rolls_everything_back(): void
    {
        [$sale, $item] = $this->paidSale();

        try {
            app(SupplyPaymentService::class); // (resolved only to prove the container works)
            app(SupplySaleService::class)->returnItems($sale, [
                'items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => 2]],
                'reason' => 'Wrong edition',
            ], $this->actor());
            $this->fail('A refund method is required when money must be paid back.');
        } catch (ValidationException) {
        }

        $this->assertSame(15, $item->fresh()->quantity_on_hand, 'stock unchanged');
        $this->assertEquals('0.00', $sale->fresh()->returned_amount);
        $this->assertSame(0, StudentSupplyPayment::query()->where('type', 'refund')->count());
    }

    public function test_returning_goods_on_an_unpaid_sale_reduces_what_is_owed_with_no_refund(): void
    {
        [$sale] = $this->paidSale(paid: 0);

        $sale = app(SupplySaleService::class)->returnItems($sale, [
            'items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => 2]], 'reason' => 'Not needed',
        ], $this->actor());

        $this->assertEquals(30.0, $sale->netDue());
        $this->assertEquals(30.0, $sale->balance());
        $this->assertSame('unpaid', $sale->payment_status);
        $this->assertSame(0, StudentSupplyPayment::query()->count());
    }

    public function test_the_refund_for_a_discounted_sale_carries_the_discount(): void
    {
        [$sale] = $this->paidSale(paid: 0, extra: ['discount_type' => 'percent', 'discount_value' => 20]); // subtotal 50, total 40

        $sale = app(SupplySaleService::class)->returnItems($sale, [
            'items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => 5]], 'reason' => 'All back',
        ], $this->actor());

        $this->assertEquals('40.00', $sale->returned_amount, 'never more than was charged');
        $this->assertSame('returned', $sale->status);
    }

    public function test_more_than_was_kept_cannot_be_returned(): void
    {
        [$sale] = $this->paidSale();
        $line = $sale->items->first();
        $service = app(SupplySaleService::class);
        $service->returnItems($sale, ['items' => [['sale_item_id' => $line->id, 'quantity' => 4]], 'reason' => 'Return', 'refund_method' => 'cash'], $this->actor());

        $this->expectException(ValidationException::class);
        $service->returnItems($sale->fresh(), ['items' => [['sale_item_id' => $line->id, 'quantity' => 2]], 'reason' => 'Return', 'refund_method' => 'cash'], $this->actor());
    }

    public function test_cancelling_restores_all_stock_refunds_everything_and_keeps_the_history(): void
    {
        [$sale, $item] = $this->paidSale();

        $cancelled = app(SupplySaleService::class)->cancel($sale, ['reason' => 'Entered for wrong student', 'refund_method' => 'cash'], $this->actor());

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('void', $cancelled->payment_status);
        $this->assertSame(20, $item->fresh()->quantity_on_hand);
        $this->assertEquals('50.00', $cancelled->refunded_amount);
        $this->assertEquals(0.0, $cancelled->netDue());
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertDatabaseHas('student_supply_sales', ['id' => $sale->id], 'the sale row is kept');
        $this->assertSame(1, $cancelled->items()->count());
        $this->assertSame(2, $cancelled->payments()->count(), 'the payment and its refund both remain');
        $this->assertDatabaseHas('inventory_stock_movements', ['item_id' => $item->id, 'type' => 'sale_cancellation', 'quantity_change' => 5]);
    }

    public function test_a_cancelled_sale_cannot_be_cancelled_paid_or_returned_again(): void
    {
        [$sale] = $this->paidSale(paid: 0);
        $service = app(SupplySaleService::class);
        $cancelled = $service->cancel($sale, ['reason' => 'Mistake'], $this->actor());

        foreach ([
            fn () => $service->cancel($cancelled, ['reason' => 'Again'], $this->actor()),
            fn () => $service->returnItems($cancelled, ['items' => [['sale_item_id' => $cancelled->items->first()->id, 'quantity' => 1]], 'reason' => 'x'], $this->actor()),
            fn () => app(SupplyPaymentService::class)->record($cancelled, ['amount' => 5, 'payment_method' => 'cash'], $this->actor()),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A closed sale accepted a second operation.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_refunds_and_cancellations_need_their_permissions(): void
    {
        [$sale] = $this->paidSale();
        $cashier = $this->actor(['supplies.sales.view', 'supplies.sales.create', 'supplies.payments.create']);

        try {
            app(SupplySaleService::class)->cancel($sale, ['reason' => 'No right', 'refund_method' => 'cash'], $cashier);
            $this->fail('Cancel should need permission.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(AuthorizationException::class);
        app(SupplySaleService::class)->returnItems($sale, ['items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => 1]], 'reason' => 'No right', 'refund_method' => 'cash'], $cashier);
    }

    public function test_the_ledger_balances_after_a_mixed_history(): void
    {
        [$sale, $item] = $this->paidSale();
        $service = app(SupplySaleService::class);
        $service->returnItems($sale, ['items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => 2]], 'reason' => 'Return', 'refund_method' => 'cash'], $this->actor());
        $service->cancel($sale->fresh(), ['reason' => 'Cancel rest', 'refund_method' => 'cash'], $this->actor());

        $this->assertSame(20, $item->fresh()->quantity_on_hand);
        $this->assertEquals(0.0, $sale->fresh()->balance());
        $this->assertEquals('50.00', $sale->fresh()->refunded_amount);
    }
}
