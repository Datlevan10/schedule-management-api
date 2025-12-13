<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\RawScheduleEntry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiAnalysisStatusController extends Controller
{
    /**
     * Get tasks available for AI analysis
     * Includes both manual events and CSV imported entries
     */
    public function getAvailableTasks(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'include_locked' => 'boolean',
            'source_type' => 'nullable|in:events,entries,all',
        ]);

        $userId = $request->user_id;
        $includeLocked = $request->boolean('include_locked', false);
        $sourceType = $request->get('source_type', 'all');

        $result = [
            'events' => [],
            'entries' => [],
            'summary' => []
        ];

        // Get manual events
        if (in_array($sourceType, ['events', 'all'])) {
            $eventsQuery = Event::where('user_id', $userId);
            
            if (!$includeLocked) {
                $eventsQuery->availableForAiAnalysis();
            }
            
            $events = $eventsQuery->select(
                'id',
                'title',
                'description',
                'start_datetime',
                'priority',
                'ai_analysis_status',
                'ai_analyzed_at',
                'ai_analysis_locked'
            )->get();

            $result['events'] = $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'start_datetime' => $event->start_datetime,
                    'priority' => $event->priority,
                    'ai_status' => $event->ai_analysis_status,
                    'ai_analyzed_at' => $event->ai_analyzed_at,
                    'is_locked' => $event->ai_analysis_locked,
                    'is_available' => $event->isAvailableForAiAnalysis(),
                    'source_type' => 'event'
                ];
            });
        }

        // Get CSV imported entries
        if (in_array($sourceType, ['entries', 'all'])) {
            $entriesQuery = RawScheduleEntry::where('user_id', $userId);
            
            if (!$includeLocked) {
                $entriesQuery->availableForAiAnalysis();
            }
            
            $entries = $entriesQuery->select(
                'id',
                'import_id',
                'parsed_title',
                'parsed_description',
                'parsed_start_datetime',
                'parsed_priority',
                'original_data',
                'ai_analysis_status',
                'ai_analyzed_at',
                'ai_analysis_locked'
            )->get();

            $result['entries'] = $entries->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'import_id' => $entry->import_id,
                    'title' => $entry->parsed_title ?? $entry->original_data['mon_hoc'] ?? 'Untitled',
                    'description' => $entry->parsed_description ?? $entry->original_data['ghi_chu'] ?? '',
                    'start_datetime' => $entry->parsed_start_datetime,
                    'priority' => $entry->parsed_priority ?? 3,
                    'original_data' => $entry->original_data,
                    'ai_status' => $entry->ai_analysis_status,
                    'ai_analyzed_at' => $entry->ai_analyzed_at,
                    'is_locked' => $entry->ai_analysis_locked,
                    'is_available' => $entry->isAvailableForAiAnalysis(),
                    'source_type' => 'entry'
                ];
            });
        }

        // Generate summary
        $result['summary'] = [
            'total_events' => count($result['events']),
            'total_entries' => count($result['entries']),
            'available_for_analysis' => collect($result['events'])->where('is_available', true)->count() +
                                       collect($result['entries'])->where('is_available', true)->count(),
            'already_analyzed' => collect($result['events'])->where('ai_status', 'completed')->count() +
                                 collect($result['entries'])->where('ai_status', 'completed')->count(),
            'in_progress' => collect($result['events'])->where('ai_status', 'in_progress')->count() +
                            collect($result['entries'])->where('ai_status', 'in_progress')->count(),
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Tasks retrieved successfully',
            'data' => $result
        ]);
    }

    /**
     * Mark tasks as being analyzed (lock them)
     */
    public function markTasksForAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|integer',
            'tasks.*.source_type' => 'required|in:event,entry',
            'analysis_batch_id' => 'nullable|string'
        ]);

        $userId = $request->user_id;
        $tasks = $request->tasks;
        $batchId = $request->analysis_batch_id ?? Str::uuid()->toString();
        
        $results = [
            'success' => [],
            'failed' => [],
            'already_locked' => []
        ];

        DB::beginTransaction();
        try {
            foreach ($tasks as $task) {
                if ($task['source_type'] === 'event') {
                    $model = Event::where('user_id', $userId)
                                 ->where('id', $task['id'])
                                 ->first();
                } else {
                    $model = RawScheduleEntry::where('user_id', $userId)
                                            ->where('id', $task['id'])
                                            ->first();
                }

                if (!$model) {
                    $results['failed'][] = [
                        'id' => $task['id'],
                        'type' => $task['source_type'],
                        'reason' => 'Task not found'
                    ];
                    continue;
                }

                if ($model->ai_analysis_locked || $model->ai_analysis_status === 'in_progress') {
                    $results['already_locked'][] = [
                        'id' => $task['id'],
                        'type' => $task['source_type'],
                        'status' => $model->ai_analysis_status
                    ];
                    continue;
                }

                if (!$model->isAvailableForAiAnalysis()) {
                    $results['failed'][] = [
                        'id' => $task['id'],
                        'type' => $task['source_type'],
                        'reason' => 'Task not available for analysis',
                        'current_status' => $model->ai_analysis_status
                    ];
                    continue;
                }

                // Mark as in progress
                $model->markAsAiAnalysisInProgress($batchId);
                
                $results['success'][] = [
                    'id' => $task['id'],
                    'type' => $task['source_type'],
                    'batch_id' => $batchId
                ];
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Tasks marked for analysis',
                'batch_id' => $batchId,
                'results' => $results
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark tasks for analysis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update AI analysis results
     */
    public function updateAnalysisResults(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'results' => 'required|array',
            'results.*.id' => 'required|integer',
            'results.*.source_type' => 'required|in:event,entry',
            'results.*.status' => 'required|in:completed,failed',
            'results.*.data' => 'nullable|array',
            'results.*.error' => 'nullable|string'
        ]);

        $userId = $request->user_id;
        $results = $request->results;
        
        $updateResults = [
            'success' => [],
            'failed' => []
        ];

        DB::beginTransaction();
        try {
            foreach ($results as $result) {
                if ($result['source_type'] === 'event') {
                    $model = Event::where('user_id', $userId)
                                 ->where('id', $result['id'])
                                 ->first();
                } else {
                    $model = RawScheduleEntry::where('user_id', $userId)
                                            ->where('id', $result['id'])
                                            ->first();
                }

                if (!$model) {
                    $updateResults['failed'][] = [
                        'id' => $result['id'],
                        'type' => $result['source_type'],
                        'reason' => 'Task not found'
                    ];
                    continue;
                }

                if ($result['status'] === 'completed') {
                    $model->markAsAiAnalyzed($result['data'] ?? []);
                } else {
                    $model->markAiAnalysisFailed($result['error'] ?? 'Unknown error');
                }

                $updateResults['success'][] = [
                    'id' => $result['id'],
                    'type' => $result['source_type'],
                    'status' => $result['status']
                ];
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Analysis results updated',
                'results' => $updateResults
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update analysis results',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset AI analysis status for tasks
     */
    public function resetAnalysisStatus(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|integer',
            'tasks.*.source_type' => 'required|in:event,entry',
        ]);

        $userId = $request->user_id;
        $tasks = $request->tasks;
        
        $resetResults = [
            'success' => [],
            'failed' => []
        ];

        DB::beginTransaction();
        try {
            foreach ($tasks as $task) {
                if ($task['source_type'] === 'event') {
                    $model = Event::where('user_id', $userId)
                                 ->where('id', $task['id'])
                                 ->first();
                } else {
                    $model = RawScheduleEntry::where('user_id', $userId)
                                            ->where('id', $task['id'])
                                            ->first();
                }

                if (!$model) {
                    $resetResults['failed'][] = [
                        'id' => $task['id'],
                        'type' => $task['source_type'],
                        'reason' => 'Task not found'
                    ];
                    continue;
                }

                $model->resetAiAnalysis();
                
                $resetResults['success'][] = [
                    'id' => $task['id'],
                    'type' => $task['source_type']
                ];
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Analysis status reset successfully',
                'results' => $resetResults
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reset analysis status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get AI analysis statistics for a user
     */
    public function getAnalysisStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $userId = $request->user_id;

        $eventStats = Event::where('user_id', $userId)
            ->selectRaw('ai_analysis_status, COUNT(*) as count')
            ->groupBy('ai_analysis_status')
            ->pluck('count', 'ai_analysis_status')
            ->toArray();

        $entryStats = RawScheduleEntry::where('user_id', $userId)
            ->selectRaw('ai_analysis_status, COUNT(*) as count')
            ->groupBy('ai_analysis_status')
            ->pluck('count', 'ai_analysis_status')
            ->toArray();

        $statistics = [
            'events' => [
                'total' => array_sum($eventStats),
                'pending' => $eventStats['pending'] ?? 0,
                'in_progress' => $eventStats['in_progress'] ?? 0,
                'completed' => $eventStats['completed'] ?? 0,
                'failed' => $eventStats['failed'] ?? 0,
                'skipped' => $eventStats['skipped'] ?? 0,
            ],
            'entries' => [
                'total' => array_sum($entryStats),
                'pending' => $entryStats['pending'] ?? 0,
                'in_progress' => $entryStats['in_progress'] ?? 0,
                'completed' => $entryStats['completed'] ?? 0,
                'failed' => $entryStats['failed'] ?? 0,
                'skipped' => $entryStats['skipped'] ?? 0,
            ],
            'overall' => [
                'total' => array_sum($eventStats) + array_sum($entryStats),
                'pending' => ($eventStats['pending'] ?? 0) + ($entryStats['pending'] ?? 0),
                'in_progress' => ($eventStats['in_progress'] ?? 0) + ($entryStats['in_progress'] ?? 0),
                'completed' => ($eventStats['completed'] ?? 0) + ($entryStats['completed'] ?? 0),
                'failed' => ($eventStats['failed'] ?? 0) + ($entryStats['failed'] ?? 0),
                'skipped' => ($eventStats['skipped'] ?? 0) + ($entryStats['skipped'] ?? 0),
            ]
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Analysis statistics retrieved',
            'data' => $statistics
        ]);
    }
}