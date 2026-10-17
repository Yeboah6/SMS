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
        Schema::create('notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 180);
            $table->text('body');
            $table->string('audience', 20);
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'audience', 'published_at'], 'notices_status_audience_published_index');
            $table->index(['status', 'expires_at'], 'notices_status_expiry_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notices');
    }
};
