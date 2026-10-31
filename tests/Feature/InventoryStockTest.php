<?php

namespace Tests\Feature;

use App\Models\InventoryPurchase;
use App\Models\InventoryStockMovement;
use App\Models\InventorySupplier;
use App\Services\InventoryService;
use App\Services\SupplyPurchaseService;
use App\Services\SupplySaleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Feature\CreatesSupplyFixtures;
use Tests\TestCase;

class InventoryStockTest extends TestCase
{
    use CreatesSupplyFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSupplies();
    }

    public function test_opening_stock_is_written_to_the_ledger(): void
    {
        $actor = $this->actor();
        $category = \App\Models\InventoryCategory::factory()->create();

        $item = app(InventoryService::class)->createItem([
            'category_id' => $category->id, 'name' => '80-leaf exercise book', 'unit' => 'piece', 'cost_price' => 4, 'selling_price' => 7,
            'reorder_level' => 10, 'is_active' => true, 'opening_quantity' => 50,
        ], $actor);

        $this->assertSame(50, $item->quantity_on_hand);
        $this->assertStringStartsWith('ITM-', $item->sku, 'a blank SKU is generated');
        $movement = $item->stockMovements()->first();
        $this->assertSame('opening', $movement->type);
        $this->assertSame([0, 50, 50], [$movement->quantity_before, $movement->quantity_change, $movement->quantity_after]);
    }

    public function test_receiving_a_purchase_adds_stock_and_records_each_movement(): void
    {
        $actor = $this->actor();
        $item = $this->item(5);
        $supplier = InventorySupplier::factory()->create();

        $purchase = app(SupplyPurchaseService::class)->receive([
            'supplier_id' => $supplier->id, 'purchase_date' => '2026-10-01', 'reference' => 'INV-77',
            'items' => [['item_id' => $item->id, 'quantity' => 20, 'unit_cost' => 4.50]],
        ], $actor);

        $this->assertSame(25, $item->fresh()->quantity_on_hand);
        $this->assertEquals('90.00', $purchase->total_cost);
        $this->assertEquals('4.50', $item->fresh()->cost_price, 'latest cost is kept on the item');
        $this->assertDatabaseHas('inventory_stock_movements', ['item_id' => $item->id, 'type' => 'received', 'quantity_change' => 20, 'quantity_before' => 5, 'quantity_after' => 25]);
    }

    public function test_adjustments_cannot_take_stock_below_zero(): void
    {
        $actor = $this->actor();
        $item = $this->item(3);

        $this->expectException(ValidationException::class);

        try {
            app(InventoryService::class)->adjust($item, 'damaged', 4, 'Water damage', $actor);
        } finally {
            $this->assertSame(3, $item->fresh()->quantity_on_hand);
            $this->assertSame(0, InventoryStockMovement::query()->count());
        }
    }

    public function test_adjusting_stock_needs_the_permission(): void
    {
        $this->expectException(AuthorizationException::class);

        app(InventoryService::class)->adjust($this->item(3), 'adjustment_in', 1, 'Found in store', $this->actor(['supplies.items.view']));
    }

    public function test_ledger_rows_are_immutable(): void
    {
        $item = $this->item(5);
        $movement = app(InventoryService::class)->adjust($item, 'adjustment_in', 2, 'Recount', $this->actor());

        $this->expectException(LogicException::class);
        $movement->update(['quantity_change' => 99]);
    }

    public function test_ledger_rows_cannot_be_deleted(): void
    {
        $item = $this->item(5);
        $movement = app(InventoryService::class)->adjust($item, 'adjustment_in', 2, 'Recount', $this->actor());

        $this->expectException(LogicException::class);
        $movement->delete();
    }

    public function test_a_purchase_cannot_be_reversed_once_its_stock_has_been_sold(): void
    {
        $actor = $this->actor();
        $this->activeYear();
        $item = $this->item(0);
        $supplier = InventorySupplier::factory()->create();

        $purchase = app(SupplyPurchaseService::class)->receive([
            'supplier_id' => $supplier->id, 'purchase_date' => '2026-10-01',
            'items' => [['item_id' => $item->id, 'quantity' => 10, 'unit_cost' => 5]],
        ], $actor);
        app(SupplySaleService::class)->create(['student_id' => $this->student()->id, 'items' => [['item_id' => $item->id, 'quantity' => 8]]], $actor);

        try {
            app(SupplyPurchaseService::class)->reverse($purchase, 'Entered in error', $actor);
            $this->fail('Reversal should have been refused.');
        } catch (ValidationException) {
            $this->assertSame(2, $item->fresh()->quantity_on_hand, 'nothing was taken out');
            $this->assertSame('received', InventoryPurchase::query()->first()->status);
        }
    }

    public function test_reversing_a_purchase_takes_the_stock_back_out(): void
    {
        $actor = $this->actor();
        $item = $this->item(4);
        $supplier = InventorySupplier::factory()->create();
        $purchase = app(SupplyPurchaseService::class)->receive([
            'supplier_id' => $supplier->id, 'purchase_date' => '2026-10-01',
            'items' => [['item_id' => $item->id, 'quantity' => 6, 'unit_cost' => 5]],
        ], $actor);

        app(SupplyPurchaseService::class)->reverse($purchase, 'Wrong supplier', $actor);

        $this->assertSame(4, $item->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('inventory_stock_movements', ['item_id' => $item->id, 'type' => 'purchase_reversal', 'quantity_change' => -6]);
        $this->assertSame('reversed', $purchase->fresh()->status);
    }

    public function test_stock_status_scopes(): void
    {
        $out = $this->item(0, overrides: ['reorder_level' => 5]);
        $low = $this->item(3, overrides: ['reorder_level' => 5]);
        $ok = $this->item(20, overrides: ['reorder_level' => 5]);

        $this->assertSame([$out->id], \App\Models\InventoryItem::query()->outOfStock()->pluck('id')->all());
        $this->assertSame([$low->id], \App\Models\InventoryItem::query()->lowStock()->pluck('id')->all());
        $this->assertSame('ok', $ok->stockStatus());
    }
}
