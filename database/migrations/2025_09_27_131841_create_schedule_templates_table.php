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
        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profession_id')->nullable()->constrained();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('template_type', ['csv_format', 'text_pattern', 'json_schema'])->notNullable();
            $table->jsonb('field_mapping')->nullable();
            $table->jsonb('required_fields')->nullable();
            $table->jsonb('optional_fields')->nullable();
            $table->jsonb('default_values')->nullable();
            $table->jsonb('date_formats')->nullable();
            $table->jsonb('time_formats')->nullable();
            $table->jsonb('keyword_patterns')->nullable();
            $table->jsonb('validation_rules')->nullable();
            $table->jsonb('ai_processing_rules')->nullable();
            $table->integer('usage_count')->default(0);
            $table->decimal('success_rate', 3, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            
            // Indexes
            $table->index(['profession_id', 'is_active'], 'idx_templates_profession_active');
            $table->index('template_type', 'idx_templates_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_templates');
    }
};