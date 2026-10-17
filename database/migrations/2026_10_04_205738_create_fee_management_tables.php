<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fee_heads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fee_structures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('fee_head_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->timestamps();

            $table->unique(['academic_year_id', 'class_id', 'fee_head_id'], 'fee_structure_year_class_head_unique');
            $table->index(['academic_year_id', 'class_id', 'due_date'], 'fee_structure_year_class_due_index');
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number', 40)->unique();
            $table->string('status', 20)->default('unpaid');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->date('issued_at');
            $table->date('due_date');
            $table->timestamps();

            $table->unique(['academic_year_id', 'student_id'], 'invoice_year_student_unique');
            $table->index(['academic_year_id', 'status', 'due_date'], 'invoice_year_status_due_index');
            $table->index(['student_id', 'academic_year_id'], 'invoice_student_year_index');
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_structure_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_head_id')->constrained()->restrictOnDelete();
            $table->string('description', 255);
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->timestamps();

            $table->unique(['invoice_id', 'fee_structure_id'], 'invoice_item_structure_unique');
            $table->index(['invoice_id', 'fee_head_id'], 'invoice_item_head_index');
        });

        Schema::create('fee_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->string('receipt_number', 40)->unique();
            $table->string('payment_method', 30);
            $table->string('transaction_reference', 100)->nullable()->unique();
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'payment_date'], 'fee_payment_invoice_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_heads');
    }
};
