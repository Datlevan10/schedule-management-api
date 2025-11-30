<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admins = [
            [
                'name' => 'Super Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('admin123'),
                'role' => 'super_admin',
                'department' => 'IT Administration',
                'phone' => '+1234567890',
                'permissions' => Admin::getDefaultPermissions('super_admin'),
                'can_create_admins' => true,
                'can_delete_users' => true,
                'can_manage_templates' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Admin User',
                'email' => 'admin2@example.com',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'department' => 'Customer Support',
                'phone' => '+1234567891',
                'permissions' => Admin::getDefaultPermissions('admin'),
                'can_create_admins' => false,
                'can_delete_users' => false,
                'can_manage_templates' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Template Manager',
                'email' => 'templates@example.com',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'department' => 'Content Management',
                'phone' => '+1234567892',
                'permissions' => ['manage_templates', 'view_reports'],
                'can_create_admins' => false,
                'can_delete_users' => false,
                'can_manage_templates' => true,
                'is_active' => true,
            ]
        ];

        foreach ($admins as $adminData) {
            Admin::create($adminData);
        }

        $this->command->info('Admin accounts created successfully!');
        $this->command->info('Super Admin: admin@example.com / admin123');
        $this->command->info('Admin User: admin2@example.com / admin123');
        $this->command->info('Template Manager: templates@example.com / admin123');
    }
}
