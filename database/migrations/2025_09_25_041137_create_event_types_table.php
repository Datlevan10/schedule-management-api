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
        Schema::create('event_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profession_id')->constrained('professions');
            $table->string('name');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable();
            $table->string('icon')->nullable();
            $table->integer('default_priority')->default(3);
            $table->decimal('ai_priority_weight', 3, 2)->default(1.00);
            $table->json('keywords')->nullable();
            $table->boolean('requires_preparation')->default(false);
            $table->integer('preparation_days')->default(0);
            $table->integer('default_duration_minutes')->default(60);
            $table->boolean('allows_conflicts')->default(false);
            $table->boolean('is_recurring_allowed')->default(true);
            $table->timestamps();
            
            $table->unique(['profession_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_types');
    }
};
