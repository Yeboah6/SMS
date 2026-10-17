<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('contact_person', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('inventory_categories')->restrictOnDelete();
            $table->string('sku', 60)->unique();
            $table->string('name', 200)->index();
            $table->text('description')->nullable();
            $table->string('unit', 30)->default('piece');
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            // Only ever changed by InventoryService, together with a ledger row.
            $table->unsignedInteger('quantity_on_hand')->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'quantity_on_hand']);
        });

        Schema::create('inventory_purchases', function (Blueprint $table): void {
            $table->id();
            $table->string('purchase_number', 40)->unique();
            $table->foreignId('supplier_id')->constrained('inventory_suppliers')->restrictOnDelete();
            $table->date('purchase_date');
            $table->string('reference', 100)->nullable();
            $table->decimal('total_cost', 14, 2);
            $table->string('status', 20)->default('received');
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'reference']);
            $table->index('purchase_date');
        });

        Schema::create('inventory_purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->constrained('inventory_purchases')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        // The ledger. Rows are never updated or deleted: corrections are new rows.
        Schema::create('inventory_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->string('type', 30)->index();
            $table->integer('quantity_change');
            $table->unsignedInteger('quantity_before');
            $table->unsignedInteger('quantity_after');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->nullableMorphs('reference');
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_movements');
        Schema::dropIfExists('inventory_purchase_items');
        Schema::dropIfExists('inventory_purchases');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_suppliers');
        Schema::dropIfExists('inventory_categories');
    }
};