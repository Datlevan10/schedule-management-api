<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiScheduleAnalysis;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
}