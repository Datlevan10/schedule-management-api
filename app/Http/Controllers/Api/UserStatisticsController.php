<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\RawScheduleEntry;
use App\Models\User;
use App\Models\SmartNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserStatisticsController extends Controller
{
    /**
     * Get comprehensive user statistics for dashboard
     * GET /api/v1/users/{userId}/statistics
     */
    public function getUserStatistics(Request $request, $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        
        // Get current date for calculations
        $now = Carbon::now();
        $startOfYear = Carbon::now()->startOfYear();
        $endOfYear = Carbon::now()->endOfYear();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        
        // 1. Total Tasks Count
        $totalEvents = Event::where('user_id', $userId)->count();
        $totalEntries = RawScheduleEntry::where('user_id', $userId)
            ->whereNotNull('parsed_title')
            ->count();
        $totalTasks = $totalEvents + $totalEntries;
        
        // 2. Active Tasks: AI-analyzed tasks that are not completed or failed
        // Manual tasks (Events) that have been AI-analyzed and are active
        $activeEvents = Event::where('user_id', $userId)
            ->where('ai_analysis_status', 'completed')  // Must be AI-analyzed
            ->whereNotIn('status', ['completed', 'cancelled', 'failed'])  // Not completed/failed
            ->count();
            
        // CSV-imported tasks (RawScheduleEntries) that have been AI-analyzed and are active
        $activeEntries = RawScheduleEntry::where('user_id', $userId)
            ->where('ai_analysis_status', 'completed')  // Must be AI-analyzed
            ->where(function($query) {
                $query->where('processing_status', '!=', 'failed')
                    ->where(function($q) {
                        $q->whereNull('conversion_status')
                          ->orWhere('conversion_status', '!=', 'failed');
                    });
            })
            ->whereNull('converted_event_id')  // Not yet converted to an event
            ->count();
            
        $activeTasks = $activeEvents + $activeEntries;
        
        // 3. Analyzed Tasks
        $analyzedEvents = Event::where('user_id', $userId)
            ->where('ai_analysis_status', 'completed')
            ->count();
            
        $analyzedEntries = RawScheduleEntry::where('user_id', $userId)
            ->where('ai_analysis_status', 'completed')
            ->count();
            
        $analyzedTasks = $analyzedEvents + $analyzedEntries;
        
        // 4. Tasks with Reminders
        $tasksWithReminders = SmartNotification::where('user_id', $userId)
            ->where('type', 'reminder')
            ->whereNotNull('event_id')
            ->distinct('event_id')
            ->count('event_id');
        
        // 5. Pending Reminders (upcoming reminders)
        $pendingReminders = SmartNotification::where('user_id', $userId)
            ->where('type', 'reminder')
            ->where('status', 'pending')
            ->where('trigger_datetime', '>=', $now)
            ->where('trigger_datetime', '<=', $now->copy()->addDays(7))
            ->count();
        
        // 6. Calculate Productivity Score
        $productivity = $this->calculateProductivityScore($userId, $now);
        
        // 7. Get AI Analysis Statistics
        $aiAnalysisStats = $this->getAiAnalysisStatistics($userId);
        
        // 8. Task Completion Rate
        $completionRate = $this->calculateCompletionRate($userId, $startOfMonth, $endOfMonth);
        
        // 9. Reminder Frequency (percentage of year with reminders)
        $reminderFrequency = $this->calculateReminderFrequency($userId, $startOfYear, $endOfYear);
        
        // 10. Weekly Statistics
        $weeklyStats = $this->getWeeklyStatistics($userId, $startOfWeek, $endOfWeek);
        
        // 11. Priority Distribution
        $priorityDistribution = $this->getPriorityDistribution($userId);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'total_tasks' => $totalTasks,
                    'active_tasks' => $activeTasks,
                    'analyzed_tasks' => $analyzedTasks,
                    'tasks_with_reminders' => $tasksWithReminders,
                    'pending_reminders' => $pendingReminders,
                ],
                'productivity' => [
                    'score' => $productivity['score'],
                    'percentage' => $productivity['percentage'],
                    'trend' => $productivity['trend'],
                    'level' => $productivity['level'],
                ],
                'completion' => [
                    'rate' => $completionRate['rate'],
                    'completed' => $completionRate['completed'],
                    'total' => $completionRate['total'],
                    'period' => 'this_month',
                ],
                'reminders' => [
                    'frequency_percentage' => $reminderFrequency['percentage'],
                    'total_reminders' => $reminderFrequency['total'],
                    'days_with_reminders' => $reminderFrequency['days_with_reminders'],
                    'average_per_day' => $reminderFrequency['average_per_day'],
                ],
                'ai_analysis' => $aiAnalysisStats,
                'weekly' => $weeklyStats,
                'priority_distribution' => $priorityDistribution,
                'last_updated' => $now->toIso8601String(),
            ]
        ]);
    }
    
    /**
     * Get dashboard card statistics
     * GET /api/v1/users/{userId}/dashboard-stats
     */
    public function getDashboardStats(Request $request, $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $now = Carbon::now();
        
        // Active Tasks: Count all AI-analyzed tasks (manual + CSV) that are not completed or failed
        // 1. Manual tasks (Events) that have been AI-analyzed and are active
        $activeManualTasks = Event::where('user_id', $userId)
            ->where('ai_analysis_status', 'completed')  // Must be AI-analyzed
            ->whereNotIn('status', ['completed', 'cancelled', 'failed'])  // Not completed/failed
            ->count();
        
        // 2. CSV-imported tasks (RawScheduleEntries) that have been AI-analyzed and are active
        $activeCsvTasks = RawScheduleEntry::where('user_id', $userId)
            ->where('ai_analysis_status', 'completed')  // Must be AI-analyzed
            ->where(function($query) {
                // Include entries that are not failed and either:
                // - Not yet converted (conversion_status != 'success')
                // - Successfully parsed but pending further action
                $query->where('processing_status', '!=', 'failed')
                    ->where(function($q) {
                        $q->whereNull('conversion_status')
                          ->orWhere('conversion_status', '!=', 'failed');
                    });
            })
            ->whereNull('converted_event_id')  // Not yet converted to an event (to avoid double counting)
            ->count();
        
        // Total active tasks
        $activeTasks = $activeManualTasks + $activeCsvTasks;
        
        // Pending Reminders from smart_notifications table
        $reminders = SmartNotification::where('user_id', $userId)
            ->where('type', 'reminder')
            ->where('status', 'pending')
            ->where('trigger_datetime', '>=', $now)
            ->where('trigger_datetime', '<=', $now->copy()->addDays(7))
            ->count();
        
        // Productivity Score
        $productivity = $this->calculateProductivityScore($userId, $now);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'active_tasks' => $activeTasks,
                'reminders' => $reminders,
                'productivity' => $productivity['percentage'] . '%',
                'productivity_score' => $productivity['score'],
                'productivity_trend' => $productivity['trend'],
            ]
        ]);
    }
    
    /**
     * Calculate productivity score based on various factors
     */
    private function calculateProductivityScore($userId, Carbon $now): array
    {
        $startOfWeek = $now->copy()->startOfWeek();
        $endOfWeek = $now->copy()->endOfWeek();
        $lastWeekStart = $now->copy()->subWeek()->startOfWeek();
        $lastWeekEnd = $now->copy()->subWeek()->endOfWeek();
        
        // This week's completed tasks
        $thisWeekCompleted = Event::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('end_datetime', [$startOfWeek, $endOfWeek])
            ->count();
        
        // This week's total tasks
        $thisWeekTotal = Event::where('user_id', $userId)
            ->whereBetween('start_datetime', [$startOfWeek, $endOfWeek])
            ->count();
        
        // Last week's completed tasks
        $lastWeekCompleted = Event::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('end_datetime', [$lastWeekStart, $lastWeekEnd])
            ->count();
        
        // Calculate base score
        $baseScore = $thisWeekTotal > 0 
            ? round(($thisWeekCompleted / $thisWeekTotal) * 100) 
            : 0;
        
        // Factor in AI-optimized tasks
        $aiOptimizedTasks = Event::where('user_id', $userId)
            ->where('ai_analysis_status', 'completed')
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->count();
        
        // Bonus for using AI optimization
        $aiBonus = min($aiOptimizedTasks * 2, 15); // Max 15% bonus
        
        // Calculate final score
        $finalScore = min($baseScore + $aiBonus, 100);
        
        // Determine trend
        $trend = 'stable';
        if ($thisWeekCompleted > $lastWeekCompleted * 1.1) {
            $trend = 'up';
        } elseif ($thisWeekCompleted < $lastWeekCompleted * 0.9) {
            $trend = 'down';
        }
        
        // Determine level
        $level = 'low';
        if ($finalScore >= 85) {
            $level = 'excellent';
        } elseif ($finalScore >= 70) {
            $level = 'good';
        } elseif ($finalScore >= 50) {
            $level = 'moderate';
        }
        
        return [
            'score' => $finalScore,
            'percentage' => $finalScore,
            'trend' => $trend,
            'level' => $level,
            'details' => [
                'completed_this_week' => $thisWeekCompleted,
                'total_this_week' => $thisWeekTotal,
                'ai_optimized' => $aiOptimizedTasks,
                'ai_bonus' => $aiBonus,
            ]
        ];
    }
    
    /**
     * Get AI analysis statistics from ai_schedule_analyses table
     */
    private function getAiAnalysisStatistics($userId): array
    {
        $stats = DB::table('ai_schedule_analyses')
            ->where('user_id', $userId)
            ->selectRaw('
                COUNT(*) as total_analyses,
                COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_analyses,
                COUNT(CASE WHEN user_approved = 1 THEN 1 END) as approved_schedules,
                AVG(confidence_score) as avg_confidence,
                AVG(user_rating) as avg_rating,
                COUNT(DISTINCT DATE(target_date)) as days_analyzed
            ')
            ->first();
        
        // Get recent analysis
        $recentAnalysis = DB::table('ai_schedule_analyses')
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->first();
        
        return [
            'total_analyses' => $stats->total_analyses ?? 0,
            'completed_analyses' => $stats->completed_analyses ?? 0,
            'approved_schedules' => $stats->approved_schedules ?? 0,
            'average_confidence' => round($stats->avg_confidence ?? 0, 2),
            'average_rating' => round($stats->avg_rating ?? 0, 1),
            'days_analyzed' => $stats->days_analyzed ?? 0,
            'last_analysis' => $recentAnalysis ? Carbon::parse($recentAnalysis->created_at)->toIso8601String() : null,
        ];
    }
    
    /**
     * Calculate task completion rate
     */
    private function calculateCompletionRate($userId, Carbon $startDate, Carbon $endDate): array
    {
        $totalTasks = Event::where('user_id', $userId)
            ->whereBetween('start_datetime', [$startDate, $endDate])
            ->count();
        
        $completedTasks = Event::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('end_datetime', [$startDate, $endDate])
            ->count();
        
        $rate = $totalTasks > 0 
            ? round(($completedTasks / $totalTasks) * 100, 1)
            : 0;
        
        return [
            'rate' => $rate,
            'completed' => $completedTasks,
            'total' => $totalTasks,
        ];
    }
    
    /**
     * Calculate reminder frequency as percentage of year
     */
    private function calculateReminderFrequency($userId, Carbon $startOfYear, Carbon $endOfYear): array
    {
        // Get all reminders for the year
        $remindersInYear = SmartNotification::where('user_id', $userId)
            ->where('type', 'reminder')
            ->whereBetween('trigger_datetime', [$startOfYear, $endOfYear])
            ->get();
        
        // Count unique days with reminders
        $daysWithReminders = $remindersInYear
            ->pluck('trigger_datetime')
            ->map(function ($date) {
                return Carbon::parse($date)->format('Y-m-d');
            })
            ->unique()
            ->count();
        
        // Calculate percentage of year
        $daysInYear = $startOfYear->diffInDays($endOfYear) + 1;
        $daysPassed = $startOfYear->diffInDays(Carbon::now()) + 1;
        
        // Use days passed for more accurate percentage
        $percentage = $daysPassed > 0 
            ? round(($daysWithReminders / $daysPassed) * 100, 1)
            : 0;
        
        return [
            'percentage' => $percentage,
            'total' => $remindersInYear->count(),
            'days_with_reminders' => $daysWithReminders,
            'average_per_day' => $daysWithReminders > 0 
                ? round($remindersInYear->count() / $daysWithReminders, 1)
                : 0,
        ];
    }
    
    /**
     * Get weekly statistics
     */
    private function getWeeklyStatistics($userId, Carbon $startOfWeek, Carbon $endOfWeek): array
    {
        $events = Event::where('user_id', $userId)
            ->whereBetween('start_datetime', [$startOfWeek, $endOfWeek])
            ->get();
        
        // Group by day
        $byDay = [];
        for ($date = $startOfWeek->copy(); $date <= $endOfWeek; $date->addDay()) {
            $dayName = $date->format('l');
            $dayEvents = $events->filter(function ($event) use ($date) {
                return Carbon::parse($event->start_datetime)->isSameDay($date);
            });
            
            $byDay[$dayName] = [
                'total' => $dayEvents->count(),
                'completed' => $dayEvents->where('status', 'completed')->count(),
                'scheduled' => $dayEvents->where('status', 'scheduled')->count(),
            ];
        }
        
        return [
            'total_tasks' => $events->count(),
            'completed' => $events->where('status', 'completed')->count(),
            'scheduled' => $events->where('status', 'scheduled')->count(),
            'cancelled' => $events->where('status', 'cancelled')->count(),
            'by_day' => $byDay,
        ];
    }
    
    /**
     * Get priority distribution
     */
    private function getPriorityDistribution($userId): array
    {
        $distribution = Event::where('user_id', $userId)
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->orderBy('priority', 'desc')
            ->get()
            ->mapWithKeys(function ($item) {
                $labels = [
                    5 => 'Critical',
                    4 => 'High',
                    3 => 'Medium',
                    2 => 'Low',
                    1 => 'Very Low',
                ];
                return [$labels[$item->priority] ?? 'Unknown' => $item->count];
            })
            ->toArray();
        
        return $distribution;
    }
    
    /**
     * Get reminder statistics for a specific period
     * GET /api/v1/users/{userId}/reminder-stats
     */
    public function getReminderStatistics(Request $request, $userId): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|in:day,week,month,year',
            'date' => 'nullable|date',
        ]);
        
        $period = $request->get('period', 'week');
        $baseDate = $request->has('date') ? Carbon::parse($request->date) : Carbon::now();
        
        // Define date range based on period
        switch ($period) {
            case 'day':
                $startDate = $baseDate->copy()->startOfDay();
                $endDate = $baseDate->copy()->endOfDay();
                break;
            case 'week':
                $startDate = $baseDate->copy()->startOfWeek();
                $endDate = $baseDate->copy()->endOfWeek();
                break;
            case 'month':
                $startDate = $baseDate->copy()->startOfMonth();
                $endDate = $baseDate->copy()->endOfMonth();
                break;
            case 'year':
                $startDate = $baseDate->copy()->startOfYear();
                $endDate = $baseDate->copy()->endOfYear();
                break;
            default:
                $startDate = $baseDate->copy()->startOfWeek();
                $endDate = $baseDate->copy()->endOfWeek();
        }
        
        // Get reminders in the period
        $remindersInPeriod = SmartNotification::where('user_id', $userId)
            ->where('type', 'reminder')
            ->whereBetween('trigger_datetime', [$startDate, $endDate])
            ->get();
        
        // Calculate statistics
        $totalReminders = $remindersInPeriod->count();
        $upcomingReminders = $remindersInPeriod
            ->where('trigger_datetime', '>=', Carbon::now())
            ->where('status', 'pending')
            ->count();
        
        // Group by subtype or priority
        $reminderTypes = $remindersInPeriod
            ->groupBy('subtype')
            ->map(function ($group, $subtype) {
                return [
                    'type' => $subtype ?: 'standard',
                    'count' => $group->count(),
                ];
            })
            ->values();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => $period,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'total_reminders' => $totalReminders,
                'upcoming_reminders' => $upcomingReminders,
                'past_reminders' => $totalReminders - $upcomingReminders,
                'reminder_distribution' => $reminderTypes,
                'daily_average' => round($totalReminders / max($startDate->diffInDays($endDate), 1), 1),
            ]
        ]);
    }
    
    /**
     * Format reminder time to human readable
     */
    private function formatReminderTime($minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} minutes";
        } elseif ($minutes < 1440) {
            $hours = round($minutes / 60, 1);
            return "{$hours} hours";
        } else {
            $days = round($minutes / 1440, 1);
            return "{$days} days";
        }
    }
}