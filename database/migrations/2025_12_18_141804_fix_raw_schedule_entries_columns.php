<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First check which columns exist
        $existingColumns = Schema::getColumnListing('raw_schedule_entries');
        
        Schema::table('raw_schedule_entries', function (Blueprint $table) use ($existingColumns) {
            // Add missing columns one by one with error handling
            
            if (!in_array('parsed_participants', $existingColumns)) {
                $table->jsonb('parsed_participants')->nullable();
            }
            
            if (!in_array('parsed_requirements', $existingColumns)) {
                $table->text('parsed_requirements')->nullable();
            }
            
            if (!in_array('parsed_duration_minutes', $existingColumns)) {
                $table->integer('parsed_duration_minutes')->nullable();
            }
            
            if (!in_array('ai_analysis_status', $existingColumns)) {
                $table->string('ai_analysis_status')->nullable();
            }
            
            if (!in_array('ai_analyzed_at', $existingColumns)) {
                $table->timestamp('ai_analyzed_at')->nullable();
            }
            
            if (!in_array('ai_analysis_id', $existingColumns)) {
                $table->string('ai_analysis_id')->nullable();
            }
            
            if (!in_array('ai_analysis_result', $existingColumns)) {
                $table->jsonb('ai_analysis_result')->nullable();
            }
            
            if (!in_array('ai_analysis_locked', $existingColumns)) {
                $table->boolean('ai_analysis_locked')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('raw_schedule_entries', function (Blueprint $table) {
            // Get existing columns before dropping
            $existingColumns = Schema::getColumnListing('raw_schedule_entries');
            
            $columnsToDrop = [];
            
            if (in_array('parsed_participants', $existingColumns)) {
                $columnsToDrop[] = 'parsed_participants';
            }
            if (in_array('parsed_requirements', $existingColumns)) {
                $columnsToDrop[] = 'parsed_requirements';
            }
            if (in_array('parsed_duration_minutes', $existingColumns)) {
                $columnsToDrop[] = 'parsed_duration_minutes';
            }
            if (in_array('ai_analysis_status', $existingColumns)) {
                $columnsToDrop[] = 'ai_analysis_status';
            }
            if (in_array('ai_analyzed_at', $existingColumns)) {
                $columnsToDrop[] = 'ai_analyzed_at';
            }
            if (in_array('ai_analysis_id', $existingColumns)) {
                $columnsToDrop[] = 'ai_analysis_id';
            }
            if (in_array('ai_analysis_result', $existingColumns)) {
                $columnsToDrop[] = 'ai_analysis_result';
            }
            if (in_array('ai_analysis_locked', $existingColumns)) {
                $columnsToDrop[] = 'ai_analysis_locked';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};