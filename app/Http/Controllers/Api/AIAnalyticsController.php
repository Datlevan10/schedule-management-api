<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiScheduleAnalysis;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AIAnalyticsController extends Controller
{
    /**
     * Get comprehensive AI task analytics dashboard
     * GET /api/v1/ai-analytics/dashboard
     */
    public function getDashboardAnalytics(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->toDateString());
            $dateTo = $request->input('date_to', Carbon::now()->toDateString());
            $userId = $request->input('user_id');

            // Build base queries with date filtering
            $analysisQuery = AiScheduleAnalysis::whereBetween('created_at', [$dateFrom, $dateTo]);
            $eventQuery = Event::whereBetween('created_at', [$dateFrom, $dateTo]);

            // Apply user filter if specified
            if ($userId) {
                $analysisQuery->where('user_id', $userId);
                $eventQuery->where('user_id', $userId);
            }

            // Get task status analytics
            $taskStatusAnalytics = $this->getTaskStatusAnalytics($eventQuery);
            
            // Get AI performance metrics
            $aiPerformanceMetrics = $this->getAIPerformanceMetrics($analysisQuery);
            
            // Get AI processing statistics
            $processingStats = $this->getAIProcessingStatistics($analysisQuery);
            
            // Get task completion trends
            $completionTrends = $this->getTaskCompletionTrends($dateFrom, $dateTo, $userId);
            
            // Get AI recommendation accuracy
            $recommendationAccuracy = $this->getAIRecommendationAccuracy($analysisQuery);

            return response()->json([
                'status' => 'success',
                'message' => 'AI analytics dashboard retrieved successfully',
                'data' => [
                    'period' => [
                        'from' => $dateFrom,
                        'to' => $dateTo,
                        'user_filter' => $userId
                    ],
                    'task_analytics' => $taskStatusAnalytics,
                    'ai_performance' => $aiPerformanceMetrics,
                    'processing_statistics' => $processingStats,
                    'completion_trends' => $completionTrends,
                    'recommendation_accuracy' => $recommendationAccuracy,
                    'summary' => [
                        'total_ai_analyses' => $analysisQuery->count(),
                        'total_tasks_processed' => $eventQuery->count(),
                        'ai_success_rate' => $aiPerformanceMetrics['success_rate'] ?? 0,
                        'average_confidence_score' => $aiPerformanceMetrics['average_confidence'] ?? 0
                    ],
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve AI analytics dashboard', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve analytics dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get task status analytics
     */
    private function getTaskStatusAnalytics($eventQuery): array
    {
        $totalTasks = $eventQuery->count();
        
        // Get status distribution
        $statusCounts = $eventQuery->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Map status counts to expected format
        $analytics = [
            'completed' => $statusCounts['completed'] ?? 0,
            'in_progress' => $statusCounts['in_progress'] ?? 0,
            'scheduled' => $statusCounts['scheduled'] ?? 0,
            'cancelled' => $statusCounts['cancelled'] ?? 0,
            'postponed' => $statusCounts['postponed'] ?? 0,
            'pending' => $statusCounts['pending'] ?? 0
        ];

        // Calculate percentages
        $percentages = [];
        foreach ($analytics as $status => $count) {
            $percentages[$status] = $totalTasks > 0 ? round(($count / $totalTasks) * 100, 2) : 0;
        }

        return [
            'total_tasks' => $totalTasks,
            'status_counts' => $analytics,
            'status_percentages' => $percentages,
            'completion_rate' => $percentages['completed'] ?? 0
        ];
    }

    /**
     * Get AI performance metrics
     */
    private function getAIPerformanceMetrics($analysisQuery): array
    {
        $analyses = $analysisQuery->get();
        $totalAnalyses = $analyses->count();

        if ($totalAnalyses === 0) {
            return [
                'total_analyses' => 0,
                'success_rate' => 0,
                'average_confidence' => 0,
                'average_processing_time' => 0,
                'total_cost' => 0,
                'total_tokens' => 0
            ];
        }

        $successfulAnalyses = $analyses->where('status', 'completed')->count();
        $avgConfidence = $analyses->avg('confidence_score');
        $avgProcessingTime = $analyses->avg('processing_time_ms');
        $totalCost = $analyses->sum('api_cost');
        $totalTokens = $analyses->sum('token_usage');

        // Get tasks analyzed from optimization metrics
        $totalTasksAnalyzed = $analyses->sum(function($analysis) {
            return $analysis->optimization_metrics['tasks_analyzed'] ?? 0;
        });

        return [
            'total_analyses' => $totalAnalyses,
            'successful_analyses' => $successfulAnalyses,
            'failed_analyses' => $totalAnalyses - $successfulAnalyses,
            'success_rate' => round(($successfulAnalyses / $totalAnalyses) * 100, 2),
            'average_confidence' => round($avgConfidence ?? 0, 2),
            'average_processing_time' => round($avgProcessingTime ?? 0, 2),
            'total_cost' => round($totalCost ?? 0, 4),
            'total_tokens' => $totalTokens ?? 0,
            'total_tasks_analyzed' => $totalTasksAnalyzed,
            'average_tasks_per_analysis' => round($totalTasksAnalyzed / $totalAnalyses, 2)
        ];
    }

    /**
     * Get AI processing statistics
     */
    private function getAIProcessingStatistics($analysisQuery): array
    {
        $analyses = $analysisQuery->get();

        // User approval statistics
        $userRatings = $analyses->whereNotNull('user_rating');
        $avgUserRating = $userRatings->avg('user_rating');
        $approvedCount = $analyses->where('user_approved', true)->count();
        
        // Analysis type breakdown
        $typeBreakdown = $analyses->groupBy('analysis_type')->map->count();
        
        // Confidence level distribution
        $confidenceDistribution = [
            'high' => $analyses->where('confidence_score', '>=', 0.8)->count(),
            'medium' => $analyses->where('confidence_score', '>=', 0.6)->where('confidence_score', '<', 0.8)->count(),
            'low' => $analyses->where('confidence_score', '<', 0.6)->count()
        ];

        // AI model usage
        $modelUsage = $analyses->groupBy('ai_model')->map->count();

        return [
            'user_feedback' => [
                'total_rated' => $userRatings->count(),
                'average_rating' => round($avgUserRating ?? 0, 2),
                'approved_analyses' => $approvedCount,
                'approval_rate' => $analyses->count() > 0 ? round(($approvedCount / $analyses->count()) * 100, 2) : 0
            ],
            'analysis_types' => $typeBreakdown,
            'confidence_distribution' => $confidenceDistribution,
            'ai_models' => $modelUsage,
            'processing_efficiency' => [
                'average_retry_count' => round($analyses->avg('retry_count') ?? 0, 2),
                'error_rate' => $analyses->count() > 0 ? round(($analyses->whereNotNull('error_details')->count() / $analyses->count()) * 100, 2) : 0
            ]
        ];
    }

    /**
     * Get task completion trends over time
     */
    private function getTaskCompletionTrends(string $dateFrom, string $dateTo, ?int $userId): array
    {
        $query = Event::whereBetween('created_at', [$dateFrom, $dateTo]);
        
        if ($userId) {
            $query->where('user_id', $userId);
        }

        // Daily completion trends
        $dailyTrends = $query
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total_tasks'),
                DB::raw("COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_tasks")
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(function($day) {
                return [
                    'date' => $day->date,
                    'total_tasks' => $day->total_tasks,
                    'completed_tasks' => $day->completed_tasks,
                    'completion_rate' => $day->total_tasks > 0 ? round(($day->completed_tasks / $day->total_tasks) * 100, 2) : 0
                ];
            });

        return [
            'daily_trends' => $dailyTrends,
            'period_summary' => [
                'total_days' => $dailyTrends->count(),
                'average_daily_tasks' => round($dailyTrends->avg('total_tasks') ?? 0, 2),
                'average_daily_completion_rate' => round($dailyTrends->avg('completion_rate') ?? 0, 2)
            ]
        ];
    }

    /**
     * Get AI recommendation accuracy metrics
     */
    private function getAIRecommendationAccuracy($analysisQuery): array
    {
        $analyses = $analysisQuery->where('status', 'completed')->get();
        
        if ($analyses->isEmpty()) {
            return [
                'total_recommendations' => 0,
                'accuracy_score' => 0,
                'user_satisfaction' => 0
            ];
        }

        // Calculate accuracy based on user approval and ratings
        $totalRecommendations = $analyses->count();
        $approvedRecommendations = $analyses->where('user_approved', true)->count();
        $ratedAnalyses = $analyses->whereNotNull('user_rating');
        
        $accuracyScore = $totalRecommendations > 0 ? ($approvedRecommendations / $totalRecommendations) * 100 : 0;
        $avgUserSatisfaction = $ratedAnalyses->avg('user_rating') ?? 0;

        return [
            'total_recommendations' => $totalRecommendations,
            'approved_recommendations' => $approvedRecommendations,
            'accuracy_score' => round($accuracyScore, 2),
            'user_satisfaction' => round($avgUserSatisfaction * 20, 2), // Convert 5-star to percentage
            'confidence_vs_approval' => [
                'high_confidence_approved' => $analyses->where('confidence_score', '>=', 0.8)->where('user_approved', true)->count(),
                'medium_confidence_approved' => $analyses->where('confidence_score', '>=', 0.6)->where('confidence_score', '<', 0.8)->where('user_approved', true)->count(),
                'low_confidence_approved' => $analyses->where('confidence_score', '<', 0.6)->where('user_approved', true)->count()
            ]
        ];
    }

    /**
     * Get specific user analytics
     * GET /api/v1/ai-analytics/user/{userId}
     */
    public function getUserAnalytics(Request $request, int $userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);
            $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->toDateString());
            $dateTo = $request->input('date_to', Carbon::now()->toDateString());

            // Get user-specific analytics by calling dashboard with user filter
            $request->merge(['user_id' => $userId]);
            $dashboardResponse = $this->getDashboardAnalytics($request);
            $dashboardData = json_decode($dashboardResponse->content(), true);

            // Add user-specific information
            $userSpecificData = [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ],
                'user_activity' => [
                    'first_analysis' => AiScheduleAnalysis::where('user_id', $userId)->oldest()->value('created_at'),
                    'latest_analysis' => AiScheduleAnalysis::where('user_id', $userId)->latest()->value('created_at'),
                    'total_analyses' => AiScheduleAnalysis::where('user_id', $userId)->count(),
                    'total_tasks_created' => Event::where('user_id', $userId)->count()
                ]
            ];

            if ($dashboardData['status'] === 'success') {
                $dashboardData['data']['user_info'] = $userSpecificData;
            }

            return response()->json($dashboardData);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve user analytics', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve user analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get analytics for chart data (formatted for frontend charts)
     * GET /api/v1/ai-analytics/charts
     */
    public function getChartsData(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('user_id');
            $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->toDateString());
            $dateTo = $request->input('date_to', Carbon::now()->toDateString());

            $eventQuery = Event::whereBetween('created_at', [$dateFrom, $dateTo]);
            if ($userId) {
                $eventQuery->where('user_id', $userId);
            }

            $taskStatusAnalytics = $this->getTaskStatusAnalytics($eventQuery);

            // Format data for charts (matching your frontend structure)
            $chartData = [
                [
                    'label' => 'Hoàn thành',
                    'value' => $taskStatusAnalytics['status_counts']['completed'],
                    'color' => '#28a745' // success color
                ],
                [
                    'label' => 'Đang tiến hành',
                    'value' => $taskStatusAnalytics['status_counts']['in_progress'],
                    'color' => '#ffc107' // warning color
                ],
                [
                    'label' => 'Đã lên lịch',
                    'value' => $taskStatusAnalytics['status_counts']['scheduled'],
                    'color' => '#007bff' // primary color
                ],
                [
                    'label' => 'Đã hủy',
                    'value' => $taskStatusAnalytics['status_counts']['cancelled'],
                    'color' => '#dc3545' // danger color
                ],
                [
                    'label' => 'Đã hoãn lại',
                    'value' => $taskStatusAnalytics['status_counts']['postponed'],
                    'color' => '#17a2b8' // info color
                ]
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'Chart data retrieved successfully',
                'data' => [
                    'task_analytics_data' => $chartData,
                    'summary' => [
                        'total_tasks' => $taskStatusAnalytics['total_tasks'],
                        'completion_rate' => $taskStatusAnalytics['completion_rate']
                    ],
                    'period' => [
                        'from' => $dateFrom,
                        'to' => $dateTo
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve chart data', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve chart data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}