<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Profession;

class ProfessionSeeder extends Seeder
{
    public function run(): void
    {
        $professions = [
            [
                'name' => 'student',
                'display_name' => 'Student',
                'description' => 'Academic students from all levels',
                'default_categories' => [
                    'Exams', 'Assignments', 'Classes', 'Study Groups', 'Projects'
                ],
                'default_priorities' => [
                    'exam' => 5,
                    'assignment' => 4,
                    'class' => 3,
                    'study' => 2,
                    'social' => 1
                ],
                'ai_keywords' => [
                    'exam', 'test', 'assignment', 'homework', 'class', 'lecture',
                    'study', 'quiz', 'midterm', 'final', 'project', 'thesis'
                ]
            ],
            [
                'name' => 'doctor',
                'display_name' => 'Doctor / Medical Professional',
                'description' => 'Medical doctors and healthcare professionals',
                'default_categories' => [
                    'Surgery', 'Consultations', 'Rounds', 'Emergency', 'Research'
                ],
                'default_priorities' => [
                    'emergency' => 5,
                    'surgery' => 5,
                    'consultation' => 4,
                    'rounds' => 3,
                    'research' => 2
                ],
                'ai_keywords' => [
                    'surgery', 'patient', 'consultation', 'emergency', 'rounds',
                    'appointment', 'treatment', 'diagnosis', 'procedure', 'clinic'
                ]
            ],
            [
                'name' => 'teacher',
                'display_name' => 'Teacher / Educator',
                'description' => 'Teachers and educational professionals',
                'default_categories' => [
                    'Classes', 'Lesson Planning', 'Grading', 'Meetings', 'Training'
                ],
                'default_priorities' => [
                    'class' => 5,
                    'meeting' => 4,
                    'grading' => 3,
                    'planning' => 2,
                    'training' => 2
                ],
                'ai_keywords' => [
                    'class', 'lesson', 'grade', 'meeting', 'parent', 'student',
                    'curriculum', 'training', 'workshop', 'conference'
                ]
            ],
            [
                'name' => 'engineer',
                'display_name' => 'Engineer / Technical Professional',
                'description' => 'Engineers and technical professionals',
                'default_categories' => [
                    'Development', 'Meetings', 'Testing', 'Documentation', 'Research'
                ],
                'default_priorities' => [
                    'deadline' => 5,
                    'meeting' => 4,
                    'development' => 3,
                    'testing' => 3,
                    'documentation' => 2
                ],
                'ai_keywords' => [
                    'code', 'development', 'meeting', 'deadline', 'testing',
                    'review', 'design', 'architecture', 'deploy', 'debug'
                ]
            ],
            [
                'name' => 'business',
                'display_name' => 'Business Professional',
                'description' => 'Business professionals and managers',
                'default_categories' => [
                    'Meetings', 'Presentations', 'Negotiations', 'Planning', 'Travel'
                ],
                'default_priorities' => [
                    'client' => 5,
                    'presentation' => 4,
                    'meeting' => 3,
                    'planning' => 2,
                    'travel' => 2
                ],
                'ai_keywords' => [
                    'meeting', 'client', 'presentation', 'negotiation', 'deal',
                    'proposal', 'budget', 'planning', 'strategy', 'report'
                ]
            ]
        ];

        foreach ($professions as $profession) {
            Profession::create($profession);
        }
    }
}