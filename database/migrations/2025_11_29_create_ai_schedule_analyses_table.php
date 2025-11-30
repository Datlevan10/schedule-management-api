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
        Schema::create('ai_schedule_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('import_id')->nullable()->constrained('raw_schedule_imports')->onDelete('set null');
            
            // Analysis request data
            $table->jsonb('input_data'); // Normalized task data sent to AI
            $table->string('analysis_type')->default('daily'); // daily, weekly, monthly
            $table->date('target_date');
            $table->date('end_date')->nullable(); // For weekly/monthly schedules
            
            // AI Processing
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'partial'])->default('pending');
            $table->string('ai_model')->default('gpt-4o-mini');
            $table->jsonb('ai_request_payload')->nullable(); // Full request sent to OpenAI
            $table->jsonb('ai_response')->nullable(); // Raw AI response
            
            // Optimized Schedule Result
            $table->jsonb('optimized_schedule'); // Final schedule with time slots
            $table->jsonb('optimization_metrics')->nullable(); // Metrics like utilization rate, priority coverage
            $table->text('ai_reasoning')->nullable(); // AI's explanation for the schedule
            $table->decimal('confidence_score', 3, 2)->nullable();
            
            // User preferences used in analysis
            $table->jsonb('user_preferences')->nullable();
            $table->time('work_start_time')->nullable();
            $table->time('work_end_time')->nullable();
            $table->integer('break_duration')->nullable(); // minutes
            $table->jsonb('excluded_time_slots')->nullable();
            
            // Processing metadata
            $table->integer('processing_time_ms')->nullable();
            $table->integer('token_usage')->nullable();
            $table->decimal('api_cost', 8, 4)->nullable();
            $table->jsonb('error_details')->nullable();
            $table->integer('retry_count')->default(0);
            
            // User feedback
            $table->boolean('user_approved')->nullable();
            $table->integer('user_rating')->nullable();
            $table->text('user_feedback')->nullable();
            $table->jsonb('user_modifications')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['target_date', 'user_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_schedule_analyses');
    }
};