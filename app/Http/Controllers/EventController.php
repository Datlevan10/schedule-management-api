<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\TaskTemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $events = Event::with('category')->paginate(10);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Events retrieved successfully',
            'data' => $events
        ]);
    }

    /**
     * Get all events without pagination.
     */
    public function getAll(): JsonResponse
    {
        $events = Event::with('category')->get();
        
        return response()->json([
            'status' => 'success',
            'message' => 'All events retrieved successfully',
            'total' => $events->count(),
            'data' => $events
        ]);
    }

    /**
     * Get events by status.
     */
    public function getByStatus(Request $request): JsonResponse
    {
        $status = $request->query('status', 'active');
        $events = Event::with('category')->where('status', $status)->get();
        
        return response()->json([
            'status' => 'success',
            'message' => "Events with status '{$status}' retrieved successfully",
            'total' => $events->count(),
            'data' => $events
        ]);
    }

    /**
     * Get upcoming events.
     */
    public function getUpcoming(): JsonResponse
    {
        $events = Event::with('category')
            ->where('start_datetime', '>', now())
            ->orderBy('start_datetime', 'asc')
            ->get();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Upcoming events retrieved successfully',
            'total' => $events->count(),
            'data' => $events
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|in:scheduled,in_progress,completed,cancelled,postponed',
            'event_category_id' => 'nullable|exists:event_categories,id',
            'user_id' => 'nullable|exists:users,id',
            'priority' => 'nullable|integer|min:1|max:5',
            'event_metadata' => 'nullable|array',
            'participants' => 'nullable|array',
            'requirements' => 'nullable|array',
            'preparation_items' => 'nullable|array',
            'completion_percentage' => 'nullable|integer|min:0|max:100',
            'recurring_pattern' => 'nullable|array',
            'parent_event_id' => 'nullable|exists:events,id'
        ]);

        $event = Event::create($validated);
        $event->load('category');

        return response()->json([
            'status' => 'success',
            'message' => 'Event created successfully',
            'data' => $event
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event): JsonResponse
    {
        $event->load('category');
        
        return response()->json([
            'status' => 'success',
            'message' => 'Event retrieved successfully',
            'data' => $event
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'start_datetime' => 'sometimes|required|date',
            'end_datetime' => 'sometimes|required|date|after:start_datetime',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|in:scheduled,in_progress,completed,cancelled,postponed',
            'event_category_id' => 'nullable|exists:event_categories,id',
            'user_id' => 'nullable|exists:users,id',
            'priority' => 'nullable|integer|min:1|max:5',
            'event_metadata' => 'nullable|array',
            'participants' => 'nullable|array',
            'requirements' => 'nullable|array',
            'preparation_items' => 'nullable|array',
            'completion_percentage' => 'nullable|integer|min:0|max:100',
            'recurring_pattern' => 'nullable|array',
            'parent_event_id' => 'nullable|exists:events,id'
        ]);

        $event->update($validated);
        $event->load('category');

        return response()->json([
            'status' => 'success',
            'message' => 'Event updated successfully',
            'data' => $event
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event): JsonResponse
    {
        $event->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Event deleted successfully',
            'data' => null
        ]);
    }

    /**
     * Search events.
     */
    public function search(Request $request): JsonResponse
    {
        $query = Event::with('category');

        if ($request->has('keyword')) {
            $keyword = $request->query('keyword');
            $query->where(function($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('description', 'like', "%{$keyword}%")
                  ->orWhere('location', 'like', "%{$keyword}%");
            });
        }

        if ($request->has('date_from')) {
            $query->where('start_datetime', '>=', $request->query('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('end_datetime', '<=', $request->query('date_to'));
        }

        if ($request->has('category_id')) {
            $query->where('event_category_id', $request->query('category_id'));
        }

        $events = $query->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Search results retrieved successfully',
            'total' => $events->count(),
            'data' => $events
        ]);
    }

    /**
     * Get all events/tasks for a specific user
     * GET /api/v1/events/user/{userId}
     */
    public function getUserEvents(Request $request, $userId): JsonResponse
    {
        try {
            $query = Event::with(['category', 'user'])
                ->where('user_id', $userId);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->has('date_from')) {
                $query->where('start_datetime', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->where('end_datetime', '<=', $request->date_to);
            }

            // Filter upcoming events only
            if ($request->has('upcoming') && $request->boolean('upcoming')) {
                $query->where('start_datetime', '>', now());
            }

            // Filter by completion percentage
            if ($request->has('completion_min')) {
                $query->where('completion_percentage', '>=', $request->completion_min);
            }
            if ($request->has('completion_max')) {
                $query->where('completion_percentage', '<=', $request->completion_max);
            }

            // Filter by priority
            if ($request->has('priority')) {
                $query->where('priority', $request->priority);
            }
            if ($request->has('priority_min')) {
                $query->where('priority', '>=', $request->priority_min);
            }

            // Search in title and description
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filter manually created tasks
            if ($request->has('manual_only') && $request->boolean('manual_only')) {
                $query->whereJsonContains('event_metadata->created_manually', true);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'start_datetime');
            $sortOrder = $request->get('sort_order', 'asc');
            
            // Validate sort fields
            $allowedSortFields = ['start_datetime', 'end_datetime', 'title', 'priority', 'status', 'completion_percentage', 'created_at'];
            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'start_datetime';
            }
            
            $query->orderBy($sortBy, $sortOrder);

            // Pagination or get all
            if ($request->has('paginate') && $request->boolean('paginate')) {
                $perPage = $request->get('per_page', 15);
                $events = $query->paginate($perPage);
                
                return response()->json([
                    'status' => 'success',
                    'message' => "User's events retrieved successfully",
                    'data' => $events->items(),
                    'pagination' => [
                        'current_page' => $events->currentPage(),
                        'last_page' => $events->lastPage(),
                        'per_page' => $events->perPage(),
                        'total' => $events->total(),
                        'from' => $events->firstItem(),
                        'to' => $events->lastItem()
                    ]
                ]);
            } else {
                $events = $query->get();
                
                return response()->json([
                    'status' => 'success',
                    'message' => "User's events retrieved successfully",
                    'total' => $events->count(),
                    'data' => $events,
                    'user_id' => $userId,
                    'filters_applied' => array_keys($request->query()),
                    'ai_selection_ready' => true
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve user events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a manual task that interacts with the event table.
     */
    public function createManualTask(Request $request): JsonResponse
    {
        $templateService = new TaskTemplateService();
        
        // Check if using template
        if ($request->has('use_template') && $request->has('template_type')) {
            $templateType = $request->input('template_type');
            $templateVariables = $request->input('template_variables', []);
            $language = $request->input('language', 'vi');
            
            $templateData = $templateService->generateTaskFromTemplate($templateType, $templateVariables, $language);
            
            if (!$templateData) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid template type',
                    'available_templates' => array_keys($templateService->getAllTemplates())
                ], 400);
            }
            
            // Merge template data with request data
            $mergedData = array_merge($templateData, $request->only([
                'start_datetime', 'end_datetime', 'location', 'status', 
                'event_category_id', 'user_id', 'priority'
            ]));
            
            $request->merge($mergedData);
        }
        
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|in:scheduled,in_progress,completed,cancelled,postponed',
            'event_category_id' => 'nullable|exists:event_categories,id',
            'user_id' => 'nullable|exists:users,id',
            'priority' => 'nullable|integer|min:1|max:5',
            'event_metadata' => 'nullable|array',
            'participants' => 'nullable|array',
            'requirements' => 'nullable|array',
            'preparation_items' => 'nullable|array',
            'task_type' => 'nullable|string|max:100',
            'task_priority_label' => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|integer',
            'ai_execution_enabled' => 'nullable|boolean',
            'ai_execution_instructions' => 'nullable|string',
        ]);

        // Add manual task specific metadata
        $taskMetaData = array_merge($validated['event_metadata'] ?? [], [
            'created_manually' => true,
            'task_type' => $validated['task_type'] ?? 'general',
            'task_priority_label' => $validated['task_priority_label'] ?? 'medium',
            'assigned_to' => $validated['assigned_to'] ?? null,
            'created_by' => auth()->id() ?? null,
            'created_at' => now()->toISOString(),
            'ai_execution_enabled' => $validated['ai_execution_enabled'] ?? false,
            'ai_execution_instructions' => $validated['ai_execution_instructions'] ?? null,
            'template_used' => $request->input('template_type', null)
        ]);

        $validated['event_metadata'] = $taskMetaData;
        $validated['status'] = $validated['status'] ?? 'scheduled';
        $validated['priority'] = $validated['priority'] ?? 3;
        $validated['completion_percentage'] = 0;
        
        // Set user_id - use provided user_id, authenticated user, or get first user as default
        if (!isset($validated['user_id'])) {
            if (auth()->check()) {
                $validated['user_id'] = auth()->id();
            } else {
                // Get the first user from database as default (same approach as migration)
                $defaultUserId = \App\Models\User::value('id');
                if (!$defaultUserId) {
                    // Create a default user if none exists
                    $defaultUser = \App\Models\User::create([
                        'name' => 'System User',
                        'email' => 'system@' . request()->getHost(),
                        'password' => bcrypt(uniqid()),
                        'is_active' => true
                    ]);
                    $defaultUserId = $defaultUser->id;
                }
                $validated['user_id'] = $defaultUserId;
            }
        }
        
        // Remove non-database fields
        unset($validated['task_type'], $validated['task_priority_label'], $validated['assigned_to']);
        unset($validated['ai_execution_enabled'], $validated['ai_execution_instructions']);

        $event = Event::create($validated);
        $event->load('category', 'user');

        return response()->json([
            'status' => 'success',
            'message' => 'Manual task created successfully',
            'data' => $event,
            'task_info' => [
                'type' => $taskMetaData['task_type'],
                'priority_label' => $taskMetaData['task_priority_label'],
                'priority_numeric' => $event->priority,
                'created_by' => $taskMetaData['created_by'],
                'ai_enabled' => $taskMetaData['ai_execution_enabled'],
                'template_used' => $taskMetaData['template_used']
            ]
        ], 201);
    }
    
    /**
     * Get events imported from CSV or other files
     * GET /api/v1/events/imported
     */
    public function getImportedEvents(Request $request): JsonResponse
    {
        try {
            $userId = $request->get('user_id', auth()->id() ?? 1);
            $query = Event::where('user_id', $userId)
                ->whereJsonContains('event_metadata->imported', true);

            // Filter by import_id if specified
            if ($request->has('import_id')) {
                $query->whereJsonContains('event_metadata->import_id', intval($request->import_id));
            }

            // Filter by date range
            if ($request->has('date_from')) {
                $query->where('start_datetime', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->where('end_datetime', '<=', $request->date_to);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by priority
            if ($request->has('priority_min')) {
                $query->where('priority', '>=', $request->priority_min);
            }

            // Include import details
            if ($request->boolean('include_import_details')) {
                $query->with(['category', 'user']);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination or get all
            if ($request->boolean('paginate', true)) {
                $perPage = $request->get('per_page', 15);
                $events = $query->paginate($perPage);
                
                // Add import source information to each event
                $events->getCollection()->transform(function ($event) {
                    $importId = $event->event_metadata['import_id'] ?? null;
                    if ($importId) {
                        $import = \App\Models\RawScheduleImport::find($importId);
                        if ($import) {
                            $event->import_source = [
                                'id' => $import->id,
                                'filename' => $import->original_filename,
                                'type' => $import->source_type,
                                'imported_at' => $import->created_at,
                                'total_records' => $import->total_records_found
                            ];
                        }
                    }
                    return $event;
                });

                return response()->json([
                    'status' => 'success',
                    'message' => 'Imported events retrieved successfully',
                    'data' => $events->items(),
                    'pagination' => [
                        'current_page' => $events->currentPage(),
                        'last_page' => $events->lastPage(),
                        'per_page' => $events->perPage(),
                        'total' => $events->total()
                    ]
                ]);
            } else {
                $events = $query->get();
                
                // Add import source information
                $events->transform(function ($event) {
                    $importId = $event->event_metadata['import_id'] ?? null;
                    if ($importId) {
                        $import = \App\Models\RawScheduleImport::find($importId);
                        if ($import) {
                            $event->import_source = [
                                'id' => $import->id,
                                'filename' => $import->original_filename,
                                'type' => $import->source_type,
                                'imported_at' => $import->created_at,
                                'total_records' => $import->total_records_found
                            ];
                        }
                    }
                    return $event;
                });

                return response()->json([
                    'status' => 'success',
                    'message' => 'Imported events retrieved successfully',
                    'total' => $events->count(),
                    'data' => $events
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve imported events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get events grouped by import source
     * GET /api/v1/events/imported-grouped
     */
    public function getImportedEventsGrouped(Request $request): JsonResponse
    {
        try {
            $userId = $request->get('user_id', auth()->id() ?? 1);
            
            // Get all imports for the user
            $imports = \App\Models\RawScheduleImport::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            $groupedEvents = [];

            foreach ($imports as $import) {
                // Get events for this import
                $events = Event::where('user_id', $userId)
                    ->whereJsonContains('event_metadata->import_id', $import->id)
                    ->get();

                if ($events->count() > 0) {
                    $groupedEvents[] = [
                        'import' => [
                            'id' => $import->id,
                            'filename' => $import->original_filename,
                            'source_type' => $import->source_type,
                            'import_type' => $import->import_type,
                            'imported_at' => $import->created_at,
                            'status' => $import->status,
                            'total_records' => $import->total_records_found,
                            'successfully_processed' => $import->successfully_processed,
                            'ai_confidence' => $import->ai_confidence_score
                        ],
                        'events_count' => $events->count(),
                        'events' => $request->boolean('include_events', false) ? $events : null,
                        'statistics' => [
                            'scheduled' => $events->where('status', 'scheduled')->count(),
                            'completed' => $events->where('status', 'completed')->count(),
                            'in_progress' => $events->where('status', 'in_progress')->count(),
                            'cancelled' => $events->where('status', 'cancelled')->count(),
                            'high_priority' => $events->where('priority', '>=', 4)->count()
                        ]
                    ];
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Imported events grouped by source',
                'total_imports' => count($groupedEvents),
                'total_events' => array_sum(array_column($groupedEvents, 'events_count')),
                'data' => $groupedEvents
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve grouped imported events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Select imported events for AI processing
     * POST /api/v1/events/select-for-ai
     */
    public function selectEventsForAI(Request $request): JsonResponse
    {
        $request->validate([
            'event_ids' => 'nullable|array',
            'event_ids.*' => 'integer|exists:events,id',
            'import_id' => 'nullable|integer|exists:raw_schedule_imports,id',
            'filters' => 'nullable|array',
            'ai_task' => 'required|string|in:optimize,analyze,reschedule,prioritize,conflict_resolution'
        ]);

        try {
            $userId = $request->get('user_id', auth()->id() ?? 1);
            $query = Event::where('user_id', $userId);

            // Select by specific event IDs
            if ($request->has('event_ids') && !empty($request->event_ids)) {
                $query->whereIn('id', $request->event_ids);
            }
            // Or select by import ID
            elseif ($request->has('import_id')) {
                $query->whereJsonContains('event_metadata->imported', true)
                      ->whereJsonContains('event_metadata->import_id', intval($request->import_id));
            }
            // Or apply filters
            elseif ($request->has('filters')) {
                $filters = $request->filters;
                
                if (isset($filters['date_from'])) {
                    $query->where('start_datetime', '>=', $filters['date_from']);
                }
                if (isset($filters['date_to'])) {
                    $query->where('end_datetime', '<=', $filters['date_to']);
                }
                if (isset($filters['priority_min'])) {
                    $query->where('priority', '>=', $filters['priority_min']);
                }
                if (isset($filters['status'])) {
                    $query->where('status', $filters['status']);
                }
                if (isset($filters['imported_only']) && $filters['imported_only']) {
                    $query->whereJsonContains('event_metadata->imported', true);
                }
            }

            $events = $query->get();

            if ($events->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No events found matching the criteria'
                ], 404);
            }

            // Prepare data for AI processing
            $aiData = [
                'task' => $request->ai_task,
                'events_count' => $events->count(),
                'events' => $events->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'title' => $event->title,
                        'description' => $event->description,
                        'start_datetime' => $event->start_datetime,
                        'end_datetime' => $event->end_datetime,
                        'location' => $event->location,
                        'priority' => $event->priority,
                        'status' => $event->status,
                        'ai_metadata' => [
                            'imported' => $event->event_metadata['imported'] ?? false,
                            'import_id' => $event->event_metadata['import_id'] ?? null,
                            'ai_confidence' => $event->event_metadata['ai_confidence'] ?? null,
                            'entry_id' => $event->event_metadata['entry_id'] ?? null
                        ]
                    ];
                }),
                'processing_options' => $request->get('processing_options', []),
                'prepared_at' => now()
            ];

            // Save selection for AI processing (optional - could store in cache or session)
            $selectionId = 'ai_selection_' . uniqid();
            cache()->put($selectionId, $aiData, now()->addHours(1));

            return response()->json([
                'status' => 'success',
                'message' => 'Events selected for AI processing',
                'selection_id' => $selectionId,
                'data' => [
                    'task' => $request->ai_task,
                    'events_count' => $events->count(),
                    'events_preview' => $events->take(5)->map(function ($event) {
                        return [
                            'id' => $event->id,
                            'title' => $event->title,
                            'start_datetime' => $event->start_datetime,
                            'priority' => $event->priority
                        ];
                    }),
                    'ready_for_ai' => true
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to select events for AI processing',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available task templates
     */
    public function getTaskTemplates(Request $request): JsonResponse
    {
        $templateService = new TaskTemplateService();
        $language = $request->input('language', 'vi');
        
        $templates = $templateService->getAllTemplates($language);
        $requirements = $templateService->getTemplateRequirements();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Task templates retrieved successfully',
            'data' => [
                'templates' => $templates,
                'template_requirements' => $requirements,
                'available_languages' => ['vi'],
                'usage_example' => [
                    'use_template' => true,
                    'template_type' => 'meeting',
                    'template_variables' => [
                        'subject' => 'Q4 Planning',
                        'participants' => 'Team Leaders',
                        'objectives' => 'Define Q4 goals and KPIs'
                    ],
                    'start_datetime' => '2024-01-15 09:00:00',
                    'end_datetime' => '2024-01-15 11:00:00'
                ]
            ]
        ]);
    }
}
