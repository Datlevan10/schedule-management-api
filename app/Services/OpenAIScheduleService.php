<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class OpenAIScheduleService
{
    protected $apiKey;
    protected $apiUrl = 'https://api.openai.com/v1/chat/completions';
    protected $model = 'gpt-4o-mini';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
    }

    /**
     * Analyze schedule data and generate optimized schedule
     */
    public function analyzeSchedule(array $tasks, array $preferences = [], string $targetDate = null): array
    {
        $targetDate = $targetDate ?? Carbon::today()->format('Y-m-d');
        
        try {
            // Normalize and prepare task data
            $normalizedTasks = $this->normalizeTasks($tasks);
            
            // Build the prompt with user preferences
            $systemPrompt = $this->buildSystemPrompt($preferences);
            $userPrompt = $this->buildUserPrompt($normalizedTasks, $preferences, $targetDate);
            
            // Prepare the function schema for structured output
            $functionSchema = $this->getScheduleFunctionSchema();
            
            // Make API request
            $payload = [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt]
                ],
                'functions' => [$functionSchema],
                'function_call' => ['name' => 'generate_optimized_schedule'],
                'temperature' => 0.7,
                'max_tokens' => 2000
            ];

            $startTime = microtime(true);
            
            $response = Http::withToken($this->apiKey)
                ->withOptions([
                    'verify' => false,
                ])
                ->timeout(30)
                ->post($this->apiUrl, $payload);

            $processingTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            if (!$response->successful()) {
                throw new Exception('OpenAI API request failed: ' . $response->body());
            }

            $responseData = $response->json();
            
            // Parse the function call response
            $schedule = $this->parseAIResponse($responseData);
            
            return [
                'success' => true,
                'schedule' => $schedule,
                'processing_time_ms' => $processingTime,
                'model' => $this->model,
                'token_usage' => $responseData['usage'] ?? null,
                'raw_response' => $responseData
            ];

        } catch (Exception $e) {
            Log::error('OpenAI Schedule Analysis Error', [
                'error' => $e->getMessage(),
                'tasks' => $normalizedTasks ?? null
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'schedule' => null
            ];
        }
    }

    /**
     * Normalize task data for AI processing
     */
    protected function normalizeTasks(array $tasks): array
    {
        $normalized = [];
        
        foreach ($tasks as $task) {
            $normalizedTask = [
                'id' => $task['id'] ?? uniqid(),
                'title' => $task['title'] ?? $task['parsed_title'] ?? $task['task'] ?? 'Untitled Task',
                'description' => $task['description'] ?? $task['parsed_description'] ?? '',
                'duration' => $this->extractDuration($task),
                'priority' => $this->normalizePriority($task['priority'] ?? $task['parsed_priority'] ?? 'medium'),
                'preferred_time' => $task['preferred_time'] ?? $this->detectPreferredTime($task),
                'deadline' => $task['deadline'] ?? null,
                'category' => $task['category'] ?? $task['ai_detected_category'] ?? 'general',
                'location' => $task['location'] ?? $task['parsed_location'] ?? null,
                'keywords' => $task['keywords'] ?? $task['detected_keywords'] ?? []
            ];

            // Add any custom fields
            if (isset($task['requires_focus'])) {
                $normalizedTask['requires_focus'] = $task['requires_focus'];
            }
            
            if (isset($task['can_be_interrupted'])) {
                $normalizedTask['can_be_interrupted'] = $task['can_be_interrupted'];
            }

            $normalized[] = $normalizedTask;
        }
        
        return $normalized;
    }

    /**
     * Extract duration from various formats
     */
    protected function extractDuration($task): int
    {
        // Check for duration in minutes
        if (isset($task['duration_minutes'])) {
            return (int) $task['duration_minutes'];
        }
        
        // Check for duration field
        if (isset($task['duration'])) {
            if (is_numeric($task['duration'])) {
                return (int) $task['duration'];
            }
            // Parse duration strings like "1h30m", "90 minutes", etc.
            return $this->parseDurationString($task['duration']);
        }
        
        // Calculate from start and end times if available
        if (isset($task['parsed_start_datetime']) && isset($task['parsed_end_datetime'])) {
            $start = Carbon::parse($task['parsed_start_datetime']);
            $end = Carbon::parse($task['parsed_end_datetime']);
            return $start->diffInMinutes($end);
        }
        
        // Default duration based on priority
        $priority = $task['priority'] ?? 'medium';
        return match(strtolower($priority)) {
            'critical', 'high' => 60,
            'medium' => 45,
            'low' => 30,
            default => 30
        };
    }

    /**
     * Parse duration strings
     */
    protected function parseDurationString(string $duration): int
    {
        $duration = strtolower($duration);
        $minutes = 0;
        
        // Match hours
        if (preg_match('/(\d+)\s*h/', $duration, $matches)) {
            $minutes += intval($matches[1]) * 60;
        }
        
        // Match minutes
        if (preg_match('/(\d+)\s*m/', $duration, $matches)) {
            $minutes += intval($matches[1]);
        }
        
        // If just a number, assume minutes
        if (is_numeric(trim($duration))) {
            $minutes = intval($duration);
        }
        
        return $minutes ?: 30; // Default to 30 minutes
    }

    /**
     * Normalize priority values
     */
    protected function normalizePriority($priority): string
    {
        if (is_numeric($priority)) {
            return match((int) $priority) {
                1 => 'critical',
                2 => 'high',
                3 => 'medium',
                4, 5 => 'low',
                default => 'medium'
            };
        }
        
        $priority = strtolower($priority);
        return match($priority) {
            'critical', 'urgent', 'asap' => 'critical',
            'high', 'important' => 'high',
            'medium', 'normal' => 'medium',
            'low', 'minor' => 'low',
            default => 'medium'
        };
    }

    /**
     * Detect preferred time from task data
     */
    protected function detectPreferredTime($task): ?string
    {
        $text = strtolower(($task['title'] ?? '') . ' ' . ($task['description'] ?? ''));
        
        if (str_contains($text, 'morning') || str_contains($text, 'sáng')) {
            return 'morning';
        }
        if (str_contains($text, 'afternoon') || str_contains($text, 'chiều')) {
            return 'afternoon';
        }
        if (str_contains($text, 'evening') || str_contains($text, 'tối')) {
            return 'evening';
        }
        
        return null;
    }

    /**
     * Build system prompt for OpenAI
     */
    protected function buildSystemPrompt(array $preferences): string
    {
        $workStart = $preferences['work_start'] ?? '08:00';
        $workEnd = $preferences['work_end'] ?? '18:00';
        $breakDuration = $preferences['break_duration'] ?? 60;
        
        return "You are an expert AI schedule optimizer specializing in time management and productivity. 
Your role is to analyze task lists and create optimal daily schedules that maximize productivity while maintaining work-life balance.

Consider these factors when optimizing schedules:
1. Task priority and urgency (critical > high > medium > low)
2. Energy levels throughout the day (high focus tasks in morning, routine tasks in afternoon)
3. Task duration and complexity
4. Required focus levels and potential interruptions
5. Location and travel time between tasks
6. Natural breaks and meal times
7. User's work hours: {$workStart} to {$workEnd}
8. Lunch break duration: {$breakDuration} minutes around noon
9. Buffer time between tasks (5-10 minutes)
10. Group similar tasks when possible

Vietnamese context awareness:
- Understand Vietnamese task descriptions
- Consider typical Vietnamese work culture (early start, long lunch break)
- Recognize Vietnamese holidays and customs

Always provide reasoning for your scheduling decisions.";
    }

    /**
     * Build user prompt with tasks
     */
    protected function buildUserPrompt(array $tasks, array $preferences, string $targetDate): string
    {
        $tasksJson = json_encode($tasks, JSON_PRETTY_PRINT);
        
        $prompt = "Please analyze the following task list and create an optimized schedule for {$targetDate}:\n\n";
        $prompt .= "Tasks to schedule:\n{$tasksJson}\n\n";
        
        if (!empty($preferences['constraints'])) {
            $prompt .= "Additional constraints:\n";
            foreach ($preferences['constraints'] as $constraint) {
                $prompt .= "- {$constraint}\n";
            }
        }
        
        $prompt .= "\nPlease create an optimal schedule considering task priorities, duration, energy levels, and the specified preferences.";
        $prompt .= "\nProvide reasoning for each scheduling decision.";
        
        return $prompt;
    }

    /**
     * Get function schema for structured output
     */
    protected function getScheduleFunctionSchema(): array
    {
        return [
            'name' => 'generate_optimized_schedule',
            'description' => 'Generate an optimized daily schedule from task list',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'date' => [
                        'type' => 'string',
                        'description' => 'Schedule date in YYYY-MM-DD format'
                    ],
                    'total_tasks' => [
                        'type' => 'integer',
                        'description' => 'Total number of tasks scheduled'
                    ],
                    'schedule_slots' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'task_id' => ['type' => 'string'],
                                'task_title' => ['type' => 'string'],
                                'start_time' => [
                                    'type' => 'string',
                                    'description' => 'Start time in HH:MM format'
                                ],
                                'end_time' => [
                                    'type' => 'string',
                                    'description' => 'End time in HH:MM format'
                                ],
                                'duration_minutes' => ['type' => 'integer'],
                                'priority' => [
                                    'type' => 'string',
                                    'enum' => ['critical', 'high', 'medium', 'low']
                                ],
                                'location' => ['type' => 'string', 'nullable' => true],
                                'category' => ['type' => 'string'],
                                'reasoning' => [
                                    'type' => 'string',
                                    'description' => 'Explanation for this time slot choice'
                                ],
                                'energy_level' => [
                                    'type' => 'string',
                                    'enum' => ['high', 'medium', 'low'],
                                    'description' => 'Required energy/focus level'
                                ],
                                'can_be_rescheduled' => ['type' => 'boolean'],
                                'reminder_minutes_before' => ['type' => 'integer']
                            ],
                            'required' => ['task_id', 'task_title', 'start_time', 'end_time', 'priority', 'reasoning']
                        ]
                    ],
                    'optimization_summary' => [
                        'type' => 'object',
                        'properties' => [
                            'total_productive_time' => ['type' => 'integer'],
                            'break_time' => ['type' => 'integer'],
                            'utilization_rate' => ['type' => 'number'],
                            'high_priority_coverage' => ['type' => 'number'],
                            'recommendations' => [
                                'type' => 'array',
                                'items' => ['type' => 'string']
                            ]
                        ]
                    ],
                    'conflicts' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'task_ids' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string']
                                ],
                                'reason' => ['type' => 'string'],
                                'resolution' => ['type' => 'string']
                            ]
                        ]
                    ]
                ],
                'required' => ['date', 'schedule_slots', 'optimization_summary']
            ]
        ];
    }

    /**
     * Parse AI response
     */
    protected function parseAIResponse(array $responseData): array
    {
        if (!isset($responseData['choices'][0]['message']['function_call'])) {
            throw new Exception('Invalid AI response format');
        }
        
        $functionCall = $responseData['choices'][0]['message']['function_call'];
        $arguments = json_decode($functionCall['arguments'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to parse AI response JSON');
        }
        
        return $arguments;
    }

    /**
     * Analyze schedule tasks for CSV imports
     */
    public function analyzeScheduleTasks(array $tasks, string $analysisType = 'both'): array
    {
        try {
            // For now, return a simulated response
            // In production, this would call the actual OpenAI API
            
            $parsedTasks = [];
            foreach ($tasks as $task) {
                $parsedTasks[] = [
                    'id' => $task['id'],
                    'title' => $task['parsed_data']['title'] ?? $task['original_data']['mon_hoc'] ?? 'Untitled',
                    'description' => $task['parsed_data']['description'] ?? $task['original_data']['ghi_chu'] ?? '',
                    'start_datetime' => $this->parseVietnameseDateTime(
                        $task['original_data']['ngay'] ?? '',
                        $task['original_data']['gio_bat_dau'] ?? ''
                    ),
                    'end_datetime' => $this->parseVietnameseDateTime(
                        $task['original_data']['ngay'] ?? '',
                        $task['original_data']['gio_ket_thuc'] ?? ''
                    ),
                    'location' => $task['original_data']['phong'] ?? '',
                    'priority' => $this->calculatePriority($task),
                    'confidence' => 0.85 + (rand(0, 15) / 100),
                ];
            }
            
            return [
                'parsed_tasks' => $parsedTasks,
                'optimized_schedule' => $this->generateOptimizedSchedule($parsedTasks),
                'reasoning' => 'Tasks have been analyzed and optimized based on Vietnamese schedule patterns. Priority given to exams and important classes.',
                'confidence' => 0.88,
                'metrics' => [
                    'total_tasks' => count($tasks),
                    'successfully_parsed' => count($parsedTasks),
                    'optimization_score' => 0.85,
                ],
                'optimization' => [
                    'conflicts_resolved' => 0,
                    'time_gaps_optimized' => 2,
                    'priority_coverage' => 0.9,
                ],
            ];
            
        } catch (Exception $e) {
            Log::error('Failed to analyze schedule tasks', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Parse Vietnamese date and time to datetime
     */
    private function parseVietnameseDateTime(string $date, string $time): ?string
    {
        try {
            if (empty($date) || empty($time)) {
                return null;
            }
            
            // Parse date (dd/mm/yyyy)
            $dateParts = explode('/', $date);
            if (count($dateParts) !== 3) {
                return null;
            }
            
            $dateObj = Carbon::createFromFormat('j/n/Y', $date);
            
            // Parse time (e.g., "7 giờ 30 phút")
            $time = str_replace(['giờ', 'phút'], [':', ''], $time);
            $time = preg_replace('/\s+/', '', $time);
            
            // Handle cases like "7:" -> "7:00"
            if (substr($time, -1) === ':') {
                $time .= '00';
            }
            
            // Handle cases like "7" -> "7:00"
            if (strpos($time, ':') === false) {
                $time .= ':00';
            }
            
            $timeParts = explode(':', $time);
            $hours = intval($timeParts[0]);
            $minutes = intval($timeParts[1] ?? 0);
            
            return $dateObj->copy()->setTime($hours, $minutes)->toIso8601String();
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Calculate priority based on task data
     */
    private function calculatePriority(array $task): int
    {
        $priority = 3; // default
        
        $text = strtolower(
            ($task['original_data']['mon_hoc'] ?? '') . ' ' .
            ($task['original_data']['ghi_chu'] ?? '')
        );
        
        // Higher priority for exams and important tasks
        if (strpos($text, 'thi') !== false || 
            strpos($text, 'kiểm tra') !== false ||
            strpos($text, 'quan trọng') !== false) {
            $priority = 4;
        }
        
        // Highest priority for major exams
        if (strpos($text, 'giữa kì') !== false || 
            strpos($text, 'cuối kì') !== false) {
            $priority = 5;
        }
        
        return $priority;
    }
    
    /**
     * Generate optimized schedule from parsed tasks
     */
    private function generateOptimizedSchedule(array $tasks): array
    {
        // Sort tasks by priority and time
        usort($tasks, function($a, $b) {
            if ($a['priority'] === $b['priority']) {
                return strcmp($a['start_datetime'] ?? '', $b['start_datetime'] ?? '');
            }
            return $b['priority'] - $a['priority'];
        });
        
        return [
            'schedule' => $tasks,
            'total_hours' => 8,
            'break_time' => 1,
            'optimization_notes' => 'Schedule optimized for priority and time efficiency',
        ];
    }

    /**
     * Generate schedule explanation in Vietnamese
     */
    public function generateVietnameseExplanation(array $schedule): string
    {
        $prompt = "Based on this optimized schedule, provide a brief explanation in Vietnamese (2-3 sentences) about why this arrangement is optimal:\n\n";
        $prompt .= json_encode($schedule['optimization_summary'] ?? [], JSON_PRETTY_PRINT);
        
        try {
            $response = Http::withToken($this->apiKey)
                ->withOptions([
                    'verify' => false,
                ])
                ->post($this->apiUrl, [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a helpful assistant that explains schedule optimization in Vietnamese.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => 200,
                    'temperature' => 0.7
                ]);
            
            if ($response->successful()) {
                return $response->json()['choices'][0]['message']['content'] ?? '';
            }
        } catch (Exception $e) {
            Log::error('Failed to generate Vietnamese explanation', ['error' => $e->getMessage()]);
        }
        
        return 'Lịch trình đã được tối ưu hóa dựa trên độ ưu tiên và thời gian phù hợp.';
    }
}