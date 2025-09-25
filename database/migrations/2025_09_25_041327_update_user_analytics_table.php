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
        Schema::table('user_analytics', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('profession_id')->nullable()->constrained('professions');
            $table->integer('total_events')->default(0);
            $table->integer('completed_events')->default(0);
            $table->integer('cancelled_events')->default(0);
            $table->integer('high_priority_events')->default(0);
            $table->bigInteger('total_scheduled_minutes')->default(0);
            $table->bigInteger('actual_worked_minutes')->default(0);
            $table->bigInteger('break_time_minutes')->default(0);
            $table->bigInteger('overtime_minutes')->default(0);
            $table->decimal('productivity_score', 5, 2)->nullable();
            $table->decimal('stress_level', 5, 2)->nullable();
            $table->decimal('work_life_balance_score', 5, 2)->nullable();
            $table->integer('ai_suggestions_given')->default(0);
            $table->integer('ai_suggestions_accepted')->default(0);
            $table->decimal('ai_accuracy_rate', 5, 4)->nullable();
            $table->json('profession_metrics')->nullable();
            $table->date('analytics_date');
            
            $table->unique(['user_id', 'analytics_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_analytics', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'analytics_date']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['profession_id']);
            $table->dropColumn([
                'user_id',
                'profession_id',
                'total_events',
                'completed_events',
                'cancelled_events',
                'high_priority_events',
                'total_scheduled_minutes',
                'actual_worked_minutes',
                'break_time_minutes',
                'overtime_minutes',
                'productivity_score',
                'stress_level',
                'work_life_balance_score',
                'ai_suggestions_given',
                'ai_suggestions_accepted',
                'ai_accuracy_rate',
                'profession_metrics',
                'analytics_date'
            ]);
        });
    }
};
