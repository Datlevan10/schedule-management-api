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
        Schema::table('ai_schedule_analyses', function (Blueprint $table) {
            // Make optimized_schedule nullable to allow processing state
            $table->jsonb('optimized_schedule')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_schedule_analyses', function (Blueprint $table) {
            // Revert to not nullable (original state)
            $table->jsonb('optimized_schedule')->nullable(false)->change();
        });
    }
};