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
        Schema::create('welcome_screens', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->enum('background_type', ['color', 'image', 'video']);
            $table->text('background_value');
            $table->integer('duration');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            
            $table->index('is_active', 'idx_welcome_screens_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('welcome_screens');
    }
};
