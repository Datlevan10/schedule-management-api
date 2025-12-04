<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Event;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * Get dashboard statistics for admin panel
     * GET /api/v1/admin/dashboard/statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            // Get total users count
            $totalUsers = User::count();
            $activeUsers = User::where('is_active', true)->count();
            $inactiveUsers = User::where('is_active', false)->count();

            // Get total tasks/events count
            $totalTasks = Event::count();
            $scheduledTasks = Event::where('status', 'scheduled')->count();
            $inProgressTasks = Event::where('status', 'in_progress')->count();
            $completedTasks = Event::where('status', 'completed')->count();
            $cancelledTasks = Event::where('status', 'cancelled')->count();
            $postponedTasks = Event::where('status', 'postponed')->count();

            // Get manual tasks count
            $manualTasks = Event::whereJsonContains('event_metadata->created_manually', true)->count();

            // Get total admins count
            $totalAdmins = Admin::count();
            $activeAdmins = Admin::where('is_active', true)->count();

            // Get user registrations by time period
            $usersToday = User::whereDate('created_at', today())->count();
            $usersThisWeek = User::where('created_at', '>=', now()->startOfWeek())->count();
            $usersThisMonth = User::where('created_at', '>=', now()->startOfMonth())->count();

            // Get tasks created by time period
            $tasksToday = Event::whereDate('created_at', today())->count();
            $tasksThisWeek = Event::where('created_at', '>=', now()->startOfWeek())->count();
            $tasksThisMonth = Event::where('created_at', '>=', now()->startOfMonth())->count();

            // Get priority distribution
            $tasksByPriority = Event::select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->pluck('count', 'priority')
                ->toArray();

            // Get recent activity
            $recentUsers = User::latest()->limit(5)->select('id', 'name', 'email', 'created_at')->get();
            $recentTasks = Event::with(['user:id,name', 'category:id,name'])
                ->latest()
                ->limit(5)
                ->select('id', 'title', 'user_id', 'event_category_id', 'status', 'created_at')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => [
                    'users' => [
                        'total' => $totalUsers,
                        'active' => $activeUsers,
                        'inactive' => $inactiveUsers,
                        'today' => $usersToday,
                        'this_week' => $usersThisWeek,
                        'this_month' => $usersThisMonth,
                    ],
                    'tasks' => [
                        'total' => $totalTasks,
                        'scheduled' => $scheduledTasks,
                        'in_progress' => $inProgressTasks,
                        'completed' => $completedTasks,
                        'cancelled' => $cancelledTasks,
                        'postponed' => $postponedTasks,
                        'manual_tasks' => $manualTasks,
                        'today' => $tasksToday,
                        'this_week' => $tasksThisWeek,
                        'this_month' => $tasksThisMonth,
                        'by_priority' => $tasksByPriority
                    ],
                    'admins' => [
                        'total' => $totalAdmins,
                        'active' => $activeAdmins
                    ],
                    'recent_activity' => [
                        'recent_users' => $recentUsers,
                        'recent_tasks' => $recentTasks
                    ],
                    'summary' => [
                        'total_users' => $totalUsers,
                        'total_tasks' => $totalTasks,
                        'total_admins' => $totalAdmins,
                        'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0,
                        'active_user_percentage' => $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 2) : 0
                    ]
                ],
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get quick summary for admin dashboard
     * GET /api/v1/admin/dashboard/summary
     */
    public function getQuickSummary(Request $request): JsonResponse
    {
        try {
            $totalUsers = User::count();
            $totalTasks = Event::count();
            $totalAdmins = Admin::count();
            
            $activeUsers = User::where('is_active', true)->count();
            $completedTasks = Event::where('status', 'completed')->count();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Quick summary retrieved successfully',
                'data' => [
                    'total_users' => $totalUsers,
                    'total_tasks' => $totalTasks,
                    'total_admins' => $totalAdmins,
                    'active_users' => $activeUsers,
                    'completed_tasks' => $completedTasks,
                    'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve quick summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}