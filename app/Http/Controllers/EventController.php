<?php

namespace App\Http\Controllers;

use App\Models\Event;
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
     * Create a manual task that interacts with the event table.
     */
    public function createManualTask(Request $request): JsonResponse
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
            'task_type' => 'nullable|string|max:100',
            'task_priority_label' => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|integer',
        ]);

        // Add manual task specific metadata
        $taskMetaData = array_merge($validated['event_metadata'] ?? [], [
            'created_manually' => true,
            'task_type' => $validated['task_type'] ?? 'general',
            'task_priority_label' => $validated['task_priority_label'] ?? 'medium',
            'assigned_to' => $validated['assigned_to'] ?? null,
            'created_by' => auth()->id() ?? null,
            'created_at' => now()->toISOString()
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
                'created_by' => $taskMetaData['created_by']
            ]
        ], 201);
    }
}
