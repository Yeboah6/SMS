<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('draft')->index();
            $table->timestamps();
            $table->index(['status', 'start_date']);
        });

        Schema::create('terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
            $table->unique(['academic_year_id', 'name']);
            $table->index(['academic_year_id', 'start_date', 'end_date']);
        });

        Schema::create('school_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('singleton_key', 32)->unique()->default('primary');
            $table->string('school_name', 255);
            $table->string('motto')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();
            $table->decimal('attendance_threshold', 5, 2)->default(75);
            $table->json('grade_settings')->nullable();
            $table->json('fee_settings')->nullable();
            $table->json('notification_settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_settings');
        Schema::dropIfExists('terms');
        Schema::dropIfExists('academic_years');
    }
};
