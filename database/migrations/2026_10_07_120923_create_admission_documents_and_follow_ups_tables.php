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
        Schema::create('admission_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('category', 40);
            $table->string('original_name', 255);
            $table->string('storage_path', 500)->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->index(['admission_id', 'category'], 'admission_document_admission_category_index');
        });

        Schema::create('admission_follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('follow_up_at');
            $table->string('method', 30);
            $table->text('notes');
            $table->string('status', 20)->default('pending');
            $table->text('outcome')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['admission_id', 'status', 'follow_up_at'], 'admission_follow_up_schedule_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_follow_ups');
        Schema::dropIfExists('admission_documents');
    }
};
