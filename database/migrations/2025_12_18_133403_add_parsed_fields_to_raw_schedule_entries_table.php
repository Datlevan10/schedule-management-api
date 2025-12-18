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
        Schema::table('raw_schedule_entries', function (Blueprint $table) {
            // Add new columns if they don't exist
            if (!Schema::hasColumn('raw_schedule_entries', 'parsed_participants')) {
                $table->jsonb('parsed_participants')->nullable()->after('parsed_priority');
            }
            if (!Schema::hasColumn('raw_schedule_entries', 'parsed_requirements')) {
                $table->text('parsed_requirements')->nullable()->after('parsed_participants');
            }
            if (!Schema::hasColumn('raw_schedule_entries', 'parsed_duration_minutes')) {
                $table->integer('parsed_duration_minutes')->nullable()->after('parsed_requirements');
            }
            if (!Schema::hasColumn('raw_schedule_entries', 'ai_analysis_status')) {
                $table->string('ai_analysis_status')->nullable()->after('ai_detected_importance');
            }
            if (!Schema::hasColumn('raw_schedule_entries', 'ai_analyzed_at')) {
                $table->timestamp('ai_analyzed_at')->nullable()->after('ai_analysis_status');
            }
            if (!Schema::hasColumn('raw_schedule_entries', 'ai_analysis_id')) {
                $table->unsignedBigInteger('ai_analysis_id')->nullable()->after('ai_analyzed_at');
            }
            if (!Schema::hasColumn('raw_schedule_entries', 'ai_analysis_result')) {
                $table->jsonb('ai_analysis_result')->nullable()->after('ai_analysis_id');
            }
            if (!Schema::hasColumn('raw_schedule_entries', 'ai_analysis_locked')) {
                $table->boolean('ai_analysis_locked')->default(false)->after('ai_analysis_result');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('raw_schedule_entries', function (Blueprint $table) {
            $table->dropColumn([
                'parsed_participants',
                'parsed_requirements', 
                'parsed_duration_minutes',
                'ai_analysis_status',
                'ai_analyzed_at',
                'ai_analysis_id',
                'ai_analysis_result',
                'ai_analysis_locked'
            ]);
        });
    }
};