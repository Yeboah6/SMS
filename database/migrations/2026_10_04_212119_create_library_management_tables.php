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
        Schema::create('books', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 200);
            $table->string('author', 150);
            $table->string('isbn', 30)->nullable()->unique();
            $table->string('category', 100);
            $table->string('publisher', 150)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'title'], 'books_active_title_index');
        });

        Schema::create('book_copies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->string('accession_number', 40)->unique();
            $table->string('shelf_location', 100)->nullable();
            $table->string('status', 20)->default('available');
            $table->timestamps();

            $table->index(['book_id', 'status'], 'book_copy_book_status_index');
        });

        Schema::create('library_loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_copy_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('issue_date');
            $table->date('due_date');
            $table->timestamp('returned_at')->nullable();
            $table->unsignedSmallInteger('renewal_count')->default(0);
            $table->string('status', 20)->default('issued');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status', 'due_date'], 'library_loan_student_status_due_index');
            $table->index(['book_copy_id', 'status'], 'library_loan_copy_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('library_loans');
        Schema::dropIfExists('book_copies');
        Schema::dropIfExists('books');
    }
};
