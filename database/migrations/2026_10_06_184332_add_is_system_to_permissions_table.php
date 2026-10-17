<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(config('permission.table_names.permissions'), function (Blueprint $table): void {
            $table->boolean('is_system')->default(false)->after('guard_name');
        });

        DB::table(config('permission.table_names.permissions'))->update(['is_system' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('permission.table_names.permissions'), function (Blueprint $table): void {
            $table->dropColumn('is_system');
        });
    }
};
