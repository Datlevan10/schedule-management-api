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
        Schema::create('raw_schedule_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('import_type', ['file_upload', 'manual_input', 'text_parsing', 'calendar_sync'])->notNullable();
            $table->enum('source_type', ['csv', 'excel', 'txt', 'json', 'ics', 'manual'])->notNullable();
            $table->string('original_filename', 255)->nullable();
            $table->integer('file_size_bytes')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->text('raw_content')->nullable();
            $table->jsonb('raw_data')->nullable();
            $table->text('file_path')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->integer('total_records_found')->default(0);
            $table->integer('successfully_processed')->default(0);
            $table->integer('failed_records')->default(0);
            $table->jsonb('error_log')->nullable();
            $table->decimal('ai_confidence_score', 3, 2)->nullable();
            $table->string('detected_format', 100)->nullable();
            $table->string('detected_profession', 50)->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status'], 'idx_raw_imports_user_status');
            $table->index(['import_type', 'source_type'], 'idx_raw_imports_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_schedule_imports');
    }
};