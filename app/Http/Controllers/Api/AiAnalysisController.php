<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiScheduleAnalysis;
use App\Models\User;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AiAnalysisController extends Controller
{
    /**
     * Get specific analysis by ID
     * GET /api/v1/ai-analyses/{analysisId}
     */
    public function show(Request $request, $analysisId): JsonResponse
    {
        try {
            $analysis = AiScheduleAnalysis::with(['user'])
                ->findOrFail($analysisId);

            return response()->json([
                'status' => 'success',
                'message' => 'Analysis retrieved successfully',
                'data' => [
                    'id' => $analysis->id,
                    'user_id' => $analysis->user_id,
                    'user_name' => $analysis->user->name,
                    'analysis_type' => $analysis->analysis_type,
                    'target_date' => $analysis->target_date,
                    'end_date' => $analysis->end_date,
                    'status' => $analysis->status,
                    'ai_model' => $analysis->ai_model,
                    'input_data' => $analysis->input_data,
                    'optimized_schedule' => $analysis->optimized_schedule,
                    'optimization_metrics' => $analysis->optimization_metrics,
                    'ai_reasoning' => $analysis->ai_reasoning,
                    'confidence_score' => $analysis->confidence_score,
                    'user_preferences' => $analysis->user_preferences,
                    'processing_time_ms' => $analysis->processing_time_ms,
                    'token_usage' => $analysis->token_usage,
                    'api_cost' => $analysis->api_cost,
                    'user_approved' => $analysis->user_approved,
                    'user_rating' => $analysis->user_rating,
                    'user_feedback' => $analysis->user_feedback,
                    'user_modifications' => $analysis->user_modifications,
                    'created_at' => $analysis->created_at->toISOString(),
                    'updated_at' => $analysis->updated_at->toISOString(),
                    'ai_response' => $analysis->ai_response, // Full OpenAI response
                    'ai_request_payload' => $analysis->ai_request_payload // Original request
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve analysis', [
                'analysis_id' => $analysisId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Analysis not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get all analyses for a user with filtering
     * GET /api/v1/ai-analyses/user/{userId}
     */
    public function getUserAnalyses(Request $request, $userId): JsonResponse
    {
        try {
            // Validate user exists
            $user = User::findOrFail($userId);

            // Build query with filters
            $query = AiScheduleAnalysis::where('user_id', $userId)
                ->with(['user']);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('analysis_type')) {
                $query->where('analysis_type', $request->analysis_type);
            }

            if ($request->has('date_from')) {
                $query->where('target_date', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('target_date', '<=', $request->date_to);
            }

            if ($request->has('rating')) {
                $query->where('user_rating', $request->rating);
            }

            if ($request->boolean('approved_only')) {
                $query->where('user_approved', true);
            }

            // Apply sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginate results
            $limit = min($request->input('limit', 10), 50); // Max 50 per page
            $analyses = $query->paginate($limit);

            // Transform data for response
            $transformedAnalyses = $analyses->map(function($analysis) {
                return [
                    'id' => $analysis->id,
                    'analysis_type' => $analysis->analysis_type,
                    'target_date' => $analysis->target_date,
                    'end_date' => $analysis->end_date,
                    'status' => $analysis->status,
                    'confidence_score' => $analysis->confidence_score,
                    'tasks_analyzed' => $analysis->optimization_metrics['tasks_analyzed'] ?? 0,
                    'total_duration_minutes' => $analysis->optimization_metrics['total_duration_minutes'] ?? 0,
                    'user_rating' => $analysis->user_rating,
                    'user_approved' => $analysis->user_approved,
                    'processing_time_ms' => $analysis->processing_time_ms,
                    'api_cost' => $analysis->api_cost,
                    'created_at' => $analysis->created_at->toISOString(),
                    'updated_at' => $analysis->updated_at->toISOString()
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'User analyses retrieved successfully',
                'data' => [
                    'user_id' => $userId,
                    'user_name' => $user->name,
                    'analyses' => $transformedAnalyses,
                    'pagination' => [
                        'current_page' => $analyses->currentPage(),
                        'per_page' => $analyses->perPage(),
                        'total' => $analyses->total(),
                        'last_page' => $analyses->lastPage(),
                        'from' => $analyses->firstItem(),
                        'to' => $analyses->lastItem()
                    ],
                    'filters_applied' => array_filter([
                        'status' => $request->status,
                        'analysis_type' => $request->analysis_type,
                        'date_from' => $request->date_from,
                        'date_to' => $request->date_to,
                        'rating' => $request->rating,
                        'approved_only' => $request->boolean('approved_only')
                    ])
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve user analyses', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve analyses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get analysis summary/statistics for a user
     * GET /api/v1/ai-analyses/user/{userId}/summary
     */
    public function getUserAnalysisSummary(Request $request, $userId): JsonResponse
    {
        try {
            // Validate user exists
            $user = User::findOrFail($userId);

            // Get counts by status
            $statusCounts = AiScheduleAnalysis::where('user_id', $userId)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            // Get counts by analysis type
            $typeCounts = AiScheduleAnalysis::where('user_id', $userId)
                ->selectRaw('analysis_type, COUNT(*) as count')
                ->groupBy('analysis_type')
                ->pluck('count', 'analysis_type');

            // Get rating statistics
            $ratingStats = AiScheduleAnalysis::where('user_id', $userId)
                ->whereNotNull('user_rating')
                ->selectRaw('
                    AVG(user_rating) as average_rating,
                    MIN(user_rating) as min_rating,
                    MAX(user_rating) as max_rating,
                    COUNT(*) as total_rated
                ')
                ->first();

            // Get cost and performance stats
            $performanceStats = AiScheduleAnalysis::where('user_id', $userId)
                ->where('status', 'completed')
                ->selectRaw('
                    SUM(api_cost) as total_cost,
                    AVG(api_cost) as average_cost,
                    AVG(processing_time_ms) as average_processing_time,
                    SUM(token_usage) as total_tokens,
                    COUNT(*) as total_completed
                ')
                ->first();

            // Get recent analysis
            $latestAnalysis = AiScheduleAnalysis::where('user_id', $userId)
                ->latest()
                ->first();

            return response()->json([
                'status' => 'success',
                'message' => 'Analysis summary retrieved successfully',
                'data' => [
                    'user_id' => $userId,
                    'user_name' => $user->name,
                    'total_analyses' => AiScheduleAnalysis::where('user_id', $userId)->count(),
                    'status_breakdown' => $statusCounts,
                    'analysis_type_breakdown' => $typeCounts,
                    'rating_statistics' => [
                        'average_rating' => $ratingStats?->average_rating ? round($ratingStats->average_rating, 2) : null,
                        'min_rating' => $ratingStats?->min_rating,
                        'max_rating' => $ratingStats?->max_rating,
                        'total_rated' => $ratingStats?->total_rated ?? 0
                    ],
                    'performance_statistics' => [
                        'total_cost' => $performanceStats?->total_cost ? round($performanceStats->total_cost, 4) : 0,
                        'average_cost' => $performanceStats?->average_cost ? round($performanceStats->average_cost, 4) : null,
                        'average_processing_time_ms' => $performanceStats?->average_processing_time ? round($performanceStats->average_processing_time) : null,
                        'total_tokens' => $performanceStats?->total_tokens ?? 0,
                        'total_completed' => $performanceStats?->total_completed ?? 0
                    ],
                    'latest_analysis' => $latestAnalysis ? [
                        'id' => $latestAnalysis->id,
                        'analysis_type' => $latestAnalysis->analysis_type,
                        'status' => $latestAnalysis->status,
                        'created_at' => $latestAnalysis->created_at->toISOString()
                    ] : null,
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve user analysis summary', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve analysis summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update analysis feedback (user rating and comments)
     * PATCH /api/v1/ai-analyses/{analysisId}/feedback
     */
    public function updateFeedback(Request $request, $analysisId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_approved' => 'nullable|boolean',
                'user_rating' => 'nullable|integer|min:1|max:5',
                'user_feedback' => 'nullable|string|max:1000',
                'user_modifications' => 'nullable|array'
            ]);

            $analysis = AiScheduleAnalysis::findOrFail($analysisId);

            $analysis->update(array_filter($validated, function($value) {
                return $value !== null;
            }));

            return response()->json([
                'status' => 'success',
                'message' => 'Feedback updated successfully',
                'data' => [
                    'analysis_id' => $analysis->id,
                    'user_approved' => $analysis->user_approved,
                    'user_rating' => $analysis->user_rating,
                    'user_feedback' => $analysis->user_feedback,
                    'user_modifications' => $analysis->user_modifications,
                    'updated_at' => $analysis->updated_at->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update analysis feedback', [
                'analysis_id' => $analysisId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get task priority recommendations from AI analysis
     * GET /api/v1/ai-analyses/{analysisId}/priority-recommendations
     */
    public function getTaskPriorityRecommendations(Request $request, $analysisId): JsonResponse
    {
        try {
            $analysis = AiScheduleAnalysis::find($analysisId);
            
            if (!$analysis) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Analysis not found',
                    'error' => "No query results for model [App\\Models\\AiScheduleAnalysis] {$analysisId}"
                ], 404);
            }

            if (!$analysis->input_data || !isset($analysis->input_data['selected_tasks'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No task data found in analysis'
                ], 400);
            }

            $selectedTasks = collect($analysis->input_data['selected_tasks']);

            // Extract and sort tasks by priority (higher number = higher priority)
            $prioritizedTasks = $selectedTasks->map(function($task) {
                return [
                    'task_id' => $task['task_id'],
                    'title' => $task['title'],
                    'description' => $task['description'],
                    'priority' => $task['priority'],
                    'start_datetime' => $task['start_datetime'],
                    'end_datetime' => $task['end_datetime'],
                    'location' => $task['location'],
                    'duration_minutes' => $task['duration_minutes'],
                    'status' => $task['status']
                ];
            })->sortByDesc('priority')->values();

            // Find highest priority task
            $highestPriorityTask = $prioritizedTasks->first();
            
            // Create priority ranking
            $priorityRanking = $prioritizedTasks->map(function($task, $index) {
                return [
                    'rank' => $index + 1,
                    'task_id' => $task['task_id'],
                    'title' => $task['title'],
                    'priority' => $task['priority'],
                    'start_datetime' => $task['start_datetime'],
                    'location' => $task['location'],
                    'urgency_level' => $this->getUrgencyLevel($task['priority']),
                    'priority_description' => $this->getPriorityDescription($task['priority'])
                ];
            });

            // Generate notification messages
            $notifications = $this->generateTaskNotifications($prioritizedTasks, $analysis);

            // Extract AI recommendations if available
            $aiRecommendations = null;
            if ($analysis->optimized_schedule && isset($analysis->optimized_schedule['analysis']['recommended_schedule']['sequence'])) {
                $aiRecommendations = $analysis->optimized_schedule['analysis']['recommended_schedule']['sequence'];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Task priority recommendations retrieved successfully',
                'data' => [
                    'analysis_id' => $analysis->id,
                    'analysis_date' => $analysis->created_at->toISOString(),
                    'highest_priority_task' => [
                        'task_id' => $highestPriorityTask['task_id'],
                        'title' => $highestPriorityTask['title'],
                        'priority' => $highestPriorityTask['priority'],
                        'urgency_level' => $this->getUrgencyLevel($highestPriorityTask['priority']),
                        'start_datetime' => $highestPriorityTask['start_datetime'],
                        'location' => $highestPriorityTask['location'],
                        'notification_message' => "🔥 Highest Priority: {$highestPriorityTask['title']} (Priority {$highestPriorityTask['priority']})"
                    ],
                    'priority_ranking' => $priorityRanking,
                    'task_summary' => [
                        'total_tasks' => $prioritizedTasks->count(),
                        'priority_distribution' => $prioritizedTasks->groupBy('priority')->map->count(),
                        'urgency_breakdown' => [
                            'critical' => $prioritizedTasks->where('priority', '>=', 5)->count(),
                            'high' => $prioritizedTasks->where('priority', 4)->count(),
                            'medium' => $prioritizedTasks->where('priority', 3)->count(),
                            'low' => $prioritizedTasks->where('priority', '<=', 2)->count()
                        ],
                        'date_range' => [
                            'earliest' => $prioritizedTasks->min('start_datetime'),
                            'latest' => $prioritizedTasks->max('start_datetime')
                        ]
                    ],
                    'notifications_for_frontend' => $notifications,
                    'ai_recommendations' => $aiRecommendations,
                    'ai_confidence_score' => $analysis->confidence_score,
                    'priority_guidelines' => [
                        'priority_5' => 'Critical - Handle immediately',
                        'priority_4' => 'High - Complete today if possible', 
                        'priority_3' => 'Medium - Complete this week',
                        'priority_2' => 'Low - Can be rescheduled',
                        'priority_1' => 'Lowest - Optional/flexible timing'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve task priority recommendations', [
                'analysis_id' => $analysisId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve priority recommendations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all task priority recommendations for a user (latest analysis)
     * GET /api/v1/ai-analyses/user/{userId}/latest-priorities
     */
    public function getLatestUserPriorities(Request $request, $userId): JsonResponse
    {
        try {
            // Get user's latest completed analysis
            $latestAnalysis = AiScheduleAnalysis::where('user_id', $userId)
                ->where('status', 'completed')
                ->latest()
                ->first();

            if (!$latestAnalysis) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No completed analysis found for user'
                ], 404);
            }

            // Call the priority recommendations method
            return $this->getTaskPriorityRecommendations($request, $latestAnalysis->id);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve latest user priorities', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve latest priorities',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Get urgency level based on priority
     */
    private function getUrgencyLevel(int $priority): string
    {
        return match(true) {
            $priority >= 5 => 'critical',
            $priority == 4 => 'high',
            $priority == 3 => 'medium',
            $priority <= 2 => 'low',
            default => 'unknown'
        };
    }

    /**
     * Helper: Get priority description
     */
    private function getPriorityDescription(int $priority): string
    {
        return match($priority) {
            5 => 'Critical - Handle immediately',
            4 => 'High - Complete today if possible',
            3 => 'Medium - Complete this week',
            2 => 'Low - Can be rescheduled',
            1 => 'Lowest - Optional/flexible timing',
            default => 'Unknown priority level'
        };
    }

    /**
     * Helper: Generate notification messages for tasks
     */
    private function generateTaskNotifications(Collection $prioritizedTasks, AiScheduleAnalysis $analysis): array
    {
        $notifications = [];
        
        foreach ($prioritizedTasks as $index => $task) {
            $rank = $index + 1;
            $urgency = $this->getUrgencyLevel($task['priority']);
            
            $notifications[] = [
                'type' => 'task_priority',
                'priority' => $urgency,
                'title' => "Task Priority #{$rank}: {$task['title']}",
                'message' => "Priority {$task['priority']} - {$this->getPriorityDescription($task['priority'])}",
                'action_data' => [
                    'task_id' => $task['task_id'],
                    'analysis_id' => $analysis->id,
                    'priority_rank' => $rank,
                    'action_type' => 'view_task_details'
                ],
                'scheduled_datetime' => $task['start_datetime'],
                'location' => $task['location'],
                'context' => [
                    'duration_minutes' => $task['duration_minutes'],
                    'urgency_level' => $urgency,
                    'ai_confidence' => $analysis->confidence_score
                ]
            ];
        }

        return $notifications;
    }

    /**
     * Delete task based on AI recommendation
     * DELETE /api/v1/ai-analyses/tasks/{taskId}
     */
    public function deleteTask(Request $request, $taskId): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Find the task/event
            // Handle manual task IDs (e.g., "manual_14" -> ID 14)
            $actualId = $taskId;
            if (str_starts_with($taskId, 'manual_')) {
                $actualId = str_replace('manual_', '', $taskId);
            }
            
            $task = Event::find($actualId);

            if (!$task) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Task not found',
                    'error' => "Task with ID {$taskId} does not exist"
                ], 404);
            }

            // Store task info before deletion for response
            $taskInfo = [
                'task_id' => $taskId, // Keep original format (manual_14)
                'actual_id' => $actualId, // Show actual database ID
                'title' => $task->title,
                'description' => $task->description,
                'start_time' => $task->start_datetime,
                'end_time' => $task->end_datetime,
                'location' => $task->location,
                'priority' => $task->priority,
                'status' => $task->status
            ];

            // Delete the task
            $task->delete();

            // Log the deletion
            Log::info('Task deleted based on AI recommendation', [
                'task_id' => $taskId,
                'deleted_by' => $request->input('deleted_by', 'ai_recommendation'),
                'reason' => $request->input('reason', 'AI analysis suggested deletion'),
                'analysis_id' => $request->input('analysis_id')
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Task deleted successfully',
                'data' => [
                    'deleted_task' => $taskInfo,
                    'deleted_at' => now()->toISOString(),
                    'deletion_reason' => $request->input('reason', 'AI analysis suggested deletion')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to delete task', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete task',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete tasks based on AI recommendations
     * DELETE /api/v1/ai-analyses/tasks/bulk-delete
     */
    public function bulkDeleteTasks(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'task_ids' => 'required|array|min:1',
                'task_ids.*' => 'required|string',
                'analysis_id' => 'nullable|integer|exists:ai_schedule_analyses,id',
                'reason' => 'nullable|string|max:500',
                'confirm' => 'required|boolean'
            ]);

            if (!$validated['confirm']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Deletion not confirmed',
                    'error' => 'Please confirm the bulk deletion by setting confirm to true'
                ], 400);
            }

            DB::beginTransaction();

            $deletedTasks = [];
            $failedDeletions = [];

            foreach ($validated['task_ids'] as $taskId) {
                try {
                    // Find the task
                    // Handle manual task IDs (e.g., "manual_14" -> ID 14)
                    $actualId = $taskId;
                    if (str_starts_with($taskId, 'manual_')) {
                        $actualId = str_replace('manual_', '', $taskId);
                    }
                    
                    $task = Event::find($actualId);

                    if (!$task) {
                        $failedDeletions[] = [
                            'task_id' => $taskId,
                            'error' => 'Task not found'
                        ];
                        continue;
                    }

                    // Store task info
                    $taskInfo = [
                        'task_id' => $taskId, // Keep original format
                        'actual_id' => $actualId, // Show actual database ID
                        'title' => $task->title,
                        'description' => $task->description,
                        'start_time' => $task->start_datetime,
                        'location' => $task->location,
                        'priority' => $task->priority
                    ];

                    // Delete the task
                    $task->delete();
                    $deletedTasks[] = $taskInfo;

                } catch (\Exception $e) {
                    $failedDeletions[] = [
                        'task_id' => $taskId,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Log the bulk deletion
            Log::info('Bulk task deletion based on AI recommendation', [
                'deleted_count' => count($deletedTasks),
                'failed_count' => count($failedDeletions),
                'analysis_id' => $validated['analysis_id'] ?? null,
                'reason' => $validated['reason'] ?? 'AI analysis suggested deletion'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk deletion completed',
                'data' => [
                    'deleted_tasks' => $deletedTasks,
                    'failed_deletions' => $failedDeletions,
                    'summary' => [
                        'total_requested' => count($validated['task_ids']),
                        'successfully_deleted' => count($deletedTasks),
                        'failed' => count($failedDeletions)
                    ],
                    'deleted_at' => now()->toISOString(),
                    'deletion_reason' => $validated['reason'] ?? 'AI analysis suggested deletion'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to perform bulk task deletion', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to perform bulk deletion',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get deletable task recommendations based on AI analysis
     * GET /api/v1/ai-analyses/{analysisId}/deletable-tasks
     */
    public function getDeletableTaskRecommendations(Request $request, $analysisId): JsonResponse
    {
        try {
            $analysis = AiScheduleAnalysis::findOrFail($analysisId);

            if (!$analysis->input_data || !isset($analysis->input_data['selected_tasks'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No task data found in analysis'
                ], 400);
            }

            $selectedTasks = collect($analysis->input_data['selected_tasks']);

            // Identify deletable tasks based on priority and other criteria
            $deletableTasks = $selectedTasks->filter(function($task) {
                // Tasks with priority 1 or 2 and optional status
                return $task['priority'] <= 2 || 
                       $task['status'] === 'optional' ||
                       $task['status'] === 'cancelled';
            })->map(function($task) {
                return [
                    'task_id' => $task['task_id'],
                    'title' => $task['title'],
                    'description' => $task['description'],
                    'priority' => $task['priority'],
                    'status' => $task['status'],
                    'start_datetime' => $task['start_datetime'],
                    'location' => $task['location'],
                    'deletion_reason' => $this->getDeletionReason($task),
                    'deletion_confidence' => $this->getDeletionConfidence($task)
                ];
            })->values();

            // Sort by deletion confidence (highest first)
            $deletableTasks = $deletableTasks->sortByDesc('deletion_confidence')->values();

            return response()->json([
                'status' => 'success',
                'message' => 'Deletable task recommendations retrieved',
                'data' => [
                    'analysis_id' => $analysis->id,
                    'analysis_date' => $analysis->created_at->toISOString(),
                    'deletable_tasks' => $deletableTasks,
                    'summary' => [
                        'total_tasks_analyzed' => $selectedTasks->count(),
                        'deletable_count' => $deletableTasks->count(),
                        'deletion_criteria' => [
                            'low_priority' => 'Tasks with priority 1 or 2',
                            'optional_status' => 'Tasks marked as optional',
                            'cancelled_status' => 'Tasks marked as cancelled'
                        ]
                    ],
                    'recommendations' => [
                        'immediate_deletion' => $deletableTasks->where('deletion_confidence', '>=', 0.8)->pluck('task_id'),
                        'review_before_deletion' => $deletableTasks->where('deletion_confidence', '<', 0.8)->where('deletion_confidence', '>=', 0.5)->pluck('task_id'),
                        'keep_for_now' => $deletableTasks->where('deletion_confidence', '<', 0.5)->pluck('task_id')
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get deletable task recommendations', [
                'analysis_id' => $analysisId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get deletable recommendations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Get deletion reason for a task
     */
    private function getDeletionReason(array $task): string
    {
        if ($task['status'] === 'cancelled') {
            return 'Task has been cancelled';
        }
        if ($task['status'] === 'optional') {
            return 'Task is marked as optional';
        }
        if ($task['priority'] == 1) {
            return 'Lowest priority - Optional/flexible timing';
        }
        if ($task['priority'] == 2) {
            return 'Low priority - Can be rescheduled or removed';
        }
        return 'Task can be removed based on current priorities';
    }

    /**
     * Helper: Calculate deletion confidence score
     */
    private function getDeletionConfidence(array $task): float
    {
        $confidence = 0.0;

        // Status-based confidence
        if ($task['status'] === 'cancelled') {
            $confidence = 0.95;
        } elseif ($task['status'] === 'optional') {
            $confidence = 0.85;
        } elseif ($task['status'] === 'completed') {
            $confidence = 0.9;
        }

        // Priority-based confidence (if not already set by status)
        if ($confidence == 0.0) {
            if ($task['priority'] == 1) {
                $confidence = 0.75;
            } elseif ($task['priority'] == 2) {
                $confidence = 0.6;
            } else {
                $confidence = 0.3;
            }
        }

        return round($confidence, 2);
    }

    /**
     * Update AI analysis data after task deletion
     * PATCH /api/v1/ai-analyses/{analysisId}/remove-task/{taskId}
     */
    public function removeTaskFromAnalysis(Request $request, $analysisId, $taskId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $analysis = AiScheduleAnalysis::findOrFail($analysisId);

            if (!$analysis->input_data || !isset($analysis->input_data['selected_tasks'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No task data found in analysis'
                ], 400);
            }

            $selectedTasks = collect($analysis->input_data['selected_tasks']);
            
            // Remove the deleted task from selected_tasks
            $updatedTasks = $selectedTasks->filter(function($task) use ($taskId) {
                return $task['task_id'] !== $taskId;
            })->values()->toArray();

            // Update input_data
            $inputData = $analysis->input_data;
            $inputData['selected_tasks'] = $updatedTasks;

            // Update optimization_metrics if exists
            $optimizationMetrics = $analysis->optimization_metrics ?? [];
            if (isset($optimizationMetrics['tasks_analyzed'])) {
                $optimizationMetrics['tasks_analyzed'] = max(0, $optimizationMetrics['tasks_analyzed'] - 1);
            }

            // Update the analysis
            $analysis->update([
                'input_data' => $inputData,
                'optimization_metrics' => $optimizationMetrics
            ]);

            // Log the update
            Log::info('Task removed from AI analysis data', [
                'analysis_id' => $analysisId,
                'task_id' => $taskId,
                'remaining_tasks' => count($updatedTasks)
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Task removed from analysis data successfully',
                'data' => [
                    'analysis_id' => $analysis->id,
                    'removed_task_id' => $taskId,
                    'remaining_tasks_count' => count($updatedTasks),
                    'updated_at' => $analysis->updated_at->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to remove task from analysis', [
                'analysis_id' => $analysisId,
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove task from analysis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete task and update analysis data in one operation
     * DELETE /api/v1/ai-analyses/tasks/{taskId}/complete-removal
     */
    public function completeTaskRemoval(Request $request, $taskId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $analysisId = $request->input('analysis_id');
            $reason = $request->input('reason', 'AI recommended deletion');

            // First, delete the task
            $deleteResponse = $this->deleteTask($request, $taskId);
            $deleteData = json_decode($deleteResponse->content(), true);

            if ($deleteData['status'] !== 'success') {
                return $deleteResponse;
            }

            // If analysis_id provided, update the analysis data
            if ($analysisId) {
                $updateRequest = new Request(['analysis_id' => $analysisId]);
                $updateResponse = $this->removeTaskFromAnalysis($updateRequest, $analysisId, $taskId);
                $updateData = json_decode($updateResponse->content(), true);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Task completely removed from system and analysis data',
                'data' => [
                    'deleted_task' => $deleteData['data']['deleted_task'] ?? null,
                    'analysis_updated' => isset($updateData) && $updateData['status'] === 'success',
                    'analysis_id' => $analysisId,
                    'removed_at' => now()->toISOString(),
                    'reason' => $reason
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to complete task removal', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to complete task removal',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}