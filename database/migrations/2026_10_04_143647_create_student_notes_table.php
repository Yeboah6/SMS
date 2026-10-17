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
        Schema::create('student_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('note_type', 40);
            $table->string('title', 255);
            $table->text('content');
            $table->date('note_date');
            $table->string('visibility', 30)->default('staff_only');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name', 255)->nullable();
            $table->string('attachment_mime_type', 100)->nullable();
            $table->unsignedBigInteger('attachment_size_bytes')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'note_date'], 'student_notes_student_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_notes');
    }
};
