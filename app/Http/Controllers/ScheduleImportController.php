<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleImportRequest;
use App\Http\Resources\RawScheduleImportResource;
use App\Http\Resources\RawScheduleEntryResource;
use App\Models\RawScheduleImport;
use App\Models\RawScheduleEntry;
use App\Models\UserSchedulePreference;
use App\Services\ScheduleParsingService;
use App\Services\CsvExportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ScheduleImportController extends Controller
{
    protected $parsingService;
    protected $csvExportService;

    public function __construct(ScheduleParsingService $parsingService, CsvExportService $csvExportService)
    {
        $this->parsingService = $parsingService;
        $this->csvExportService = $csvExportService;
    }

    /**
     * Display a listing of user's imports
     * GET /api/v1/schedule-imports
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id() ?? $request->get('user_id', 1);
            $query = RawScheduleImport::where('user_id', $userId)
                ->with(['entries' => function($q) {
                    $q->select('id', 'import_id', 'processing_status', 'conversion_status');
                }]);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by import type
            if ($request->has('import_type')) {
                $query->where('import_type', $request->import_type);
            }

            // Filter by date range
            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $imports = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => RawScheduleImportResource::collection($imports),
                'meta' => [
                    'current_page' => $imports->currentPage(),
                    'last_page' => $imports->lastPage(),
                    'per_page' => $imports->perPage(),
                    'total' => $imports->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch imports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new schedule import
     * POST /api/v1/schedule-imports
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'import_type' => 'required|in:file_upload,manual_input,text_parsing,calendar_sync',
            'source_type' => 'required|in:csv,excel,txt,json,ics,manual',
            'file' => 'required_if:import_type,file_upload|file|max:10240',
            'raw_content' => 'required_if:import_type,manual_input,text_parsing|string',
            'template_id' => 'nullable|exists:schedule_templates,id',
            'user_id' => 'nullable|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            // If no authenticated user, use provided user_id or default user
            if (!$user) {
                if ($request->has('user_id')) {
                    $user = \App\Models\User::find($request->user_id);
                } else {
                    // Get or create default user
                    $user = \App\Models\User::firstOrCreate(
                        ['email' => 'default@example.com'],
                        [
                            'name' => 'Default User',
                            'password' => bcrypt(uniqid()),
                            'is_active' => true
                        ]
                    );
                }
            }
            
            $preferences = UserSchedulePreference::getOrCreateForUser($user);

            // Create import record
            $import = new RawScheduleImport();
            $import->user_id = $user->id;
            $import->import_type = $request->import_type;
            $import->source_type = $request->source_type;
            $import->status = 'pending';

            // Handle file upload
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $import->original_filename = $file->getClientOriginalName();
                $import->file_size_bytes = $file->getSize();
                $import->mime_type = $file->getMimeType();
                
                // Store file
                $path = $file->store('schedule-imports/' . $user->id, 'private');
                $import->file_path = $path;
                
                // Read file content based on type
                $content = $this->readFileContent($file, $request->source_type);
                $import->raw_content = $content;
            } else {
                // Manual input or text parsing
                $import->raw_content = $request->raw_content;
            }

            $import->save();

            // Start initial parsing
            $this->parsingService->parseImport($import, $request->template_id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Schedule import created successfully',
                'data' => new RawScheduleImportResource($import->fresh())
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create import',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified import
     * GET /api/v1/schedule-imports/{id}
     */
    public function show($id, Request $request): JsonResponse
    {
        try {
            $userId = Auth::id() ?? $request->get('user_id', 1);
            $import = RawScheduleImport::where('user_id', $userId)
                ->with(['entries', 'user'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new RawScheduleImportResource($import)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get entries for an import
     * GET /api/v1/schedule-imports/{id}/entries
     */
    public function entries($id, Request $request): JsonResponse
    {
        try {
            $userId = Auth::id() ?? $request->get('user_id', 1);
            $import = RawScheduleImport::where('user_id', $userId)->findOrFail($id);
            
            $query = RawScheduleEntry::where('import_id', $import->id);

            // Filter by processing status
            if ($request->has('processing_status')) {
                $query->where('processing_status', $request->processing_status);
            }

            // Filter by conversion status
            if ($request->has('conversion_status')) {
                $query->where('conversion_status', $request->conversion_status);
            }

            // Filter by manual review
            if ($request->has('manual_review_required')) {
                $query->where('manual_review_required', $request->boolean('manual_review_required'));
            }

            // Filter by confidence threshold
            if ($request->has('min_confidence')) {
                $query->where('ai_confidence', '>=', $request->min_confidence);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'row_number');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 20);
            $entries = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => RawScheduleEntryResource::collection($entries),
                'meta' => [
                    'current_page' => $entries->currentPage(),
                    'last_page' => $entries->lastPage(),
                    'per_page' => $entries->perPage(),
                    'total' => $entries->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch entries',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process/reprocess an import
     * POST /api/v1/schedule-imports/{id}/process
     */
    public function process($id, Request $request): JsonResponse
    {
        try {
            $userId = Auth::id() ?? $request->get('user_id', 1);
            $import = RawScheduleImport::where('user_id', $userId)->findOrFail($id);

            // Check if already processing
            if ($import->status === 'processing') {
                return response()->json([
                    'success' => false,
                    'message' => 'Import is already being processed'
                ], 400);
            }

            // Start processing
            $import->markAsProcessing();
            
            // Queue processing job
            $this->parsingService->processImport($import, $request->get('template_id'));

            return response()->json([
                'success' => true,
                'message' => 'Import processing started',
                'data' => new RawScheduleImportResource($import->fresh())
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process import',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert entries to events
     * POST /api/v1/schedule-imports/{id}/convert
     */
    public function convert($id, Request $request): JsonResponse
    {
        $request->validate([
            'entry_ids' => 'nullable|array',
            'entry_ids.*' => 'integer|exists:raw_schedule_entries,id',
            'min_confidence' => 'nullable|numeric|min:0|max:1',
        ]);

        try {
            $userId = Auth::id() ?? $request->get('user_id', 1);
            $import = RawScheduleImport::where('user_id', $userId)->findOrFail($id);
            
            // Get entries to convert
            $query = RawScheduleEntry::where('import_id', $import->id)
                ->whereIn('processing_status', ['pending', 'parsed'])
                ->whereIn('conversion_status', ['pending', 'failed'])
                ->whereNotNull('parsed_title')
                ->whereNotNull('parsed_start_datetime');

            // Filter by specific entries
            if ($request->has('entry_ids')) {
                $query->whereIn('id', $request->entry_ids);
            }

            // Filter by confidence threshold
            $minConfidence = $request->get('min_confidence', 0.7);
            $query->where('ai_confidence', '>=', $minConfidence);

            $entries = $query->get();

            if ($entries->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No entries available for conversion'
                ], 400);
            }

            // Convert entries to events
            $results = $this->parsingService->convertEntriesToEvents($entries);

            return response()->json([
                'success' => true,
                'message' => 'Conversion completed',
                'data' => [
                    'total_processed' => $results['total'],
                    'successfully_converted' => $results['success'],
                    'failed_conversions' => $results['failed'],
                    'manual_review_required' => $results['manual_review'],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to convert entries',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update entry for manual review
     * PATCH /api/v1/schedule-imports/entries/{id}
     */
    public function updateEntry($id, Request $request): JsonResponse
    {
        $request->validate([
            'parsed_title' => 'nullable|string|max:255',
            'parsed_description' => 'nullable|string',
            'parsed_start_datetime' => 'nullable|date',
            'parsed_end_datetime' => 'nullable|date|after:parsed_start_datetime',
            'parsed_location' => 'nullable|string|max:255',
            'parsed_priority' => 'nullable|integer|min:1|max:5',
            'manual_review_notes' => 'nullable|string',
            'manual_review_required' => 'nullable|boolean',
        ]);

        try {
            $userId = Auth::id() ?? $request->get('user_id', 1);
            $entry = RawScheduleEntry::where('user_id', $userId)->findOrFail($id);

            // Update parsed data
            $entry->fill($request->only([
                'parsed_title',
                'parsed_description',
                'parsed_start_datetime',
                'parsed_end_datetime',
                'parsed_location',
                'parsed_priority',
                'manual_review_notes',
                'manual_review_required'
            ]));

            // Mark as manually reviewed if changed
            if ($request->has('manual_review_required') && !$request->manual_review_required) {
                $entry->conversion_status = 'pending';
            }

            $entry->save();

            return response()->json([
                'success' => true,
                'message' => 'Entry updated successfully',
                'data' => new RawScheduleEntryResource($entry)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an import and its entries
     * DELETE /api/v1/schedule-imports/{id}
     */
    public function destroy($id, Request $request): JsonResponse
    {
        try {
            $userId = Auth::id() ?? $request->get('user_id', 1);
            $import = RawScheduleImport::where('user_id', $userId)->findOrFail($id);

            // Delete file if exists
            if ($import->file_path && Storage::disk('private')->exists($import->file_path)) {
                Storage::disk('private')->delete($import->file_path);
            }

            // Delete import (entries will cascade delete)
            $import->delete();

            return response()->json([
                'success' => true,
                'message' => 'Import deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete import',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get import statistics
     * GET /api/v1/schedule-imports/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id() ?? $request->get('user_id', 1);

            $stats = [
                'total_imports' => RawScheduleImport::where('user_id', $userId)->count(),
                'pending_imports' => RawScheduleImport::where('user_id', $userId)->pending()->count(),
                'completed_imports' => RawScheduleImport::where('user_id', $userId)->completed()->count(),
                'failed_imports' => RawScheduleImport::where('user_id', $userId)->failed()->count(),
                'total_entries' => RawScheduleEntry::where('user_id', $userId)->count(),
                'converted_entries' => RawScheduleEntry::where('user_id', $userId)->converted()->count(),
                'pending_review' => RawScheduleEntry::where('user_id', $userId)->requiresManualReview()->count(),
                'average_confidence' => RawScheduleEntry::where('user_id', $userId)
                    ->whereNotNull('ai_confidence')
                    ->avg('ai_confidence'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export import data as CSV
     * GET /api/v1/schedule-imports/{id}/export
     */
    public function exportCsv($id, Request $request)
    {
        try {
            $userId = Auth::id() ?? $request->get('user_id', 1);
            $import = RawScheduleImport::where('user_id', $userId)->findOrFail($id);
            
            $format = $request->get('format', 'original');
            $filename = $request->get('filename', 'schedule_export_' . date('Y-m-d_His') . '.csv');
            
            $csv = $this->csvExportService->exportImportData($import, $format);
            
            return $this->csvExportService->createCsvResponse($csv, $filename);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export CSV',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export multiple imports as CSV
     * POST /api/v1/schedule-imports/export-batch
     */
    public function exportBatchCsv(Request $request)
    {
        $request->validate([
            'import_ids' => 'required|array',
            'import_ids.*' => 'integer|exists:raw_schedule_imports,id',
            'format' => 'nullable|in:original,parsed,standard,ai_enhanced,vietnamese_school'
        ]);

        try {
            $userId = Auth::id() ?? $request->get('user_id', 1);
            $imports = RawScheduleImport::where('user_id', $userId)
                ->whereIn('id', $request->import_ids)
                ->get();

            if ($imports->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No imports found'
                ], 404);
            }

            $format = $request->get('format', 'standard');
            $allEntries = collect();

            foreach ($imports as $import) {
                $allEntries = $allEntries->concat($import->entries);
            }

            $csv = $this->csvExportService->exportImportData($imports->first(), $format);
            $filename = 'batch_export_' . date('Y-m-d_His') . '.csv';

            return $this->csvExportService->createCsvResponse($csv, $filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export batch CSV',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export converted events as CSV
     * GET /api/v1/schedule-imports/{id}/export-events
     */
    public function exportEventsCsv($id, Request $request)
    {
        try {
            $userId = Auth::id() ?? $request->get('user_id', 1);
            $import = RawScheduleImport::where('user_id', $userId)->findOrFail($id);
            
            // Get all converted events from this import
            $eventIds = RawScheduleEntry::where('import_id', $import->id)
                ->whereNotNull('converted_event_id')
                ->pluck('converted_event_id');
            
            if ($eventIds->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No converted events found for this import'
                ], 404);
            }
            
            $events = \App\Models\Event::whereIn('id', $eventIds)->get();
            
            $format = $request->get('format', 'standard');
            $filename = 'events_export_' . date('Y-m-d_His') . '.csv';
            
            $csv = $this->csvExportService->exportEvents($events, $format);
            
            return $this->csvExportService->createCsvResponse($csv, $filename);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export events CSV',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get CSV export preview (JSON format)
     * GET /api/v1/schedule-imports/{id}/preview
     */
    public function previewExport($id, Request $request): JsonResponse
    {
        try {
            $userId = Auth::id() ?? $request->get('user_id', 1);
            $import = RawScheduleImport::where('user_id', $userId)->findOrFail($id);
            
            $format = $request->get('format', 'original');
            $limit = $request->get('limit', 5);
            
            $entries = $import->entries()->limit($limit)->get();
            
            $preview = [];
            foreach ($entries as $entry) {
                $preview[] = [
                    'row_number' => $entry->row_number,
                    'original_data' => $entry->original_data,
                    'parsed_data' => [
                        'title' => $entry->parsed_title,
                        'description' => $entry->parsed_description,
                        'start_datetime' => $entry->parsed_start_datetime,
                        'end_datetime' => $entry->parsed_end_datetime,
                        'location' => $entry->parsed_location,
                        'priority' => $entry->parsed_priority
                    ],
                    'ai_confidence' => $entry->ai_confidence
                ];
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Export preview retrieved',
                'data' => [
                    'format' => $format,
                    'total_entries' => $import->entries()->count(),
                    'preview_count' => count($preview),
                    'available_formats' => [
                        'original' => 'Original format as imported',
                        'parsed' => 'Parsed and processed data',
                        'standard' => 'Standard calendar format',
                        'ai_enhanced' => 'With AI suggestions and analysis',
                        'vietnamese_school' => 'Vietnamese school schedule format'
                    ],
                    'entries' => $preview
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate preview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Read file content based on type
     */
    protected function readFileContent($file, $sourceType): string
    {
        $content = '';
        
        switch ($sourceType) {
            case 'csv':
            case 'txt':
                $content = file_get_contents($file->getRealPath());
                break;
            case 'excel':
                // Use appropriate Excel reader library
                // This is a placeholder
                $content = file_get_contents($file->getRealPath());
                break;
            case 'json':
                $content = file_get_contents($file->getRealPath());
                break;
            case 'ics':
                // Use appropriate iCal parser
                $content = file_get_contents($file->getRealPath());
                break;
        }

        return $content;
    }
}