<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\StudentSupplySale;
use App\Services\SupplySaleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\CreatesSupplyFixtures;
use Tests\TestCase;

class SupplySaleTest extends TestCase
{
    use CreatesSupplyFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSupplies();
        $this->activeYear();
    }

    public function test_a_sale_deducts_stock_and_totals_from_server_prices(): void
    {
        $actor = $this->actor();
        $book = $this->item(10, 7.50);
        $pen = $this->item(20, 2.00);

        $sale = app(SupplySaleService::class)->create([
            'student_id' => $this->student()->id,
            'items' => [['item_id' => $book->id, 'quantity' => 3], ['item_id' => $pen->id, 'quantity' => 5]],
        ], $actor);

        $this->assertEquals('32.50', $sale->subtotal);
        $this->assertEquals('32.50', $sale->total_amount);
        $this->assertSame(7, $book->fresh()->quantity_on_hand);
        $this->assertSame(15, $pen->fresh()->quantity_on_hand);
        $this->assertMatchesRegularExpression('/^SUP-2026-\d{6}$/', $sale->sale_number);
        $this->assertSame('unpaid', $sale->payment_status);
        $this->assertDatabaseHas('inventory_stock_movements', ['item_id' => $book->id, 'type' => 'sale', 'quantity_change' => -3, 'reference_id' => $sale->id]);
    }

    public function test_the_price_at_the_time_of_sale_is_kept(): void
    {
        $book = $this->item(10, 7.00);
        $sale = app(SupplySaleService::class)->create(['student_id' => $this->student()->id, 'items' => [['item_id' => $book->id, 'quantity' => 2]]], $this->actor());

        $book->update(['selling_price' => 12.00, 'name' => 'Renamed book']);

        $line = $sale->fresh()->items->first();
        $this->assertEquals('7.00', $line->unit_price);
        $this->assertEquals('14.00', $line->line_total);
        $this->assertSame('Renamed book' === $line->item_name, false, 'the name is a snapshot too');
    }

    public function test_repeated_rows_for_one_item_are_merged_and_checked_together(): void
    {
        $book = $this->item(5, 4.00);

        $this->expectException(ValidationException::class);
        app(SupplySaleService::class)->create(['student_id' => $this->student()->id, 'items' => [['item_id' => $book->id, 'quantity' => 3], ['item_id' => $book->id, 'quantity' => 3]]], $this->actor());
    }

    public function test_a_sale_cannot_exceed_stock_and_nothing_is_half_applied(): void
    {
        $plenty = $this->item(10, 5.00);
        $scarce = $this->item(1, 5.00);

        try {
            app(SupplySaleService::class)->create([
                'student_id' => $this->student()->id,
                'items' => [['item_id' => $plenty->id, 'quantity' => 4], ['item_id' => $scarce->id, 'quantity' => 2]],
            ], $this->actor());
            $this->fail('The sale should have been refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertSame(10, $plenty->fresh()->quantity_on_hand, 'the first line was rolled back');
        $this->assertSame(1, $scarce->fresh()->quantity_on_hand);
        $this->assertSame(0, StudentSupplySale::query()->count());
    }

    /**
     * Two sales racing for the last unit: whichever runs second must be refused. The service locks the item row
     * inside the transaction, so on a real database the second request waits for the first and then sees zero stock.
     */
    public function test_two_sales_for_the_last_unit_cannot_both_succeed(): void
    {
        $item = $this->item(1, 5.00);
        $actor = $this->actor();
        $service = app(SupplySaleService::class);

        $service->create(['student_id' => $this->student()->id, 'items' => [['item_id' => $item->id, 'quantity' => 1]]], $actor);

        $this->expectException(ValidationException::class);
        $service->create(['student_id' => $this->student()->id, 'items' => [['item_id' => $item->id, 'quantity' => 1]]], $actor);
    }

    public function test_stock_never_goes_negative_across_many_sales(): void
    {
        $item = $this->item(5, 3.00);
        $actor = $this->actor();
        $student = $this->student();
        $sold = 0;

        for ($i = 0; $i < 10; $i++) {
            try {
                app(SupplySaleService::class)->create(['student_id' => $student->id, 'items' => [['item_id' => $item->id, 'quantity' => 1]]], $actor);
                $sold++;
            } catch (ValidationException) {
            }
        }

        $this->assertSame(5, $sold);
        $this->assertSame(0, $item->fresh()->quantity_on_hand);
        $this->assertSame(-5, (int) $item->stockMovements()->sum('quantity_change'));
    }

    public function test_discounts_need_the_discount_permission(): void
    {
        $item = $this->item(10, 10.00);
        $cashier = $this->actor(['supplies.sales.create', 'supplies.payments.create']);

        $this->expectException(ValidationException::class);
        app(SupplySaleService::class)->create([
            'student_id' => $this->student()->id, 'items' => [['item_id' => $item->id, 'quantity' => 2]],
            'discount_type' => 'percent', 'discount_value' => 10,
        ], $cashier);
    }

    public function test_an_authorised_discount_reduces_the_total_and_cannot_exceed_the_subtotal(): void
    {
        $item = $this->item(10, 10.00);
        $manager = $this->actor();
        $service = app(SupplySaleService::class);

        $sale = $service->create(['student_id' => $this->student()->id, 'items' => [['item_id' => $item->id, 'quantity' => 4]], 'discount_type' => 'percent', 'discount_value' => 25], $manager);
        $this->assertEquals('40.00', $sale->subtotal);
        $this->assertEquals('10.00', $sale->discount_amount);
        $this->assertEquals('30.00', $sale->total_amount);

        $this->expectException(ValidationException::class);
        $service->create(['student_id' => $this->student()->id, 'items' => [['item_id' => $item->id, 'quantity' => 1]], 'discount_type' => 'amount', 'discount_value' => 50], $manager);
    }

    public function test_selling_needs_the_permission(): void
    {
        $this->expectException(AuthorizationException::class);
        app(SupplySaleService::class)->create(['student_id' => $this->student()->id, 'items' => [['item_id' => $this->item(5)->id, 'quantity' => 1]]], $this->actor(['supplies.sales.view']));
    }

    public function test_inactive_items_and_inactive_students_cannot_be_sold_to(): void
    {
        $inactiveItem = $this->item(5, 5.00, overrides: ['is_active' => false]);

        try {
            app(SupplySaleService::class)->create(['student_id' => $this->student()->id, 'items' => [['item_id' => $inactiveItem->id, 'quantity' => 1]]], $this->actor());
            $this->fail('Inactive item sold.');
        } catch (ValidationException) {
        }

        $this->expectException(ValidationException::class);
        app(SupplySaleService::class)->create(['student_id' => $this->student(['status' => 'left'])->id, 'items' => [['item_id' => $this->item(5)->id, 'quantity' => 1]]], $this->actor());
    }

    public function test_the_override_is_off_by_default_and_needs_its_own_permission(): void
    {
        $item = $this->item(1, 5.00);

        $this->expectException(ValidationException::class);
        app(SupplySaleService::class)->create(['student_id' => $this->student()->id, 'items' => [['item_id' => $item->id, 'quantity' => 3]], 'override_stock' => true], $this->actor());
    }

    public function test_an_authorised_override_stops_stock_at_zero_and_records_a_backorder(): void
    {
        config(['supplies.allow_stock_override' => true]);
        $item = $this->item(2, 5.00);
        $actor = $this->actor();

        $sale = app(SupplySaleService::class)->create(['student_id' => $this->student()->id, 'items' => [['item_id' => $item->id, 'quantity' => 5]], 'override_stock' => true], $actor);

        $line = $sale->items->first();
        $this->assertSame(0, $item->fresh()->quantity_on_hand, 'never negative');
        $this->assertSame(3, $line->backordered_quantity);
        $this->assertDatabaseHas('inventory_stock_movements', ['item_id' => $item->id, 'type' => 'sale', 'quantity_change' => -2]);

        // Stock arrives, the owed units are handed over.
        app(\App\Services\InventoryService::class)->adjust($item, 'adjustment_in', 3, 'Delivery', $actor);
        app(SupplySaleService::class)->deliverBackorder($line, 3, $actor);

        $this->assertSame(0, $item->fresh()->quantity_on_hand);
        $this->assertSame(0, $line->fresh()->backordered_quantity);
    }
}
