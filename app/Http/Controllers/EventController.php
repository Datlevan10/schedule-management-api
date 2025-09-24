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
            ->where('start_date', '>', now())
            ->orderBy('start_date', 'asc')
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
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|in:pending,active,completed,cancelled',
            'category_id' => 'nullable|exists:event_categories,id',
            'max_participants' => 'nullable|integer|min:1',
            'meta_data' => 'nullable|array'
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
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|in:pending,active,completed,cancelled',
            'category_id' => 'nullable|exists:event_categories,id',
            'max_participants' => 'nullable|integer|min:1',
            'meta_data' => 'nullable|array'
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
            $query->where('start_date', '>=', $request->query('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('end_date', '<=', $request->query('date_to'));
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        $events = $query->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Search results retrieved successfully',
            'total' => $events->count(),
            'data' => $events
        ]);
    }
}
