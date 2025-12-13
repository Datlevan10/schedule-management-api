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
        // Add AI analysis status to raw_schedule_entries table (CSV imported tasks)
        Schema::table('raw_schedule_entries', function (Blueprint $table) {
            $table->enum('ai_analysis_status', ['pending', 'in_progress', 'completed', 'failed', 'skipped'])
                ->default('pending')
                ->after('ai_detected_importance');
            $table->timestamp('ai_analyzed_at')->nullable()->after('ai_analysis_status');
            $table->string('ai_analysis_id', 100)->nullable()->after('ai_analyzed_at');
            $table->jsonb('ai_analysis_result')->nullable()->after('ai_analysis_id');
            $table->boolean('ai_analysis_locked')->default(false)->after('ai_analysis_result');
            
            // Add indexes
            $table->index('ai_analysis_status', 'idx_entries_ai_status');
            $table->index('ai_analysis_locked', 'idx_entries_ai_locked');
            $table->index(['user_id', 'ai_analysis_status'], 'idx_entries_user_ai_status');
        });

        // Add AI analysis status to events table (manually created tasks)
        Schema::table('events', function (Blueprint $table) {
            $table->enum('ai_analysis_status', ['pending', 'in_progress', 'completed', 'failed', 'skipped'])
                ->default('pending')
                ->after('event_metadata');
            $table->timestamp('ai_analyzed_at')->nullable()->after('ai_analysis_status');
            $table->string('ai_analysis_id', 100)->nullable()->after('ai_analyzed_at');
            $table->jsonb('ai_analysis_result')->nullable()->after('ai_analysis_id');
            $table->boolean('ai_analysis_locked')->default(false)->after('ai_analysis_result');
            
            // Add indexes
            $table->index('ai_analysis_status', 'idx_events_ai_status');
            $table->index('ai_analysis_locked', 'idx_events_ai_locked');
            $table->index(['user_id', 'ai_analysis_status'], 'idx_events_user_ai_status');
        });

        // Create a unified AI analysis tracking table for both types
        Schema::create('ai_analysis_tasks', function (Blueprint $table) {
            $table->id();
            $table->enum('source_type', ['event', 'raw_entry']);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed', 'skipped'])->default('pending');
            $table->string('analysis_batch_id', 100)->nullable();
            $table->jsonb('request_data')->nullable();
            $table->jsonb('response_data')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['source_type', 'source_id'], 'idx_ai_tasks_source');
            $table->index(['user_id', 'status'], 'idx_ai_tasks_user_status');
            $table->index('analysis_batch_id', 'idx_ai_tasks_batch');
            $table->unique(['source_type', 'source_id'], 'unique_ai_task_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('raw_schedule_entries', function (Blueprint $table) {
            $table->dropIndex('idx_entries_ai_status');
            $table->dropIndex('idx_entries_ai_locked');
            $table->dropIndex('idx_entries_user_ai_status');
            $table->dropColumn([
                'ai_analysis_status',
                'ai_analyzed_at',
                'ai_analysis_id',
                'ai_analysis_result',
                'ai_analysis_locked'
            ]);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('idx_events_ai_status');
            $table->dropIndex('idx_events_ai_locked');
            $table->dropIndex('idx_events_user_ai_status');
            $table->dropColumn([
                'ai_analysis_status',
                'ai_analyzed_at',
                'ai_analysis_id',
                'ai_analysis_result',
                'ai_analysis_locked'
            ]);
        });

        Schema::dropIfExists('ai_analysis_tasks');
    }
};