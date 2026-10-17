<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_supply_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->unsignedInteger('quantity_per_student');
            $table->boolean('is_required')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // A NULL term never collides in MySQL, so the "no term" case is checked in the request.
            $table->unique(['academic_year_id', 'class_id', 'item_id', 'term_id'], 'class_supply_requirement_unique');
        });

        // Staff mark a requirement as bought elsewhere (only when supplies.allow_external_fulfilment is on).
        Schema::create('class_supply_ext_fulfilments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requirement_id')->constrained('class_supply_requirements')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marked_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['requirement_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_supply_external_fulfilments');
        Schema::dropIfExists('class_supply_requirements');
    }
};