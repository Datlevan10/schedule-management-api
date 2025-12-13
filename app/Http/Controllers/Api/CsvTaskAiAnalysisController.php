<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawScheduleEntry;
use App\Models\RawScheduleImport;
use App\Models\AiScheduleAnalysis;
use App\Services\OpenAIScheduleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CsvTaskAiAnalysisController extends Controller
{
    protected $openAIService;

    public function __construct(OpenAIScheduleService $openAIService)
    {
        $this->openAIService = $openAIService;
    }

    /**
     * Analyze CSV imported tasks with AI
     * POST /api/v1/csv-tasks/analyze
     * 
     * This endpoint takes selected CSV tasks and sends them for AI analysis
     */
    public function analyzeCsvTasks(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'entry_ids' => 'required|array|min:1',
            'entry_ids.*' => 'required|integer|exists:raw_schedule_entries,id',
            'analysis_type' => 'nullable|in:parse,optimize,both',
            'target_date' => 'nullable|date',
            'preferences' => 'nullable|array',
        ]);

        $userId = $request->user_id;
        $entryIds = $request->entry_ids;
        $analysisType = $request->get('analysis_type', 'both');
        $targetDate = $request->get('target_date', Carbon::now()->toDateString());
        $batchId = Str::uuid()->toString();

        DB::beginTransaction();
        try {
            // 1. Fetch and validate entries
            $entries = RawScheduleEntry::where('user_id', $userId)
                ->whereIn('id', $entryIds)
                ->get();

            if ($entries->count() !== count($entryIds)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Some entries not found or not authorized'
                ], 404);
            }

            // 2. Check if entries are available for analysis
            $lockedEntries = $entries->filter(function ($entry) {
                return !$entry->isAvailableForAiAnalysis();
            });

            if ($lockedEntries->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Some tasks are already being analyzed or completed',
                    'locked_entries' => $lockedEntries->pluck('id')
                ], 409);
            }

            // 3. Mark entries as being analyzed
            foreach ($entries as $entry) {
                $entry->markAsAiAnalysisInProgress($batchId);
            }

            // 4. Prepare data for AI analysis
            $tasksForAi = $this->prepareTasksForAi($entries);

            // 5. Create AI analysis record
            $aiAnalysis = AiScheduleAnalysis::create([
                'user_id' => $userId,
                'import_id' => $entries->first()->import_id,
                'input_data' => $tasksForAi,
                'analysis_type' => $analysisType === 'both' ? 'daily' : $analysisType,
                'target_date' => $targetDate,
                'status' => 'processing',
                'ai_model' => 'gpt-4o-mini',
                'user_preferences' => $request->preferences,
                'optimized_schedule' => [], // Initialize with empty array
                'ai_reasoning' => 'Processing...', // Default reasoning
            ]);

            DB::commit();

            // 6. Send to AI for analysis (async or sync based on your setup)
            $this->processAiAnalysis($aiAnalysis, $entries, $analysisType);

            return response()->json([
                'status' => 'success',
                'message' => 'Tasks submitted for AI analysis',
                'data' => [
                    'analysis_id' => $aiAnalysis->id,
                    'batch_id' => $batchId,
                    'tasks_count' => $entries->count(),
                    'analysis_type' => $analysisType,
                    'estimated_completion' => Carbon::now()->addSeconds(30)->toIso8601String(),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CSV AI Analysis Error', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'entry_ids' => $entryIds
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to analyze tasks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get AI analysis results for CSV tasks
     * GET /api/v1/csv-tasks/analysis-results/{analysisId}
     */
    public function getAnalysisResults($analysisId): JsonResponse
    {
        $analysis = AiScheduleAnalysis::with(['user'])->findOrFail($analysisId);

        // Get the entries that were analyzed
        $entries = RawScheduleEntry::where('ai_analysis_id', $analysis->id)
            ->orWhere('ai_analysis_id', 'LIKE', '%' . $analysis->id . '%')
            ->get();

        $response = [
            'status' => 'success',
            'data' => [
                'analysis_id' => $analysis->id,
                'status' => $analysis->status,
                'created_at' => $analysis->created_at,
                'completed_at' => $analysis->updated_at,
                'tasks_analyzed' => $entries->count(),
                'results' => []
            ]
        ];

        if ($analysis->status === 'completed') {
            $response['data']['results'] = [
                'parsed_tasks' => $this->formatParsedResults($analysis, $entries),
                'optimized_schedule' => $analysis->optimized_schedule,
                'ai_reasoning' => $analysis->ai_reasoning,
                'confidence_score' => $analysis->confidence_score,
                'optimization_metrics' => $analysis->optimization_metrics,
            ];
        } elseif ($analysis->status === 'failed') {
            $response['data']['error'] = $analysis->error_details;
        }

        return response()->json($response);
    }

    /**
     * Parse Vietnamese schedule text using AI
     * POST /api/v1/csv-tasks/parse-vietnamese
     */
    public function parseVietnameseSchedule(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'entries' => 'required|array',
            'entries.*.id' => 'required|integer',
            'entries.*.original_data' => 'required|array',
        ]);

        try {
            $parsedEntries = [];

            foreach ($request->entries as $entryData) {
                $entry = RawScheduleEntry::find($entryData['id']);
                if (!$entry) continue;

                // Parse Vietnamese time format
                $parsedData = $this->parseVietnameseEntry($entryData['original_data']);
                
                // Update entry with parsed data
                $entry->update([
                    'parsed_title' => $parsedData['title'],
                    'parsed_description' => $parsedData['description'],
                    'parsed_start_datetime' => $parsedData['start_datetime'],
                    'parsed_end_datetime' => $parsedData['end_datetime'],
                    'parsed_location' => $parsedData['location'],
                    'parsed_priority' => $parsedData['priority'],
                    'ai_confidence' => $parsedData['confidence'],
                    'processing_status' => 'parsed',
                ]);

                $parsedEntries[] = [
                    'id' => $entry->id,
                    'parsed_data' => $parsedData,
                    'original_data' => $entryData['original_data'],
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Vietnamese schedule parsed successfully',
                'data' => $parsedEntries
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to parse Vietnamese schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch analyze multiple CSV imports
     * POST /api/v1/csv-tasks/batch-analyze
     */
    public function batchAnalyzeImports(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'import_ids' => 'required|array',
            'import_ids.*' => 'required|integer|exists:raw_schedule_imports,id',
            'analysis_options' => 'nullable|array',
        ]);

        $userId = $request->user_id;
        $importIds = $request->import_ids;
        $results = [];

        foreach ($importIds as $importId) {
            // Get all entries for this import
            $entries = RawScheduleEntry::where('user_id', $userId)
                ->where('import_id', $importId)
                ->availableForAiAnalysis()
                ->get();

            if ($entries->isEmpty()) {
                $results[] = [
                    'import_id' => $importId,
                    'status' => 'skipped',
                    'reason' => 'No available entries for analysis'
                ];
                continue;
            }

            // Submit for analysis
            $analysisRequest = new Request([
                'user_id' => $userId,
                'entry_ids' => $entries->pluck('id')->toArray(),
                'analysis_type' => 'both',
                'preferences' => $request->analysis_options,
            ]);

            $analysisResult = $this->analyzeCsvTasks($analysisRequest);
            $resultData = json_decode($analysisResult->getContent(), true);

            $results[] = [
                'import_id' => $importId,
                'status' => $resultData['status'],
                'analysis_id' => $resultData['data']['analysis_id'] ?? null,
                'tasks_count' => $entries->count(),
            ];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Batch analysis submitted',
            'data' => [
                'total_imports' => count($importIds),
                'results' => $results
            ]
        ]);
    }

    /**
     * Get analysis status for CSV tasks
     * GET /api/v1/csv-tasks/analysis-status
     */
    public function getAnalysisStatus(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'import_id' => 'nullable|integer|exists:raw_schedule_imports,id',
        ]);

        $userId = $request->user_id;

        $query = RawScheduleEntry::where('user_id', $userId)
            ->when($request->has('import_id'), function ($q) use ($request) {
                $q->where('import_id', $request->import_id);
            });

        $stats = [
            'total' => $query->count(),
            'pending' => (clone $query)->where('ai_analysis_status', 'pending')->count(),
            'in_progress' => (clone $query)->where('ai_analysis_status', 'in_progress')->count(),
            'completed' => (clone $query)->where('ai_analysis_status', 'completed')->count(),
            'failed' => (clone $query)->where('ai_analysis_status', 'failed')->count(),
            'available_for_analysis' => (clone $query)->availableForAiAnalysis()->count(),
        ];

        // Get recent analyses
        $recentAnalyses = AiScheduleAnalysis::where('user_id', $userId)
            ->when($request->has('import_id'), function ($q) use ($request) {
                $q->where('import_id', $request->import_id);
            })
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'status', 'analysis_type', 'created_at', 'confidence_score']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'statistics' => $stats,
                'recent_analyses' => $recentAnalyses,
                'last_updated' => Carbon::now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Prepare tasks for AI analysis
     */
    private function prepareTasksForAi($entries): array
    {
        return $entries->map(function ($entry) {
            return [
                'id' => $entry->id,
                'raw_text' => $entry->raw_text,
                'original_data' => $entry->original_data,
                'parsed_data' => [
                    'title' => $entry->parsed_title ?? $entry->original_data['mon_hoc'] ?? '',
                    'description' => $entry->parsed_description ?? $entry->original_data['ghi_chu'] ?? '',
                    'date' => $entry->original_data['ngay'] ?? '',
                    'start_time' => $entry->original_data['gio_bat_dau'] ?? '',
                    'end_time' => $entry->original_data['gio_ket_thuc'] ?? '',
                    'location' => $entry->original_data['phong'] ?? '',
                    'class' => $entry->original_data['lop'] ?? '',
                ],
                'current_confidence' => $entry->ai_confidence,
            ];
        })->toArray();
    }

    /**
     * Process AI analysis
     */
    private function processAiAnalysis($aiAnalysis, $entries, $analysisType): void
    {
        // This would typically be queued
        try {
            // Simulate AI processing (replace with actual OpenAI call)
            $aiResponse = $this->openAIService->analyzeScheduleTasks(
                $aiAnalysis->input_data,
                $analysisType
            );

            // Update analysis record
            $aiAnalysis->update([
                'status' => 'completed',
                'ai_response' => $aiResponse,
                'optimized_schedule' => $aiResponse['optimized_schedule'] ?? [],
                'ai_reasoning' => $aiResponse['reasoning'] ?? '',
                'confidence_score' => $aiResponse['confidence'] ?? 0.85,
                'optimization_metrics' => $aiResponse['metrics'] ?? [],
                'processing_time_ms' => 3000,
            ]);

            // Update entries with results
            foreach ($entries as $entry) {
                $entryResult = collect($aiResponse['parsed_tasks'] ?? [])
                    ->firstWhere('id', $entry->id);

                if ($entryResult) {
                    $entry->markAsAiAnalyzed([
                        'parsed_data' => $entryResult,
                        'optimization' => $aiResponse['optimization'] ?? [],
                    ]);

                    // Update parsed fields if parsing was improved
                    if ($analysisType === 'parse' || $analysisType === 'both') {
                        $entry->update([
                            'parsed_title' => $entryResult['title'] ?? $entry->parsed_title,
                            'parsed_description' => $entryResult['description'] ?? $entry->parsed_description,
                            'parsed_start_datetime' => $entryResult['start_datetime'] ?? $entry->parsed_start_datetime,
                            'parsed_end_datetime' => $entryResult['end_datetime'] ?? $entry->parsed_end_datetime,
                            'parsed_location' => $entryResult['location'] ?? $entry->parsed_location,
                            'parsed_priority' => $entryResult['priority'] ?? $entry->parsed_priority,
                            'ai_confidence' => $entryResult['confidence'] ?? 0.85,
                        ]);
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('AI Processing Error', ['error' => $e->getMessage()]);
            
            $aiAnalysis->update([
                'status' => 'failed',
                'error_details' => ['error' => $e->getMessage()],
            ]);

            foreach ($entries as $entry) {
                $entry->markAiAnalysisFailed($e->getMessage());
            }
        }
    }

    /**
     * Parse Vietnamese entry data
     */
    private function parseVietnameseEntry(array $data): array
    {
        // Parse Vietnamese date format (e.g., "9/12/2025")
        $date = Carbon::createFromFormat('j/n/Y', $data['ngay'] ?? '');
        
        // Parse Vietnamese time format (e.g., "7 giờ", "7 giờ 45 phút")
        $startTime = $this->parseVietnameseTime($data['gio_bat_dau'] ?? '');
        $endTime = $this->parseVietnameseTime($data['gio_ket_thuc'] ?? '');

        $startDateTime = $date->copy()->setTimeFromTimeString($startTime);
        $endDateTime = $date->copy()->setTimeFromTimeString($endTime);

        // Determine priority based on keywords
        $priority = 3; // default
        if (str_contains(strtolower($data['ghi_chu'] ?? ''), 'quan trọng') ||
            str_contains(strtolower($data['mon_hoc'] ?? ''), 'thi')) {
            $priority = 4;
        }

        return [
            'title' => $data['mon_hoc'] ?? 'Untitled',
            'description' => $data['ghi_chu'] ?? '',
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'location' => $data['phong'] ?? '',
            'priority' => $priority,
            'confidence' => 0.9,
            'class' => $data['lop'] ?? '',
        ];
    }

    /**
     * Parse Vietnamese time string to 24h format
     */
    private function parseVietnameseTime(string $timeStr): string
    {
        // Remove extra spaces and convert to lowercase
        $timeStr = trim(strtolower($timeStr));
        
        // Extract hours and minutes
        preg_match('/(\d+)\s*giờ\s*(\d*)\s*phút?/', $timeStr, $matches);
        
        $hours = $matches[1] ?? 0;
        $minutes = $matches[2] ?? 0;
        
        return sprintf('%02d:%02d:00', $hours, $minutes);
    }

    /**
     * Format parsed results for response
     */
    private function formatParsedResults($analysis, $entries): array
    {
        return $entries->map(function ($entry) {
            return [
                'id' => $entry->id,
                'original' => $entry->original_data,
                'parsed' => [
                    'title' => $entry->parsed_title,
                    'description' => $entry->parsed_description,
                    'start_datetime' => $entry->parsed_start_datetime,
                    'end_datetime' => $entry->parsed_end_datetime,
                    'location' => $entry->parsed_location,
                    'priority' => $entry->parsed_priority,
                ],
                'ai_confidence' => $entry->ai_confidence,
                'analysis_status' => $entry->ai_analysis_status,
            ];
        })->toArray();
    }
}