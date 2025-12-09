<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\RawScheduleEntry;
use App\Models\User;
use App\Models\AiScheduleAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIScheduleController extends Controller
{
    /**
     * Get all user tasks from both manual (events) and imported (raw_schedule_entries) sources
     * for user selection and AI analysis
     * GET /api/v1/tasks/user/{userId}/list
     */
    public function getUserTasksForSelection(Request $request, $userId): JsonResponse
    {
        try {
            // Validate user exists
            $user = User::findOrFail($userId);

            // Get manual tasks from events table
            $manualTasks = Event::where('user_id', $userId)
                ->when($request->has('status'), function($q) use ($request) {
                    $q->where('status', $request->status);
                })
                ->when($request->has('date_from'), function($q) use ($request) {
                    $q->where('start_datetime', '>=', $request->date_from);
                })
                ->when($request->has('date_to'), function($q) use ($request) {
                    $q->where('end_datetime', '<=', $request->date_to);
                })
                ->when($request->boolean('upcoming_only'), function($q) {
                    $q->where('start_datetime', '>', now());
                })
                ->with(['category'])
                ->orderBy('start_datetime')
                ->get()
                ->map(function($event) {
                    return [
                        'task_id' => "manual_{$event->id}",
                        'source' => 'manual',
                        'source_id' => $event->id,
                        'title' => $event->title,
                        'description' => $event->description,
                        'start_datetime' => $event->start_datetime,
                        'end_datetime' => $event->end_datetime,
                        'location' => $event->location,
                        'status' => $event->status,
                        'priority' => $event->priority,
                        'completion_percentage' => $event->completion_percentage,
                        'category' => $event->category?->name,
                        'duration_minutes' => $event->start_datetime && $event->end_datetime 
                            ? $event->start_datetime->diffInMinutes($event->end_datetime) 
                            : null,
                        'is_selectable' => true,
                        'created_at' => $event->created_at,
                        'metadata' => [
                            'manually_created' => $event->event_metadata['created_manually'] ?? false,
                            'task_type' => $event->event_metadata['task_type'] ?? 'general'
                        ]
                    ];
                });

            // Get imported tasks from raw_schedule_entries table that are converted to events
            $importedTasks = RawScheduleEntry::where('user_id', $userId)
                ->where('conversion_status', 'success') // Only successfully converted
                ->when($request->has('date_from'), function($q) use ($request) {
                    $q->where('parsed_start_datetime', '>=', $request->date_from);
                })
                ->when($request->has('date_to'), function($q) use ($request) {
                    $q->where('parsed_end_datetime', '<=', $request->date_to);
                })
                ->when($request->boolean('upcoming_only'), function($q) {
                    $q->where('parsed_start_datetime', '>', now());
                })
                ->with(['import', 'convertedEvent'])
                ->orderBy('parsed_start_datetime')
                ->get()
                ->map(function($entry) {
                    return [
                        'task_id' => "imported_{$entry->id}",
                        'source' => 'imported',
                        'source_id' => $entry->id,
                        'import_id' => $entry->import_id,
                        'title' => $entry->parsed_title,
                        'description' => $entry->parsed_description,
                        'start_datetime' => $entry->parsed_start_datetime,
                        'end_datetime' => $entry->parsed_end_datetime,
                        'location' => $entry->parsed_location,
                        'priority' => $entry->parsed_priority,
                        'ai_confidence' => $entry->ai_confidence,
                        'ai_detected_category' => $entry->ai_detected_category,
                        'duration_minutes' => $entry->parsed_duration_minutes,
                        'is_selectable' => true,
                        'created_at' => $entry->created_at,
                        'metadata' => [
                            'source_type' => $entry->import?->source_type,
                            'import_type' => $entry->import?->import_type,
                            'original_text' => $entry->raw_text,
                            'converted_event_id' => $entry->converted_event_id,
                            'ai_confidence' => $entry->ai_confidence,
                            'requires_review' => $entry->manual_review_required
                        ]
                    ];
                });

            // Combine and sort by start_datetime
            $allTasks = $manualTasks->concat($importedTasks)
                ->sortBy('start_datetime')
                ->values();

            // Prepare summary for frontend
            $summary = [
                'total_tasks' => $allTasks->count(),
                'manual_tasks' => $manualTasks->count(),
                'imported_tasks' => $importedTasks->count(),
                'selectable_tasks' => $allTasks->where('is_selectable', true)->count(),
                'date_range' => [
                    'earliest' => $allTasks->first()['start_datetime'] ?? null,
                    'latest' => $allTasks->last()['start_datetime'] ?? null
                ],
                'filters_available' => [
                    'by_source' => ['manual', 'imported'],
                    'by_priority' => [1, 2, 3, 4, 5],
                    'by_status' => ['scheduled', 'in_progress', 'completed', 'cancelled', 'postponed']
                ]
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'User tasks retrieved for selection',
                'data' => [
                    'user_id' => $userId,
                    'user_name' => $user->name,
                    'tasks' => $allTasks,
                    'summary' => $summary,
                    'selection_info' => [
                        'instruction' => 'Select tasks you want to analyze with AI',
                        'min_selection' => 1,
                        'max_selection' => null,
                        'recommended_selection' => 'Select 5-15 tasks for optimal AI analysis'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get user tasks for selection', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve user tasks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all user tasks from both manual (events) and imported (raw_schedule_entries) sources
     * for AI analysis (original method)
     * GET /api/v1/ai-schedule/user/{userId}/tasks
     */
    public function getUserTasksForAI(Request $request, $userId): JsonResponse
    {
        try {
            // Validate user exists
            $user = User::findOrFail($userId);

            // Get manual tasks from events table
            $manualTasks = Event::where('user_id', $userId)
                ->when($request->has('status'), function($q) use ($request) {
                    $q->where('status', $request->status);
                })
                ->when($request->has('date_from'), function($q) use ($request) {
                    $q->where('start_datetime', '>=', $request->date_from);
                })
                ->when($request->has('date_to'), function($q) use ($request) {
                    $q->where('end_datetime', '<=', $request->date_to);
                })
                ->when($request->boolean('upcoming_only'), function($q) {
                    $q->where('start_datetime', '>', now());
                })
                ->with(['category'])
                ->get()
                ->map(function($event) {
                    return [
                        'source' => 'manual',
                        'id' => $event->id,
                        'title' => $event->title,
                        'description' => $event->description,
                        'start_datetime' => $event->start_datetime,
                        'end_datetime' => $event->end_datetime,
                        'location' => $event->location,
                        'status' => $event->status,
                        'priority' => $event->priority,
                        'completion_percentage' => $event->completion_percentage,
                        'category' => $event->category?->name,
                        'metadata' => $event->event_metadata,
                        'participants' => $event->participants,
                        'requirements' => $event->requirements,
                        'is_manually_created' => $event->event_metadata['created_manually'] ?? false
                    ];
                });

            // Get imported tasks from raw_schedule_entries table
            $importedTasks = RawScheduleEntry::where('user_id', $userId)
                ->where('conversion_status', 'success') // Only successfully converted entries
                ->when($request->has('date_from'), function($q) use ($request) {
                    $q->where('parsed_start_datetime', '>=', $request->date_from);
                })
                ->when($request->has('date_to'), function($q) use ($request) {
                    $q->where('parsed_end_datetime', '<=', $request->date_to);
                })
                ->when($request->boolean('upcoming_only'), function($q) {
                    $q->where('parsed_start_datetime', '>', now());
                })
                ->with(['import'])
                ->get()
                ->map(function($entry) {
                    return [
                        'source' => 'imported',
                        'id' => $entry->id,
                        'import_id' => $entry->import_id,
                        'title' => $entry->parsed_title,
                        'description' => $entry->parsed_description,
                        'start_datetime' => $entry->parsed_start_datetime,
                        'end_datetime' => $entry->parsed_end_datetime,
                        'location' => $entry->parsed_location,
                        'priority' => $entry->parsed_priority,
                        'ai_confidence' => $entry->ai_confidence,
                        'ai_detected_category' => $entry->ai_detected_category,
                        'ai_detected_importance' => $entry->ai_detected_importance,
                        'original_text' => $entry->raw_text,
                        'detected_keywords' => $entry->detected_keywords,
                        'source_type' => $entry->import?->source_type,
                        'import_type' => $entry->import?->import_type,
                        'converted_event_id' => $entry->converted_event_id,
                        'requires_review' => $entry->manual_review_required
                    ];
                });

            // Combine both sources
            $allTasks = $manualTasks->concat($importedTasks);

            // Sort by start_datetime
            $sortedTasks = $allTasks->sortBy('start_datetime')->values();

            // Prepare summary for AI
            $summary = [
                'total_tasks' => $allTasks->count(),
                'manual_tasks' => $manualTasks->count(),
                'imported_tasks' => $importedTasks->count(),
                'task_distribution' => [
                    'by_priority' => $allTasks->groupBy('priority')->map->count(),
                    'by_status' => $manualTasks->groupBy('status')->map->count(),
                    'by_source' => $allTasks->groupBy('source')->map->count()
                ],
                'time_range' => [
                    'earliest' => $sortedTasks->first()['start_datetime'] ?? null,
                    'latest' => $sortedTasks->last()['start_datetime'] ?? null
                ]
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'User tasks retrieved for AI analysis',
                'data' => [
                    'user_id' => $userId,
                    'user_name' => $user->name,
                    'tasks' => $sortedTasks,
                    'summary' => $summary,
                    'ai_ready' => true,
                    'filters_applied' => array_keys($request->query())
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get user tasks for AI', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve user tasks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Analyze user schedule with AI
     * POST /api/v1/ai-schedule/analyze/{userId}
     */
    public function analyzeUserSchedule(Request $request, $userId): JsonResponse
    {
        try {
            $apiKey = env('OPENAI_API_KEY');
            $model = env('OPENAI_MODEL', 'gpt-4o-mini');

            if (!$apiKey) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'AI service not configured'
                ], 500);
            }

            // Get user tasks
            $tasksResponse = $this->getUserTasksForAI($request, $userId);
            $tasksData = $tasksResponse->getData(true);

            if ($tasksData['status'] !== 'success') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to retrieve user tasks for analysis'
                ], 500);
            }

            $tasks = $tasksData['data']['tasks'];
            $summary = $tasksData['data']['summary'];

            // Prepare AI prompt
            $analysisType = $request->input('analysis_type', 'general');
            $focusAreas = $request->input('focus_areas', [
                'time_optimization',
                'conflict_detection',
                'priority_alignment',
                'workload_balance'
            ]);

            $prompt = $this->buildAnalysisPrompt($tasks, $summary, $analysisType, $focusAreas);

            // Call OpenAI API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->withOptions([
                'verify' => false,
            ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert schedule optimization AI. Provide practical, actionable advice for improving productivity and time management. Always respond in valid JSON format.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 1500,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $aiResponse = $response->json();
                $analysis = $aiResponse['choices'][0]['message']['content'];

                // Try to parse as JSON for structured response
                $structuredAnalysis = $this->parseAIResponse($analysis);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Schedule analysis completed',
                    'data' => [
                        'user_id' => $userId,
                        'analysis_type' => $analysisType,
                        'focus_areas' => $focusAreas,
                        'task_summary' => $summary,
                        'ai_analysis' => [
                            'raw_response' => $analysis,
                            'structured_response' => $structuredAnalysis,
                            'model_used' => $model,
                            'usage' => $aiResponse['usage'] ?? null
                        ],
                        'analyzed_at' => now()->toISOString()
                    ]
                ]);

            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'AI analysis failed',
                    'error' => $response->json()
                ], $response->status());
            }

        } catch (\Exception $e) {
            Log::error('AI schedule analysis failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to analyze schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build AI analysis prompt
     */
    private function buildAnalysisPrompt($tasks, $summary, $analysisType, $focusAreas): string
    {
        $tasksJson = json_encode($tasks, JSON_PRETTY_PRINT);
        $summaryJson = json_encode($summary, JSON_PRETTY_PRINT);

        $prompt = "SCHEDULE ANALYSIS REQUEST\n\n";
        $prompt .= "Analysis Type: {$analysisType}\n";
        $prompt .= "Focus Areas: " . implode(', ', $focusAreas) . "\n\n";
        $prompt .= "TASK DATA:\n{$tasksJson}\n\n";
        $prompt .= "SUMMARY STATISTICS:\n{$summaryJson}\n\n";
        
        $prompt .= "Please analyze this schedule and provide:\n";
        $prompt .= "1. Overall assessment of the schedule quality\n";
        $prompt .= "2. Identified conflicts or issues\n";
        $prompt .= "3. Time optimization suggestions\n";
        $prompt .= "4. Priority and workload recommendations\n";
        $prompt .= "5. Specific actionable improvements\n\n";
        
        $prompt .= "Consider both manually created tasks and imported tasks in your analysis.\n";
        $prompt .= "Focus on practical, implementable suggestions.\n";
        $prompt .= "If possible, provide your response in JSON format with structured recommendations.";

        return $prompt;
    }

    /**
     * Analyze selected user tasks with AI
     * POST /api/v1/ai-schedule/analyze-selected/{userId}
     */
    public function analyzeSelectedTasks(Request $request, $userId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'selected_tasks' => 'required|array|min:1',
                'selected_tasks.*' => 'required|string', // task_id format: "manual_1" or "imported_2"
                'analysis_type' => 'nullable|string|in:general,optimization,conflict_detection,workload_balance',
                'focus_areas' => 'nullable|array',
                'additional_context' => 'nullable|string'
            ]);

            $apiKey = env('OPENAI_API_KEY');
            $model = env('OPENAI_MODEL', 'gpt-4o-mini');

            if (!$apiKey) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'AI service not configured'
                ], 500);
            }

            // Parse selected task IDs and fetch the actual tasks
            $selectedTasks = collect();
            $manualTaskIds = [];
            $importedTaskIds = [];

            foreach ($validated['selected_tasks'] as $taskId) {
                if (strpos($taskId, 'manual_') === 0) {
                    $manualTaskIds[] = (int) str_replace('manual_', '', $taskId);
                } elseif (strpos($taskId, 'imported_') === 0) {
                    $importedTaskIds[] = (int) str_replace('imported_', '', $taskId);
                }
            }

            // Fetch selected manual tasks
            if (!empty($manualTaskIds)) {
                $manualTasks = Event::where('user_id', $userId)
                    ->whereIn('id', $manualTaskIds)
                    ->with(['category'])
                    ->get()
                    ->map(function($event) {
                        return [
                            'task_id' => "manual_{$event->id}",
                            'source' => 'manual',
                            'title' => $event->title,
                            'description' => $event->description,
                            'start_datetime' => $event->start_datetime,
                            'end_datetime' => $event->end_datetime,
                            'location' => $event->location,
                            'status' => $event->status,
                            'priority' => $event->priority,
                            'completion_percentage' => $event->completion_percentage,
                            'category' => $event->category?->name,
                            'duration_minutes' => $event->start_datetime && $event->end_datetime 
                                ? $event->start_datetime->diffInMinutes($event->end_datetime) 
                                : null,
                        ];
                    });

                $selectedTasks = $selectedTasks->concat($manualTasks);
            }

            // Fetch selected imported tasks
            if (!empty($importedTaskIds)) {
                $importedTasks = RawScheduleEntry::where('user_id', $userId)
                    ->whereIn('id', $importedTaskIds)
                    ->where('conversion_status', 'success')
                    ->with(['import'])
                    ->get()
                    ->map(function($entry) {
                        return [
                            'task_id' => "imported_{$entry->id}",
                            'source' => 'imported',
                            'title' => $entry->parsed_title,
                            'description' => $entry->parsed_description,
                            'start_datetime' => $entry->parsed_start_datetime,
                            'end_datetime' => $entry->parsed_end_datetime,
                            'location' => $entry->parsed_location,
                            'priority' => $entry->parsed_priority,
                            'ai_confidence' => $entry->ai_confidence,
                            'ai_detected_category' => $entry->ai_detected_category,
                            'duration_minutes' => $entry->parsed_duration_minutes,
                            'source_type' => $entry->import?->source_type,
                        ];
                    });

                $selectedTasks = $selectedTasks->concat($importedTasks);
            }

            if ($selectedTasks->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No valid tasks found for the selected task IDs'
                ], 400);
            }

            // Sort by start_datetime
            $sortedTasks = $selectedTasks->sortBy('start_datetime')->values();

            // Prepare summary
            $summary = [
                'selected_tasks_count' => $sortedTasks->count(),
                'manual_tasks_count' => $sortedTasks->where('source', 'manual')->count(),
                'imported_tasks_count' => $sortedTasks->where('source', 'imported')->count(),
                'priority_distribution' => $sortedTasks->groupBy('priority')->map->count(),
                'total_duration_minutes' => $sortedTasks->sum('duration_minutes'),
                'date_range' => [
                    'start' => $sortedTasks->first()['start_datetime'] ?? null,
                    'end' => $sortedTasks->last()['start_datetime'] ?? null,
                ]
            ];

            // Build AI prompt for selected tasks
            $analysisType = $validated['analysis_type'] ?? 'general';
            $focusAreas = $validated['focus_areas'] ?? ['time_optimization', 'conflict_detection', 'priority_alignment'];
            $additionalContext = $validated['additional_context'] ?? '';

            $prompt = $this->buildSelectedTasksPrompt($sortedTasks, $summary, $analysisType, $focusAreas, $additionalContext);

            // Start timing for processing
            $processingStartTime = microtime(true);

            // Call OpenAI API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->withOptions([
                'verify' => false,
            ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert schedule optimization AI. Analyze the selected tasks and provide practical, actionable advice. Always respond in valid JSON format with structured recommendations.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 1500,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $aiResponse = $response->json();
                $analysis = $aiResponse['choices'][0]['message']['content'];

                // Try to parse as JSON for structured response
                $structuredAnalysis = $this->parseAIResponse($analysis);

                // Calculate processing time and API cost estimates
                $processingTime = round((microtime(true) - $processingStartTime) * 1000); // milliseconds as integer
                $tokenUsage = $aiResponse['usage']['total_tokens'] ?? null;
                $apiCost = $this->estimateApiCost($tokenUsage, $model);

                // Save analysis to database
                $analysisRecord = AiScheduleAnalysis::create([
                    'user_id' => $userId,
                    'input_data' => [
                        'selected_tasks' => $sortedTasks->toArray(),
                        'task_summary' => $summary,
                        'selection_metadata' => [
                            'total_selected' => count($validated['selected_tasks']),
                            'manual_selected' => count($manualTaskIds),
                            'imported_selected' => count($importedTaskIds),
                        ]
                    ],
                    'analysis_type' => $analysisType,
                    'target_date' => $sortedTasks->first()['start_datetime'] ? 
                        date('Y-m-d', strtotime($sortedTasks->first()['start_datetime'])) : 
                        now()->toDateString(),
                    'end_date' => $sortedTasks->last()['start_datetime'] ? 
                        date('Y-m-d', strtotime($sortedTasks->last()['start_datetime'])) : null,
                    'status' => 'completed',
                    'ai_model' => $model,
                    'ai_request_payload' => [
                        'model' => $model,
                        'prompt' => $prompt,
                        'max_tokens' => 1500,
                        'temperature' => 0.7,
                        'focus_areas' => $focusAreas,
                        'additional_context' => $additionalContext
                    ],
                    'ai_response' => $aiResponse,
                    'optimized_schedule' => $structuredAnalysis,
                    'optimization_metrics' => [
                        'tasks_analyzed' => $sortedTasks->count(),
                        'total_duration_minutes' => $summary['total_duration_minutes'],
                        'confidence_level' => 'high'
                    ],
                    'ai_reasoning' => $analysis,
                    'confidence_score' => 0.85, // High confidence for selected tasks
                    'user_preferences' => [
                        'analysis_type' => $analysisType,
                        'focus_areas' => $focusAreas
                    ],
                    'processing_time_ms' => $processingTime,
                    'token_usage' => $tokenUsage,
                    'api_cost' => $apiCost,
                    'retry_count' => 0
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Selected tasks analysis completed and saved',
                    'data' => [
                        'analysis_id' => $analysisRecord->id,
                        'user_id' => $userId,
                        'analysis_type' => $analysisType,
                        'focus_areas' => $focusAreas,
                        'selected_tasks' => $sortedTasks,
                        'task_summary' => $summary,
                        'ai_analysis' => [
                            'raw_response' => $analysis,
                            'structured_response' => $structuredAnalysis,
                            'model_used' => $model,
                            'usage' => $aiResponse['usage'] ?? null,
                            'confidence' => 'high',
                            'processing_time_ms' => $processingTime,
                            'api_cost' => $apiCost
                        ],
                        'recommendations' => $structuredAnalysis['recommendations'] ?? null,
                        'analyzed_at' => $analysisRecord->created_at->toISOString(),
                        'selection_metadata' => [
                            'total_selected' => count($validated['selected_tasks']),
                            'manual_selected' => count($manualTaskIds),
                            'imported_selected' => count($importedTaskIds),
                        ],
                        'saved_to_database' => true
                    ]
                ]);

            } else {
                $processingTime = round((microtime(true) - $processingStartTime) * 1000);
                
                // Save failed analysis to database
                AiScheduleAnalysis::create([
                    'user_id' => $userId,
                    'input_data' => [
                        'selected_tasks' => $sortedTasks->toArray(),
                        'task_summary' => $summary,
                        'selection_metadata' => [
                            'total_selected' => count($validated['selected_tasks']),
                            'manual_selected' => count($manualTaskIds),
                            'imported_selected' => count($importedTaskIds),
                        ]
                    ],
                    'analysis_type' => $analysisType,
                    'target_date' => $sortedTasks->first()['start_datetime'] ? 
                        date('Y-m-d', strtotime($sortedTasks->first()['start_datetime'])) : 
                        now()->toDateString(),
                    'status' => 'failed',
                    'ai_model' => $model,
                    'ai_request_payload' => [
                        'model' => $model,
                        'prompt' => $prompt,
                        'focus_areas' => $focusAreas,
                    ],
                    'error_details' => [
                        'http_status' => $response->status(),
                        'error_response' => $response->json(),
                        'error_message' => 'OpenAI API request failed'
                    ],
                    'processing_time_ms' => $processingTime,
                    'retry_count' => 0
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'AI analysis failed',
                    'error' => $response->json()
                ], $response->status());
            }

        } catch (\Exception $e) {
            // Try to save failed analysis with exception details
            try {
                if (isset($sortedTasks) && isset($summary) && isset($analysisType)) {
                    AiScheduleAnalysis::create([
                        'user_id' => $userId,
                        'input_data' => [
                            'selected_tasks' => $sortedTasks->toArray(),
                            'task_summary' => $summary ?? [],
                            'selection_metadata' => [
                                'total_selected' => count($validated['selected_tasks'] ?? []),
                                'manual_selected' => count($manualTaskIds ?? []),
                                'imported_selected' => count($importedTaskIds ?? []),
                            ]
                        ],
                        'analysis_type' => $analysisType ?? 'general',
                        'target_date' => now()->toDateString(),
                        'status' => 'failed',
                        'ai_model' => $model ?? 'unknown',
                        'error_details' => [
                            'exception_type' => get_class($e),
                            'error_message' => $e->getMessage(),
                            'error_file' => $e->getFile(),
                            'error_line' => $e->getLine(),
                        ],
                        'processing_time_ms' => isset($processingStartTime) ? 
                            round((microtime(true) - $processingStartTime) * 1000) : null,
                        'retry_count' => 0
                    ]);
                }
            } catch (\Exception $dbException) {
                Log::error('Failed to save failed analysis to database', [
                    'original_error' => $e->getMessage(),
                    'database_error' => $dbException->getMessage()
                ]);
            }

            Log::error('AI selected tasks analysis failed', [
                'user_id' => $userId,
                'selected_tasks' => $validated['selected_tasks'] ?? [],
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to analyze selected tasks',
                'error' => $e->getMessage(),
                'debug' => [
                    'user_id' => $userId,
                    'error_type' => get_class($e),
                    'error_line' => $e->getLine(),
                    'openai_configured' => !empty(env('OPENAI_API_KEY')),
                    'selected_tasks_count' => count($validated['selected_tasks'] ?? [])
                ]
            ], 500);
        }
    }

    /**
     * Build AI analysis prompt for selected tasks
     */
    private function buildSelectedTasksPrompt($tasks, $summary, $analysisType, $focusAreas, $additionalContext): string
    {
        $tasksJson = json_encode($tasks, JSON_PRETTY_PRINT);
        $summaryJson = json_encode($summary, JSON_PRETTY_PRINT);

        $prompt = "SELECTED TASKS SCHEDULE ANALYSIS\n\n";
        $prompt .= "USER SELECTED {$summary['selected_tasks_count']} TASKS FOR ANALYSIS\n\n";
        $prompt .= "Analysis Type: {$analysisType}\n";
        $prompt .= "Focus Areas: " . implode(', ', $focusAreas) . "\n\n";
        
        if ($additionalContext) {
            $prompt .= "Additional Context from User: {$additionalContext}\n\n";
        }
        
        $prompt .= "SELECTED TASKS DATA:\n{$tasksJson}\n\n";
        $prompt .= "SUMMARY STATISTICS:\n{$summaryJson}\n\n";
        
        $prompt .= "Please analyze these SPECIFIC SELECTED tasks and provide:\n";
        $prompt .= "1. Assessment of the selected tasks combination\n";
        $prompt .= "2. Identified conflicts or scheduling issues within selected tasks\n";
        $prompt .= "3. Optimization suggestions for these specific tasks\n";
        $prompt .= "4. Priority recommendations and time allocation\n";
        $prompt .= "5. Specific actionable improvements for this task set\n";
        $prompt .= "6. Recommended schedule order/sequence\n\n";
        
        $prompt .= "Focus on practical suggestions that the user can implement immediately.\n";
        $prompt .= "Consider both manually created and imported tasks in your analysis.\n";
        $prompt .= "Provide response in JSON format with structured recommendations.";

        return $prompt;
    }

    /**
     * Parse JSON from AI response, handling markdown code blocks
     */
    private function parseAIResponse($analysis): ?array
    {
        // First try direct JSON decode
        if ($decodedAnalysis = json_decode($analysis, true)) {
            return $decodedAnalysis;
        }
        
        // Try to extract JSON from markdown code blocks
        if (preg_match('/```json\s*\n(.*?)\n```/s', $analysis, $matches)) {
            $jsonString = trim($matches[1]);
            if ($decodedAnalysis = json_decode($jsonString, true)) {
                return $decodedAnalysis;
            }
        } else if (preg_match('/```\s*\n(.*?)\n```/s', $analysis, $matches)) {
            // Try without 'json' keyword
            $jsonString = trim($matches[1]);
            if ($decodedAnalysis = json_decode($jsonString, true)) {
                return $decodedAnalysis;
            }
        }
        
        return null;
    }

    /**
     * Estimate API cost based on token usage and model
     */
    private function estimateApiCost($tokenUsage, $model): ?float
    {
        if (!$tokenUsage) {
            return null;
        }

        // OpenAI pricing per 1M tokens (as of 2024)
        $pricingPer1M = [
            'gpt-4o-mini' => [
                'input' => 0.15,  // $0.15 per 1M input tokens
                'output' => 0.60  // $0.60 per 1M output tokens
            ],
            'gpt-4' => [
                'input' => 30.00,
                'output' => 60.00
            ],
            'gpt-4-turbo' => [
                'input' => 10.00,
                'output' => 30.00
            ]
        ];

        $pricing = $pricingPer1M[$model] ?? $pricingPer1M['gpt-4o-mini'];
        
        $inputTokens = $tokenUsage['prompt_tokens'] ?? 0;
        $outputTokens = $tokenUsage['completion_tokens'] ?? 0;
        
        $inputCost = ($inputTokens / 1000000) * $pricing['input'];
        $outputCost = ($outputTokens / 1000000) * $pricing['output'];
        
        return round($inputCost + $outputCost, 4);
    }
}