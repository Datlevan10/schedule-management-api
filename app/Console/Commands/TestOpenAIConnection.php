<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OpenAIScheduleService;

class TestOpenAIConnection extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:test-connection';

    /**
     * The console command description.
     */
    protected $description = 'Test OpenAI API connection and schedule analysis';

    protected $openAIService;

    /**
     * Create a new command instance.
     */
    public function __construct(OpenAIScheduleService $openAIService)
    {
        parent::__construct();
        $this->openAIService = $openAIService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔑 Testing OpenAI API connection...');

        // Check if API key is set
        $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        if (!$apiKey) {
            $this->error('❌ OPENAI_API_KEY not found in .env file');
            return 1;
        }

        $this->info('✅ API Key loaded: ' . substr($apiKey, 0, 20) . '...');

        // Test sample tasks
        $sampleTasks = [
            [
                'id' => '1',
                'title' => 'Họp team hàng tuần',
                'description' => 'Cuộc họp đồng bộ với team',
                'duration' => 60,
                'priority' => 'high',
                'preferred_time' => 'morning',
                'category' => 'meeting'
            ],
            [
                'id' => '2', 
                'title' => 'Viết báo cáo dự án',
                'description' => 'Hoàn thiện báo cáo tiến độ dự án',
                'duration' => 120,
                'priority' => 'medium',
                'category' => 'work'
            ],
            [
                'id' => '3',
                'title' => 'Đánh giá hiệu suất nhân viên',
                'description' => 'Review performance Q4',
                'duration' => 90,
                'priority' => 'high',
                'preferred_time' => 'afternoon',
                'category' => 'management'
            ]
        ];

        $preferences = [
            'work_start' => '08:00',
            'work_end' => '18:00',
            'break_duration' => 60,
            'constraints' => [
                'Không có cuộc họp sau 17:00',
                'Nghỉ trưa từ 12:00-13:00'
            ]
        ];

        $this->info('🤖 Analyzing sample schedule with ChatGPT...');
        $this->info('📝 Sample tasks: ' . count($sampleTasks) . ' items');

        $result = $this->openAIService->analyzeSchedule($sampleTasks, $preferences, '2025-03-01');

        if ($result['success']) {
            $this->info('✅ AI Schedule Analysis Successful!');
            $this->info('⏱️  Processing time: ' . $result['processing_time_ms'] . 'ms');
            
            if (isset($result['token_usage'])) {
                $this->info('🔢 Tokens used: ' . $result['token_usage']['total_tokens']);
            }

            $schedule = $result['schedule'];
            $this->info('📅 Optimized schedule for ' . $schedule['date']);
            $this->info('📊 Total tasks scheduled: ' . ($schedule['total_tasks'] ?? 0));

            if (isset($schedule['schedule_slots'])) {
                $this->info('📋 Schedule slots:');
                foreach ($schedule['schedule_slots'] as $slot) {
                    $this->line("  ⏰ {$slot['start_time']}-{$slot['end_time']}: {$slot['task_title']} ({$slot['priority']})");
                    if (isset($slot['reasoning'])) {
                        $this->line("     💡 {$slot['reasoning']}");
                    }
                }
            }

            if (isset($schedule['optimization_summary'])) {
                $summary = $schedule['optimization_summary'];
                $this->info('📈 Optimization Summary:');
                if (isset($summary['utilization_rate'])) {
                    $rate = round($summary['utilization_rate'] * 100, 1);
                    $this->line("  📊 Utilization Rate: {$rate}%");
                }
                if (isset($summary['recommendations'])) {
                    $this->line("  💡 Recommendations:");
                    foreach ($summary['recommendations'] as $rec) {
                        $this->line("     - {$rec}");
                    }
                }
            }

            $this->info('🎉 OpenAI integration is working perfectly!');
            return 0;

        } else {
            $this->error('❌ AI Schedule Analysis Failed!');
            $this->error('📝 Error: ' . $result['error']);
            return 1;
        }
    }
}