<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Event;
use Carbon\Carbon;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $events = [
            [
                'title' => 'Annual Company Meeting',
                'description' => 'Yearly gathering to discuss company progress and future plans',
                'start_date' => Carbon::now()->addDays(10)->setTime(9, 0),
                'end_date' => Carbon::now()->addDays(10)->setTime(17, 0),
                'location' => 'Main Conference Hall',
                'status' => 'pending',
                'max_participants' => 200,
                'meta_data' => json_encode(['catering' => true, 'virtual_option' => true])
            ],
            [
                'title' => 'Tech Workshop: Laravel Advanced',
                'description' => 'Deep dive into advanced Laravel techniques and best practices',
                'start_date' => Carbon::now()->addDays(5)->setTime(14, 0),
                'end_date' => Carbon::now()->addDays(5)->setTime(18, 0),
                'location' => 'Training Room B',
                'status' => 'active',
                'max_participants' => 30,
                'meta_data' => json_encode(['level' => 'advanced', 'materials_provided' => true])
            ],
            [
                'title' => 'Team Building Event',
                'description' => 'Outdoor activities to strengthen team bonds',
                'start_date' => Carbon::now()->subDays(5)->setTime(8, 0),
                'end_date' => Carbon::now()->subDays(5)->setTime(16, 0),
                'location' => 'City Park',
                'status' => 'completed',
                'max_participants' => 50,
                'meta_data' => json_encode(['activities' => ['games', 'lunch', 'presentations']])
            ],
            [
                'title' => 'Product Launch Webinar',
                'description' => 'Introducing our latest product features to clients',
                'start_date' => Carbon::now()->addDays(15)->setTime(11, 0),
                'end_date' => Carbon::now()->addDays(15)->setTime(12, 30),
                'location' => 'Online - Zoom',
                'status' => 'pending',
                'max_participants' => 500,
                'meta_data' => json_encode(['platform' => 'Zoom', 'recording_available' => true])
            ],
            [
                'title' => 'Monthly Status Update',
                'description' => 'Regular monthly meeting for project status updates',
                'start_date' => Carbon::now()->addDays(3)->setTime(10, 0),
                'end_date' => Carbon::now()->addDays(3)->setTime(11, 0),
                'location' => 'Meeting Room A',
                'status' => 'active',
                'max_participants' => 15,
                'meta_data' => json_encode(['recurring' => true, 'frequency' => 'monthly'])
            ],
            [
                'title' => 'Developer Conference 2025',
                'description' => 'Annual developer conference with speakers from around the world',
                'start_date' => Carbon::now()->addDays(30)->setTime(9, 0),
                'end_date' => Carbon::now()->addDays(32)->setTime(18, 0),
                'location' => 'Convention Center',
                'status' => 'pending',
                'max_participants' => 1000,
                'meta_data' => json_encode(['speakers' => 25, 'tracks' => 5, 'workshops' => 10])
            ],
            [
                'title' => 'Cancelled Workshop',
                'description' => 'This workshop was cancelled due to low registration',
                'start_date' => Carbon::now()->subDays(2)->setTime(14, 0),
                'end_date' => Carbon::now()->subDays(2)->setTime(16, 0),
                'location' => 'Training Room C',
                'status' => 'cancelled',
                'max_participants' => 20,
                'meta_data' => json_encode(['reason' => 'low_registration'])
            ],
            [
                'title' => 'Client Presentation',
                'description' => 'Project proposal presentation to key stakeholders',
                'start_date' => Carbon::now()->addDays(2)->setTime(15, 0),
                'end_date' => Carbon::now()->addDays(2)->setTime(16, 30),
                'location' => 'Board Room',
                'status' => 'active',
                'max_participants' => 10,
                'meta_data' => json_encode(['client' => 'ABC Corp', 'project' => 'New Platform'])
            ]
        ];

        foreach ($events as $event) {
            Event::create($event);
        }
    }
}
