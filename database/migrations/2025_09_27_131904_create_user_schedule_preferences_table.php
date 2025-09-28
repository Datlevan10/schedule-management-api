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
        Schema::create('user_schedule_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->enum('preferred_import_format', ['csv', 'excel', 'txt', 'json'])->default('csv');
            $table->foreignId('default_template_id')->nullable()->constrained('schedule_templates');
            $table->string('timezone_preference', 50)->default('Asia/Ho_Chi_Minh');
            $table->string('date_format_preference', 20)->default('dd/mm/yyyy');
            $table->string('time_format_preference', 20)->default('HH:mm');
            $table->boolean('ai_auto_categorize')->default(true);
            $table->boolean('ai_auto_priority')->default(true);
            $table->decimal('ai_confidence_threshold', 3, 2)->default(0.7);
            $table->integer('default_event_duration_minutes')->default(60);
            $table->integer('default_priority')->default(3);
            $table->foreignId('default_category_id')->nullable()->constrained('event_categories');
            $table->boolean('notify_on_import_completion')->default(true);
            $table->boolean('notify_on_parsing_errors')->default(true);
            $table->jsonb('custom_field_mappings')->nullable();
            $table->jsonb('custom_keywords')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_schedule_preferences');
    }
};