<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleImportRequest;
use App\Http\Resources\RawScheduleImportResource;
use App\Http\Resources\RawScheduleEntryResource;
use App\Models\RawScheduleImport;
use App\Models\RawScheduleEntry;
use App\Models\UserSchedulePreference;
use App\Services\ScheduleParsingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ScheduleImportController extends Controller
{
    protected $parsingService;

    public function __construct(ScheduleParsingService $parsingService)
    {
        $this->parsingService = $parsingService;
    }

    /**
     * Display a listing of user's imports
     * GET /api/v1/schedule-imports
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = RawScheduleImport::where('user_id', Auth::id())
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
        ]);

        DB::beginTransaction();
        try {
            $user = Auth::user();
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
    public function show($id): JsonResponse
    {
        try {
            $import = RawScheduleImport::where('user_id', Auth::id())
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
            $import = RawScheduleImport::where('user_id', Auth::id())->findOrFail($id);
            
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
            $import = RawScheduleImport::where('user_id', Auth::id())->findOrFail($id);

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
            $import = RawScheduleImport::where('user_id', Auth::id())->findOrFail($id);
            
            // Get entries to convert
            $query = RawScheduleEntry::where('import_id', $import->id)
                ->where('processing_status', 'parsed')
                ->whereIn('conversion_status', ['pending', 'failed']);

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
            $entry = RawScheduleEntry::where('user_id', Auth::id())->findOrFail($id);

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
    public function destroy($id): JsonResponse
    {
        try {
            $import = RawScheduleImport::where('user_id', Auth::id())->findOrFail($id);

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
    public function statistics(): JsonResponse
    {
        try {
            $userId = Auth::id();

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