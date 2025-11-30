<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiScheduleAnalysis;
use App\Models\RawScheduleImport;
use App\Models\RawScheduleEntry;
use App\Models\OptimizedScheduleSlot;
use App\Models\SmartNotification;
use App\Services\OpenAIScheduleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AiScheduleAnalysisController extends Controller
{
    protected $openAIService;

    public function __construct(OpenAIScheduleService $openAIService)
    {
        $this->openAIService = $openAIService;
    }

    /**
     * Analyze schedule from import data
     * POST /api/v1/ai-schedule/analyze
     */
    public function analyzeSchedule(Request $request): JsonResponse
    {
        $request->validate([
            'import_id' => 'nullable|exists:raw_schedule_imports,id',
            'tasks' => 'nullable|array',
            'target_date' => 'nullable|date',
            'analysis_type' => 'nullable|in:daily,weekly,monthly',
            'preferences' => 'nullable|array',
            'preferences.work_start' => 'nullable|date_format:H:i',
            'preferences.work_end' => 'nullable|date_format:H:i',
            'preferences.break_duration' => 'nullable|integer|min:0|max:120',
            'preferences.constraints' => 'nullable|array',
            'auto_create_events' => 'nullable|boolean'
        ]);

        DB::beginTransaction();
        try {
            $userId = Auth::id();
            $targetDate = $request->target_date ?? Carbon::today()->format('Y-m-d');
            $analysisType = $request->analysis_type ?? 'daily';

            // Get tasks from import or request
            $tasks = $this->getTasksForAnalysis($request);

            if (empty($tasks)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tasks found for analysis'
                ], 400);
            }

            // Get user preferences
            $preferences = $this->getUserPreferences($userId, $request->preferences ?? []);

            // Create analysis record
            $analysis = AiScheduleAnalysis::create([
                'user_id' => $userId,
                'import_id' => $request->import_id,
                'input_data' => $tasks,
                'analysis_type' => $analysisType,
                'target_date' => $targetDate,
                'end_date' => $this->calculateEndDate($targetDate, $analysisType),
                'status' => 'processing',
                'user_preferences' => $preferences,
                'work_start_time' => $preferences['work_start'] ?? '08:00',
                'work_end_time' => $preferences['work_end'] ?? '18:00',
                'break_duration' => $preferences['break_duration'] ?? 60
            ]);

            // Call OpenAI service
            $result = $this->openAIService->analyzeSchedule($tasks, $preferences, $targetDate);

            if (!$result['success']) {
                $analysis->update([
                    'status' => 'failed',
                    'error_details' => ['error' => $result['error']]
                ]);

                DB::rollback();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to analyze schedule',
                    'error' => $result['error']
                ], 500);
            }

            // Update analysis with results
            $analysis->update([
                'status' => 'completed',
                'ai_model' => $result['model'],
                'ai_request_payload' => $result['raw_response']['choices'][0]['message'] ?? null,
                'ai_response' => $result['raw_response'],
                'optimized_schedule' => $result['schedule'],
                'optimization_metrics' => $result['schedule']['optimization_summary'] ?? null,
                'ai_reasoning' => $this->openAIService->generateVietnameseExplanation($result['schedule']),
                'confidence_score' => $this->calculateConfidenceScore($result['schedule']),
                'processing_time_ms' => $result['processing_time_ms'],
                'token_usage' => $result['token_usage']['total_tokens'] ?? null,
                'api_cost' => $this->calculateApiCost($result['token_usage'] ?? [])
            ]);

            // Create schedule slots
            $analysis->createScheduleSlots();

            // Auto-create events if requested
            if ($request->auto_create_events) {
                $this->createEventsFromSlots($analysis);
            }

            // Schedule notifications
            $this->scheduleNotifications($analysis);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Schedule analyzed successfully',
                'data' => [
                    'analysis_id' => $analysis->id,
                    'status' => $analysis->status,
                    'target_date' => $analysis->target_date,
                    'total_tasks' => count($tasks),
                    'scheduled_tasks' => $analysis->scheduleSlots()->count(),
                    'schedule' => $analysis->optimized_schedule,
                    'reasoning' => $analysis->ai_reasoning,
                    'confidence_score' => $analysis->confidence_score,
                    'processing_time_ms' => $analysis->processing_time_ms
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Schedule analysis error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get analysis result
     * GET /api/v1/ai-schedule/analysis/{id}
     */
    public function getAnalysis($id): JsonResponse
    {
        $analysis = AiScheduleAnalysis::with(['scheduleSlots', 'import'])
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'analysis' => $analysis,
                'slots' => $analysis->scheduleSlots,
                'metrics' => $analysis->optimization_metrics,
                'reasoning' => $analysis->ai_reasoning
            ]
        ]);
    }

    /**
     * List user's analyses
     * GET /api/v1/ai-schedule/analyses
     */
    public function listAnalyses(Request $request): JsonResponse
    {
        $query = AiScheduleAnalysis::where('user_id', Auth::id())
            ->with('scheduleSlots');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('from_date')) {
            $query->whereDate('target_date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('target_date', '<=', $request->to_date);
        }

        $analyses = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $analyses
        ]);
    }

    /**
     * Approve/reject analysis
     * POST /api/v1/ai-schedule/analysis/{id}/approve
     */
    public function approveAnalysis(Request $request, $id): JsonResponse
    {
        $request->validate([
            'approved' => 'required|boolean',
            'rating' => 'nullable|integer|min:1|max:5',
            'feedback' => 'nullable|string',
            'create_events' => 'nullable|boolean'
        ]);

        $analysis = AiScheduleAnalysis::where('user_id', Auth::id())
            ->findOrFail($id);

        $analysis->update([
            'user_approved' => $request->approved,
            'user_rating' => $request->rating,
            'user_feedback' => $request->feedback
        ]);

        if ($request->approved && $request->create_events) {
            $this->createEventsFromSlots($analysis);
        }

        return response()->json([
            'success' => true,
            'message' => $request->approved ? 'Schedule approved' : 'Schedule rejected'
        ]);
    }

    /**
     * Get optimized schedule slots
     * GET /api/v1/ai-schedule/slots
     */
    public function getScheduleSlots(Request $request): JsonResponse
    {
        $query = OptimizedScheduleSlot::where('user_id', Auth::id())
            ->with(['analysis', 'event']);

        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        } else {
            $query->upcoming();
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $slots = $query->orderBy('date')
            ->orderBy('start_time')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $slots
        ]);
    }

    /**
     * Update schedule slot
     * PUT /api/v1/ai-schedule/slots/{id}
     */
    public function updateScheduleSlot(Request $request, $id): JsonResponse
    {
        $request->validate([
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'task_title' => 'nullable|string',
            'task_description' => 'nullable|string',
            'location' => 'nullable|string',
            'priority' => 'nullable|in:critical,high,medium,low',
            'status' => 'nullable|in:scheduled,in_progress,completed,cancelled,rescheduled'
        ]);

        $slot = OptimizedScheduleSlot::where('user_id', Auth::id())
            ->findOrFail($id);

        $slot->update($request->all());

        // Update associated event if exists
        if ($slot->event_id && $slot->event) {
            $slot->event->update([
                'title' => $slot->task_title,
                'description' => $slot->task_description,
                'location' => $slot->location,
                'priority' => $slot->priority,
                'start_datetime' => $slot->start_datetime,
                'end_datetime' => $slot->end_datetime
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Schedule slot updated',
            'data' => $slot
        ]);
    }

    /**
     * Confirm schedule slot
     * POST /api/v1/ai-schedule/slots/{id}/confirm
     */
    public function confirmSlot($id): JsonResponse
    {
        $slot = OptimizedScheduleSlot::where('user_id', Auth::id())
            ->findOrFail($id);

        $slot->confirm();

        return response()->json([
            'success' => true,
            'message' => 'Slot confirmed'
        ]);
    }

    /**
     * Mark slot as completed
     * POST /api/v1/ai-schedule/slots/{id}/complete
     */
    public function completeSlot($id): JsonResponse
    {
        $slot = OptimizedScheduleSlot::where('user_id', Auth::id())
            ->findOrFail($id);

        $slot->complete();

        // Update event if exists
        if ($slot->event) {
            $slot->event->update(['status' => 'completed']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Slot marked as completed'
        ]);
    }

    /**
     * Get tasks for analysis
     */
    protected function getTasksForAnalysis(Request $request): array
    {
        // If tasks provided directly
        if ($request->has('tasks') && !empty($request->tasks)) {
            return $request->tasks;
        }

        // If import_id provided
        if ($request->has('import_id')) {
            $entries = RawScheduleEntry::where('import_id', $request->import_id)
                ->where('user_id', Auth::id())
                ->whereIn('processing_status', ['parsed', 'converted'])
                ->get();

            return $entries->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'title' => $entry->parsed_title ?? $entry->raw_text,
                    'description' => $entry->parsed_description,
                    'parsed_start_datetime' => $entry->parsed_start_datetime,
                    'parsed_end_datetime' => $entry->parsed_end_datetime,
                    'location' => $entry->parsed_location,
                    'priority' => $entry->parsed_priority,
                    'category' => $entry->ai_detected_category,
                    'keywords' => $entry->detected_keywords,
                    'importance' => $entry->ai_detected_importance
                ];
            })->toArray();
        }

        // Get recent unparsed entries
        $entries = RawScheduleEntry::where('user_id', Auth::id())
            ->where('processing_status', 'parsed')
            ->whereNull('converted_event_id')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return $entries->map(function ($entry) {
            return [
                'id' => $entry->id,
                'title' => $entry->parsed_title ?? $entry->raw_text,
                'description' => $entry->parsed_description,
                'parsed_start_datetime' => $entry->parsed_start_datetime,
                'parsed_end_datetime' => $entry->parsed_end_datetime,
                'location' => $entry->parsed_location,
                'priority' => $entry->parsed_priority,
                'category' => $entry->ai_detected_category,
                'keywords' => $entry->detected_keywords,
                'importance' => $entry->ai_detected_importance
            ];
        })->toArray();
    }

    /**
     * Get user preferences
     */
    protected function getUserPreferences($userId, array $requestPreferences): array
    {
        $userPrefs = \App\Models\UserSchedulePreference::where('user_id', $userId)->first();

        $defaults = [
            'work_start' => '08:00',
            'work_end' => '18:00',
            'break_duration' => 60,
            'lunch_time' => '12:00',
            'focus_hours' => ['09:00-11:00', '14:00-16:00'],
            'constraints' => []
        ];

        if ($userPrefs) {
            $defaults = array_merge($defaults, [
                'work_start' => $userPrefs->work_start_time ?? $defaults['work_start'],
                'work_end' => $userPrefs->work_end_time ?? $defaults['work_end'],
                'break_duration' => $userPrefs->break_duration ?? $defaults['break_duration'],
                'focus_hours' => $userPrefs->focus_hours ?? $defaults['focus_hours'],
                'constraints' => $userPrefs->custom_rules ?? []
            ]);
        }

        return array_merge($defaults, $requestPreferences);
    }

    /**
     * Calculate end date based on analysis type
     */
    protected function calculateEndDate($startDate, $analysisType): string
    {
        $date = Carbon::parse($startDate);

        return match($analysisType) {
            'weekly' => $date->addWeek()->format('Y-m-d'),
            'monthly' => $date->addMonth()->format('Y-m-d'),
            default => $date->format('Y-m-d')
        };
    }

    /**
     * Calculate confidence score
     */
    protected function calculateConfidenceScore($schedule): float
    {
        if (!isset($schedule['optimization_summary'])) {
            return 0.5;
        }

        $summary = $schedule['optimization_summary'];
        $score = 0.5; // Base score

        // Add points based on utilization rate
        if (isset($summary['utilization_rate'])) {
            $score += min($summary['utilization_rate'] * 0.3, 0.3);
        }

        // Add points based on priority coverage
        if (isset($summary['high_priority_coverage'])) {
            $score += min($summary['high_priority_coverage'] * 0.2, 0.2);
        }

        return min($score, 1.0);
    }

    /**
     * Calculate API cost
     */
    protected function calculateApiCost($tokenUsage): float
    {
        if (empty($tokenUsage)) {
            return 0.0;
        }

        // GPT-4o-mini pricing (approximate)
        $inputCostPer1K = 0.00015;
        $outputCostPer1K = 0.0006;

        $inputCost = ($tokenUsage['prompt_tokens'] ?? 0) / 1000 * $inputCostPer1K;
        $outputCost = ($tokenUsage['completion_tokens'] ?? 0) / 1000 * $outputCostPer1K;

        return round($inputCost + $outputCost, 4);
    }

    /**
     * Create events from schedule slots
     */
    protected function createEventsFromSlots(AiScheduleAnalysis $analysis): void
    {
        foreach ($analysis->scheduleSlots as $slot) {
            if (!$slot->event_id) {
                $slot->createEvent();
            }
        }
    }

    /**
     * Schedule notifications for slots
     */
    protected function scheduleNotifications(AiScheduleAnalysis $analysis): void
    {
        foreach ($analysis->scheduleSlots as $slot) {
            if ($slot->reminder_minutes_before) {
                $triggerTime = $slot->start_datetime->copy()
                    ->subMinutes($slot->reminder_minutes_before);

                SmartNotification::create([
                    'user_id' => $slot->user_id,
                    'event_id' => $slot->event_id,
                    'type' => 'reminder',
                    'subtype' => 'ai_scheduled',
                    'trigger_datetime' => $triggerTime,
                    'scheduled_at' => $triggerTime,
                    'title' => "Nhắc nhở: {$slot->task_title}",
                    'message' => "Bạn có lịch '{$slot->task_title}' vào lúc {$slot->start_time}",
                    'action_data' => [
                        'slot_id' => $slot->id,
                        'analysis_id' => $analysis->id
                    ],
                    'ai_generated' => true,
                    'priority_level' => $this->getPriorityLevel($slot->priority)
                ]);
            }
        }
    }

    /**
     * Get priority level for notification
     */
    protected function getPriorityLevel($priority): int
    {
        return match($priority) {
            'critical' => 1,
            'high' => 2,
            'medium' => 3,
            'low' => 4,
            default => 3
        };
    }
}