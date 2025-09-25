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
        Schema::create('smart_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained('events');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('type', ['reminder', 'preparation', 'priority_alert', 'conflict_warning', 'deadline_approach', 'followup']);
            $table->string('subtype')->nullable();
            $table->datetime('trigger_datetime');
            $table->datetime('scheduled_at')->nullable();
            $table->datetime('sent_at')->nullable();
            $table->string('title');
            $table->text('message');
            $table->json('action_data')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->integer('priority_level')->default(3);
            $table->json('profession_specific_data')->nullable();
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'acted', 'failed'])->default('pending');
            $table->enum('delivery_method', ['push', 'email', 'sms', 'in_app'])->default('in_app');
            $table->datetime('opened_at')->nullable();
            $table->boolean('action_taken')->default(false);
            $table->integer('feedback_rating')->nullable();
            $table->timestamps();
            
            $table->index(['trigger_datetime'], 'idx_trigger_time');
            $table->index(['user_id', 'status'], 'idx_notifications_user_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smart_notifications');
    }
};
