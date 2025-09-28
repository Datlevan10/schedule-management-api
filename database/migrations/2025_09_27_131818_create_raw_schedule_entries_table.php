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
        Schema::create('raw_schedule_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('raw_schedule_imports')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('row_number')->nullable();
            $table->text('raw_text')->nullable();
            $table->jsonb('original_data')->nullable();
            $table->string('parsed_title', 255)->nullable();
            $table->text('parsed_description')->nullable();
            $table->timestamp('parsed_start_datetime')->nullable();
            $table->timestamp('parsed_end_datetime')->nullable();
            $table->string('parsed_location', 255)->nullable();
            $table->integer('parsed_priority')->nullable();
            $table->jsonb('detected_keywords')->nullable();
            $table->jsonb('ai_parsed_data')->nullable();
            $table->decimal('ai_confidence', 3, 2)->nullable();
            $table->string('ai_detected_category', 100)->nullable();
            $table->decimal('ai_detected_importance', 3, 2)->nullable();
            $table->enum('processing_status', ['pending', 'parsed', 'converted', 'failed'])->default('pending');
            $table->enum('conversion_status', ['pending', 'success', 'failed', 'manual_review'])->default('pending');
            $table->foreignId('converted_event_id')->nullable()->constrained('events')->onDelete('set null');
            $table->jsonb('parsing_errors')->nullable();
            $table->boolean('manual_review_required')->default(false);
            $table->text('manual_review_notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['import_id', 'user_id'], 'idx_raw_entries_import_user');
            $table->index('conversion_status', 'idx_raw_entries_conversion_status');
            $table->index('manual_review_required', 'idx_raw_entries_manual_review');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_schedule_entries');
    }
};