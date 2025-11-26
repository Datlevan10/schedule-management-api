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
        Schema::create('admin_customer_reporting_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_name');
            $table->text('description')->nullable();
            $table->json('customer_fields'); // Fields to track about customers
            $table->json('report_filters')->nullable(); // Filters for reporting
            $table->json('aggregation_rules')->nullable(); // How to aggregate data
            $table->string('report_frequency')->default('monthly'); // daily, weekly, monthly, yearly
            $table->json('notification_settings')->nullable(); // Who to notify
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('customer_limit')->nullable(); // Max customers for this template
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('last_generated_at')->nullable();
            $table->integer('total_reports_generated')->default(0);
            $table->decimal('success_rate', 5, 2)->default(100.00); // Percentage
            $table->timestamps();
            
            $table->index(['is_active', 'report_frequency']);
            $table->index(['created_by', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_customer_reporting_templates');
    }
};
