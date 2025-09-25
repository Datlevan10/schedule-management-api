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
        Schema::table('event_categories', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('event_type_id')->nullable()->constrained('event_types');
            $table->string('name');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable();
            $table->string('icon')->nullable();
            $table->integer('priority')->default(3);
            $table->decimal('ai_priority_weight', 3, 2)->default(1.00);
            $table->json('custom_keywords')->nullable();
            $table->integer('preparation_days')->default(0);
            $table->boolean('is_active')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['event_type_id']);
            $table->dropColumn([
                'user_id',
                'event_type_id',
                'name',
                'display_name',
                'description',
                'color',
                'icon',
                'priority',
                'ai_priority_weight',
                'custom_keywords',
                'preparation_days',
                'is_active'
            ]);
        });
    }
};
