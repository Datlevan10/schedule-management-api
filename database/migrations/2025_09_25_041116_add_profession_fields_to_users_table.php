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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('profession_id')->nullable()->constrained('professions');
            $table->enum('profession_level', ['student', 'resident', 'junior', 'senior', 'expert'])->nullable();
            $table->string('workplace')->nullable();
            $table->string('department')->nullable();
            $table->json('work_schedule')->nullable();
            $table->json('work_habits')->nullable();
            $table->json('notification_preferences')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['profession_id']);
            $table->dropColumn([
                'profession_id',
                'profession_level',
                'workplace',
                'department',
                'work_schedule',
                'work_habits',
                'notification_preferences',
                'is_active'
            ]);
        });
    }
};
