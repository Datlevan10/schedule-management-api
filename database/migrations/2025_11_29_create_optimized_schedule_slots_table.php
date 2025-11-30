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
        Schema::create('optimized_schedule_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained('ai_schedule_analyses')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('original_entry_id')->nullable()->constrained('raw_schedule_entries')->onDelete('set null');
            $table->foreignId('event_id')->nullable()->constrained('events')->onDelete('set null');
            
            // Schedule slot details
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration_minutes');
            
            // Task information
            $table->string('task_title');
            $table->text('task_description')->nullable();
            $table->string('location')->nullable();
            $table->enum('priority', ['critical', 'high', 'medium', 'low'])->default('medium');
            $table->string('category')->nullable();
            
            // AI optimization details
            $table->text('ai_reasoning')->nullable(); // Why this time slot was chosen
            $table->decimal('suitability_score', 3, 2)->nullable(); // How well this slot fits
            $table->jsonb('optimization_factors')->nullable(); // Factors considered (energy level, focus time, etc.)
            $table->boolean('is_flexible')->default(false);
            $table->jsonb('alternative_slots')->nullable(); // Alternative time suggestions
            
            // Notification settings
            $table->integer('reminder_minutes_before')->nullable();
            $table->boolean('notification_sent')->default(false);
            $table->datetime('notification_sent_at')->nullable();
            
            // Status tracking
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled', 'rescheduled'])->default('scheduled');
            $table->boolean('user_confirmed')->default(false);
            $table->datetime('confirmed_at')->nullable();
            $table->datetime('completed_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'date', 'start_time']);
            $table->index(['analysis_id', 'date']);
            $table->index(['status', 'date']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('optimized_schedule_slots');
    }
};