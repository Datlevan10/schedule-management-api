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
        Schema::create('schedule_import_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profession_id')->nullable()->constrained();
            $table->string('template_name', 255);
            $table->text('template_description')->nullable();
            $table->enum('file_type', ['csv', 'excel', 'json', 'txt']);
            $table->string('template_version', 20)->default('1.0');
            
            // Template Structure Fields
            $table->string('sample_title', 255)->nullable();
            $table->text('sample_description')->nullable();
            $table->string('sample_start_datetime', 50)->nullable();
            $table->string('sample_end_datetime', 50)->nullable();
            $table->string('sample_location', 255)->nullable();
            $table->string('sample_priority', 20)->nullable();
            $table->string('sample_category', 100)->nullable();
            $table->text('sample_keywords')->nullable();
            
            // Format Specifications
            $table->string('date_format_example', 50)->nullable();
            $table->string('time_format_example', 50)->nullable();
            $table->jsonb('required_columns')->nullable();
            $table->jsonb('optional_columns')->nullable();
            $table->jsonb('column_descriptions')->nullable();
            
            // Template Files
            $table->text('template_file_path')->nullable();
            $table->text('sample_data_file_path')->nullable();
            $table->text('instructions_file_path')->nullable();
            
            // AI Processing Info
            $table->jsonb('ai_keywords_examples')->nullable();
            $table->jsonb('priority_detection_rules')->nullable();
            $table->jsonb('category_mapping_examples')->nullable();
            
            // Usage Statistics
            $table->integer('download_count')->default(0);
            $table->decimal('success_import_rate', 3, 2)->nullable();
            $table->decimal('user_feedback_rating', 2, 1)->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            
            // Indexes
            $table->index(['profession_id', 'is_active'], 'idx_profession_active_templates');
            $table->index('file_type', 'idx_file_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_import_templates');
    }
};