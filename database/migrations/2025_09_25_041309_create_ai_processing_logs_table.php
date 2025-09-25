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
        Schema::create('ai_processing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->text('input_text');
            $table->enum('input_type', ['schedule_parse', 'priority_analysis', 'conflict_detection', 'suggestion_generation']);
            $table->json('processed_data')->nullable();
            $table->json('detected_keywords')->nullable();
            $table->json('profession_context')->nullable();
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->decimal('priority_calculated', 5, 2)->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->string('ai_model_version')->nullable();
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'input_type'], 'idx_user_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_processing_logs');
    }
};
