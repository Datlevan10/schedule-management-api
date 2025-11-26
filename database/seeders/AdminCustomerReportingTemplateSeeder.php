<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AdminCustomerReportingTemplate;
use App\Models\User;

class AdminCustomerReportingTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first user as creator (admin)
        $adminUser = User::first();
        
        if (!$adminUser) {
            $this->command->error('No users found. Please create a user first.');
            return;
        }

        $templates = [
            [
                'template_name' => 'Monthly Customer Activity Report',
                'description' => 'Track customer login frequency, feature usage, and engagement metrics on a monthly basis',
                'customer_fields' => ['name', 'email', 'profession_id', 'workplace', 'is_active', 'created_at'],
                'report_filters' => [
                    'is_active' => true,
                    'created_at' => 'last_30_days'
                ],
                'aggregation_rules' => [
                    'name' => 'count',
                    'email' => 'count',
                    'profession_id' => 'group_by',
                    'workplace' => 'group_by',
                    'is_active' => 'count',
                    'created_at' => 'count'
                ],
                'report_frequency' => 'monthly',
                'notification_settings' => [
                    'email_recipients' => ['admin@example.com'],
                    'notify_on_generation' => true,
                    'include_summary' => true
                ],
                'is_active' => true,
                'is_default' => true,
                'customer_limit' => 1000,
                'created_by' => $adminUser->id,
            ],
            [
                'template_name' => 'Weekly New Customers Report',
                'description' => 'Weekly report of new customer registrations and their profession distribution',
                'customer_fields' => ['name', 'email', 'profession_id', 'created_at'],
                'report_filters' => [
                    'created_at' => 'last_7_days'
                ],
                'aggregation_rules' => [
                    'name' => 'count',
                    'email' => 'count',
                    'profession_id' => 'group_by',
                    'created_at' => 'count'
                ],
                'report_frequency' => 'weekly',
                'notification_settings' => [
                    'email_recipients' => ['admin@example.com', 'marketing@example.com'],
                    'notify_on_generation' => true,
                    'include_charts' => true
                ],
                'is_active' => true,
                'is_default' => false,
                'customer_limit' => null,
                'created_by' => $adminUser->id,
            ],
            [
                'template_name' => 'Profession-Based Customer Analysis',
                'description' => 'Analyze customer distribution and engagement by professional categories',
                'customer_fields' => ['profession_id', 'profession_level', 'workplace', 'department', 'is_active'],
                'report_filters' => [
                    'is_active' => true
                ],
                'aggregation_rules' => [
                    'profession_id' => 'group_by',
                    'profession_level' => 'group_by',
                    'workplace' => 'unique_count',
                    'department' => 'group_by',
                    'is_active' => 'count'
                ],
                'report_frequency' => 'monthly',
                'notification_settings' => [
                    'email_recipients' => ['hr@example.com', 'admin@example.com'],
                    'notify_on_generation' => false,
                    'auto_generate' => true
                ],
                'is_active' => true,
                'is_default' => false,
                'customer_limit' => 500,
                'created_by' => $adminUser->id,
            ],
            [
                'template_name' => 'Quarterly Customer Retention Report',
                'description' => 'Track customer retention and churn patterns over quarterly periods',
                'customer_fields' => ['email', 'created_at', 'is_active', 'profession_id'],
                'report_filters' => [
                    'created_at' => 'last_90_days'
                ],
                'aggregation_rules' => [
                    'email' => 'count',
                    'created_at' => 'group_by',
                    'is_active' => 'count',
                    'profession_id' => 'group_by'
                ],
                'report_frequency' => 'yearly',
                'notification_settings' => [
                    'email_recipients' => ['ceo@example.com', 'admin@example.com'],
                    'notify_on_generation' => true,
                    'priority' => 'high'
                ],
                'is_active' => false,
                'is_default' => false,
                'customer_limit' => 2000,
                'created_by' => $adminUser->id,
            ]
        ];

        foreach ($templates as $template) {
            AdminCustomerReportingTemplate::create($template);
        }

        $this->command->info('Admin Customer Reporting Templates seeded successfully!');
    }
}
