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
        Schema::create('parsing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_name', 255);
            $table->foreignId('profession_id')->nullable()->constrained();
            $table->enum('rule_type', ['keyword_detection', 'pattern_matching', 'priority_calculation', 'category_assignment'])->notNullable();
            $table->text('rule_pattern');
            $table->jsonb('rule_action');
            $table->jsonb('conditions')->nullable();
            $table->integer('priority_order')->default(100);
            $table->jsonb('positive_examples')->nullable();
            $table->jsonb('negative_examples')->nullable();
            $table->decimal('accuracy_rate', 3, 2)->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            
            // Indexes
            $table->index(['profession_id', 'rule_type'], 'idx_parsing_rules_profession_type');
            $table->index(['priority_order', 'is_active'], 'idx_parsing_rules_priority_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parsing_rules');
    }
};