<?php

namespace Tests\Feature;

use App\Models\StudentSupplySale;
use App\Services\InventoryService;
use App\Services\SupplyPaymentService;
use App\Services\SupplyReportService;
use App\Services\SupplySaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\CreatesSupplyFixtures;
use Tests\TestCase;

class SupplyReportTest extends TestCase
{
    use CreatesSupplyFixtures, RefreshDatabase;

    private SupplyReportService $reports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSupplies();
        $this->activeYear();
        $this->reports = app(SupplyReportService::class);
    }

    private function sell($item, int $quantity, ?array $payment = null, $student = null, $actor = null): StudentSupplySale
    {
        return app(SupplySaleService::class)->create([
            'student_id' => ($student ?? $this->student())->id,
            'items' => [['item_id' => $item->id, 'quantity' => $quantity]],
            'payment' => $payment,
        ], $actor ?? $this->actor());
    }

    public function test_dashboard_figures(): void
    {
        $book = $this->item(10, 10.00, 6.00);
        $this->item(2, 5.00, overrides: ['reorder_level' => 5]); // low
        $this->item(0, 5.00); // out
        $this->sell($book, 3, ['amount' => 10, 'payment_method' => 'cash', 'idempotency_key' => 'd1']); // 30, paid 10

        $stats = $this->reports->dashboard();

        $this->assertSame(3, $stats['items']);
        $this->assertSame(1, $stats['lowStock']);
        $this->assertSame(1, $stats['outOfStock']);
        $this->assertSame(1, $stats['salesToday']['count']);
        $this->assertEquals(30.0, $stats['salesToday']['net']);
        $this->assertEquals(10.0, $stats['salesToday']['collected']);
        $this->assertEquals(20.0, $stats['outstanding']['balance']);
        $this->assertEquals(7 * 6.00 + 2 * 6.00, $stats['stockCost'], 'valuation is cost x quantity on hand (7 books + 2 low-stock items at 6.00)');
    }

    public function test_stock_valuation_is_cost_times_quantity(): void
    {
        $this->item(10, 10.00, 6.00);
        $this->item(5, 4.00, 2.00);

        $stats = $this->reports->dashboard();

        $this->assertEquals(70.0, $stats['stockCost']);
        $this->assertEquals(120.0, $stats['stockRetail']);
    }

    public function test_net_sales_exclude_returns_and_cancellations(): void
    {
        $book = $this->item(20, 10.00);
        $kept = $this->sell($book, 4);                       // 40
        $part = $this->sell($book, 4);                       // 40, 1 returned -> 30
        app(SupplySaleService::class)->returnItems($part, ['items' => [['sale_item_id' => $part->items->first()->id, 'quantity' => 1]], 'reason' => 'Return'], $this->actor());
        $gone = $this->sell($book, 4);                       // 40 cancelled -> 0
        app(SupplySaleService::class)->cancel($gone, ['reason' => 'Mistake'], $this->actor());

        $sales = $this->reports->sales(['from' => today()->subDay()->toDateString(), 'to' => today()->toDateString()]);

        $this->assertEquals(70.0, $sales['totals']['net']);
        $this->assertSame(3, $sales['totals']['sales']);
    }

    public function test_sales_roll_up_by_month_and_collected_money_nets_refunds(): void
    {
        $book = $this->item(20, 10.00);
        $sale = $this->sell($book, 5, ['amount' => 50, 'payment_method' => 'cash', 'idempotency_key' => 'r1']);
        app(SupplySaleService::class)->returnItems($sale, ['items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => 2]], 'reason' => 'Return', 'refund_method' => 'cash'], $this->actor());

        $report = $this->reports->sales(['granularity' => 'month', 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]);

        $this->assertCount(1, $report['rows']);
        $this->assertSame(now()->format('Y-m'), $report['rows'][0]['period']);
        $this->assertEquals(30.0, $report['rows'][0]['net']);
        $this->assertEquals(30.0, $report['rows'][0]['collected'], '50 paid minus 20 refunded');
    }

    public function test_breakdowns_by_item_cashier_and_payment_method(): void
    {
        $book = $this->item(20, 10.00, 6.00, ['name' => 'Atlas']);
        $pen = $this->item(20, 2.00, 1.00, ['name' => 'Pen']);
        $ama = $this->actor();
        $this->sell($book, 3, ['amount' => 30, 'payment_method' => 'mobile_money', 'idempotency_key' => 'b1'], null, $ama);
        $this->sell($pen, 10, ['amount' => 5, 'payment_method' => 'cash', 'idempotency_key' => 'b2'], null, $ama);

        $items = collect($this->reports->breakdown('item', []));
        $this->assertSame(['Atlas', 'Pen'], $items->pluck('label')->all(), 'largest revenue first');
        $this->assertSame(3, $items->firstWhere('label', 'Atlas')['count']);
        $this->assertEquals(18.0, $items->firstWhere('label', 'Atlas')['cost']);

        $cashier = $this->reports->breakdown('cashier', []);
        $this->assertCount(1, $cashier);
        $this->assertEquals(50.0, $cashier[0]['amount']);

        $methods = collect($this->reports->breakdown('method', []))->pluck('amount', 'label');
        $this->assertEquals(30.0, $methods['Mobile money']);
        $this->assertEquals(5.0, $methods['Cash']);
    }

    public function test_outstanding_balances_list_only_sales_that_still_owe(): void
    {
        $book = $this->item(20, 10.00);
        $owes = $this->sell($book, 3);                                                                    // 30 owed
        $this->sell($book, 2, ['amount' => 20, 'payment_method' => 'cash', 'idempotency_key' => 'o1']);   // settled
        $cancelled = $this->sell($book, 1);
        app(SupplySaleService::class)->cancel($cancelled, ['reason' => 'Mistake'], $this->actor());
        $partial = $this->sell($book, 4);
        app(SupplyPaymentService::class)->record($partial, ['amount' => 15, 'payment_method' => 'cash'], $this->actor()); // 25 owed

        $report = $this->reports->outstanding([]);

        $this->assertEquals(['SUP'], array_unique(array_map(fn ($r) => substr($r['saleNumber'], 0, 3), $report['rows'])));
        $this->assertCount(2, $report['rows']);
        $this->assertEquals(55.0, $report['total']);
        $this->assertContains($owes->sale_number, array_column($report['rows'], 'saleNumber'));
    }

    public function test_stock_report_counts_movements_by_type(): void
    {
        $actor = $this->actor();
        $book = $this->item(10, 10.00);
        $this->sell($book, 4, null, null, $actor);
        app(InventoryService::class)->adjust($book, 'damaged', 2, 'Water', $actor);

        $stock = collect($this->reports->stock([])['summary'])->keyBy('type');

        $this->assertSame(4, $stock['sale']['units']);
        $this->assertSame(2, $stock['damaged']['units']);
        $this->assertSame(0, $stock['received']['units']);
    }

    public function test_exports_produce_headings_and_rows(): void
    {
        $this->sell($this->item(10, 10.00), 2);

        [$headings, $rows] = $this->reports->export('outstanding', []);

        $this->assertSame('Sale', $headings[0]);
        $this->assertCount(1, $rows);
        $this->assertEquals(20.0, $rows[0][7]);
    }
}
