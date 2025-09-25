<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, ensure we have at least one user in the system for default assignment
        $defaultUserId = DB::table('users')->value('id');
        if (!$defaultUserId) {
            // Create a default user if none exists
            $defaultUserId = DB::table('users')->insertGetId([
                'name' => 'Default User',
                'email' => 'default@example.com',
                'password' => bcrypt('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('events', function (Blueprint $table) {
            // Add user_id as nullable first
            $table->unsignedBigInteger('user_id')->nullable();
            $table->integer('priority')->default(3);
            $table->decimal('ai_calculated_priority', 5, 2)->nullable();
            $table->decimal('importance_score', 5, 2)->nullable();
            $table->json('event_metadata')->nullable();
            $table->json('participants')->nullable();
            $table->json('requirements')->nullable();
            $table->json('preparation_items')->nullable();
            $table->integer('completion_percentage')->default(0);
            $table->json('recurring_pattern')->nullable();
            $table->unsignedBigInteger('parent_event_id')->nullable();
        });

        // Update existing records to have a default user
        DB::statement("UPDATE events SET user_id = $defaultUserId WHERE user_id IS NULL");

        Schema::table('events', function (Blueprint $table) {
            // Now add foreign key constraints and make user_id not null
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('parent_event_id')->references('id')->on('events');
            
            // Make user_id not null using raw SQL
            DB::statement('ALTER TABLE events ALTER COLUMN user_id SET NOT NULL');
            
            // Rename columns
            $table->renameColumn('start_date', 'start_datetime');
            $table->renameColumn('end_date', 'end_datetime');
            $table->renameColumn('category_id', 'event_category_id');
            
            // Drop old columns first
            $table->dropColumn(['meta_data', 'max_participants']);
        });

        // Drop and recreate status enum with new values
        DB::statement("ALTER TABLE events DROP COLUMN status");
        
        Schema::table('events', function (Blueprint $table) {
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled', 'postponed'])->default('scheduled');
            
            // Add indexes
            $table->index(['user_id', 'start_datetime'], 'idx_user_datetime');
            $table->index(['user_id', 'status'], 'idx_user_status');
            $table->index(['start_datetime', 'end_datetime'], 'idx_datetime_range');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex('idx_user_datetime');
            $table->dropIndex('idx_user_status');
            $table->dropIndex('idx_datetime_range');
            
            // Drop status and recreate old one
            $table->dropColumn('status');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])->default('pending');
            
            // Rename columns back
            $table->renameColumn('start_datetime', 'start_date');
            $table->renameColumn('end_datetime', 'end_date');
            $table->renameColumn('event_category_id', 'category_id');
            
            // Add back old columns
            $table->json('meta_data')->nullable();
            $table->integer('max_participants')->nullable();
        });

        Schema::table('events', function (Blueprint $table) {
            // Drop foreign keys and new columns
            $table->dropForeign(['user_id']);
            $table->dropForeign(['parent_event_id']);
            
            $table->dropColumn([
                'user_id',
                'priority',
                'ai_calculated_priority',
                'importance_score',
                'event_metadata',
                'participants',
                'requirements',
                'preparation_items',
                'completion_percentage',
                'recurring_pattern',
                'parent_event_id'
            ]);
        });
    }
};
