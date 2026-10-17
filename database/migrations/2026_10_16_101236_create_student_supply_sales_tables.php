<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_supply_sales', function (Blueprint $table): void {
            $table->id();
            $table->string('sale_number', 40)->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2);
            $table->decimal('returned_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('refunded_amount', 14, 2)->default(0);
            $table->string('status', 30)->default('completed');
            $table->string('payment_status', 20)->default('unpaid');
            $table->text('notes')->nullable();
            $table->foreignId('sold_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('sold_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'sold_at']);
            $table->index(['academic_year_id', 'sold_at']);
            $table->index(['status', 'payment_status']);
            $table->index('sold_at');
        });

        Schema::create('student_supply_sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('student_supply_sales')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->restrictOnDelete();
            // Snapshots: later price or name changes never rewrite history.
            $table->string('item_name', 200);
            $table->string('sku', 60);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('line_total', 14, 2);
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->unsignedInteger('backordered_quantity')->default(0);
            $table->timestamps();
        });

        // Payments and refunds. Rows are never updated or deleted: a mistake is corrected with a refund.
        Schema::create('student_supply_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('student_supply_sales')->restrictOnDelete();
            $table->string('type', 10);
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 30);
            $table->string('transaction_reference', 100)->nullable()->unique();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->string('receipt_number', 40)->nullable()->unique();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['sale_id', 'type']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_supply_payments');
        Schema::dropIfExists('student_supply_sale_items');
        Schema::dropIfExists('student_supply_sales');
    }
};