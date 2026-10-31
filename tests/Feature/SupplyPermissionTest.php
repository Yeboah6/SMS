<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryPurchase;
use App\Models\InventorySupplier;
use App\Models\StudentSupplySale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\CreatesSupplyFixtures;
use Tests\TestCase;

/**
 * Policies are checked directly, and the routes are checked for the 403 an unauthorised user gets.
 * (The authorised pages are Inertia responses and are covered by your normal browser or Inertia tests.)
 */
class SupplyPermissionTest extends TestCase
{
    use CreatesSupplyFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSupplies();
    }

    public function test_policies_follow_the_permissions(): void
    {
        $this->activeYear();
        $keeper = $this->actor(['supplies.items.view', 'supplies.items.create', 'supplies.items.update', 'supplies.stock.adjust', 'supplies.purchases.view', 'supplies.purchases.create']);
        $cashier = $this->actor(['supplies.sales.view', 'supplies.sales.create', 'supplies.payments.create', 'supplies.items.view']);
        $nobody = $this->actor([]);

        $item = $this->item(5);
        $sale = StudentSupplySale::query()->create([
            'sale_number' => 'SUP-T-1', 'student_id' => $this->student()->id, 'academic_year_id' => \App\Models\AcademicYear::query()->first()->id,
            'subtotal' => 10, 'discount_amount' => 0, 'total_amount' => 10, 'status' => 'completed', 'payment_status' => 'unpaid',
            'sold_by' => $cashier->id, 'sold_at' => now(),
        ]);

        $this->assertTrue($keeper->can('create', InventoryItem::class));
        $this->assertTrue($keeper->can('adjustStock', $item));
        $this->assertFalse($cashier->can('create', InventoryItem::class));
        $this->assertFalse($cashier->can('adjustStock', $item));

        $this->assertTrue($cashier->can('create', StudentSupplySale::class));
        $this->assertTrue($cashier->can('recordPayment', $sale));
        $this->assertFalse($cashier->can('refund', $sale), 'a cashier cannot refund');
        $this->assertFalse($cashier->can('cancel', $sale), 'a cashier cannot cancel');
        $this->assertFalse($keeper->can('create', StudentSupplySale::class), 'a storekeeper cannot sell');

        $this->assertTrue($keeper->can('create', InventoryPurchase::class));
        $this->assertFalse($cashier->can('create', InventoryPurchase::class));
        $this->assertFalse($keeper->can('manage', InventoryCategory::class));
        $this->assertFalse($nobody->can('viewAny', InventoryItem::class));
        $this->assertFalse($nobody->can('viewAny', InventorySupplier::class));
    }

    public function test_a_closed_sale_cannot_take_payments_refunds_or_cancellation(): void
    {
        $this->activeYear();
        $manager = $this->actor();
        $sale = StudentSupplySale::query()->create([
            'sale_number' => 'SUP-T-2', 'student_id' => $this->student()->id, 'academic_year_id' => \App\Models\AcademicYear::query()->first()->id,
            'subtotal' => 10, 'discount_amount' => 0, 'total_amount' => 10, 'returned_amount' => 10, 'status' => 'cancelled', 'payment_status' => 'void',
            'sold_by' => $manager->id, 'sold_at' => now(),
        ]);

        $this->assertFalse($manager->can('recordPayment', $sale));
        $this->assertFalse($manager->can('refund', $sale));
        $this->assertFalse($manager->can('cancel', $sale));
    }

    public function test_unauthorised_users_get_a_403_on_the_supplies_pages(): void
    {
        $user = $this->actor([]);

        foreach (['/supplies', '/supplies/items', '/supplies/sales', '/supplies/sales/create', '/supplies/purchases', '/supplies/suppliers', '/supplies/requirements', '/supplies/reports', '/supplies/reports/export?report=sales'] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    public function test_unauthorised_users_cannot_post_sales_payments_or_adjustments(): void
    {
        $this->activeYear();
        $user = $this->actor(['supplies.sales.view']);
        $item = $this->item(5);

        $this->actingAs($user)->post('/supplies/sales', ['student_id' => $this->student()->id, 'items' => [['item_id' => $item->id, 'quantity' => 1]]])->assertForbidden();
        $this->actingAs($user)->post("/supplies/items/{$item->id}/adjust", ['type' => 'adjustment_in', 'quantity' => 1, 'reason' => 'Because'])->assertForbidden();
        $this->assertSame(5, $item->fresh()->quantity_on_hand);
        $this->assertSame(0, StudentSupplySale::query()->count());
    }

    public function test_client_side_prices_are_ignored_by_the_endpoint(): void
    {
        $this->activeYear();
        $cashier = $this->actor();
        $item = $this->item(10, 10.00);
        $student = $this->student();

        $this->actingAs($cashier)->post('/supplies/sales', [
            'student_id' => $student->id,
            'items' => [['item_id' => $item->id, 'quantity' => 2, 'unit_price' => 0.01]],
            'total_amount' => 0.02,
        ]);

        $sale = StudentSupplySale::query()->first();
        $this->assertNotNull($sale);
        $this->assertEquals('20.00', $sale->total_amount, 'the server priced the sale');
    }
}
