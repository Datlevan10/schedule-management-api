<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserSchedulePreferencesRequest;
use App\Http\Resources\UserSchedulePreferencesResource;
use App\Models\UserSchedulePreference;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserSchedulePreferencesController extends Controller
{
    /**
     * Display the user's schedule preferences
     * GET /api/v1/preferences
     */
    public function show(): JsonResponse
    {
        try {
            $user = Auth::user();
            $preferences = UserSchedulePreference::getOrCreateForUser($user);
            
            // Load relationships
            $preferences->load(['defaultTemplate', 'defaultCategory']);

            return response()->json([
                'success' => true,
                'data' => new UserSchedulePreferencesResource($preferences)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store new user preferences (if they don't exist)
     * POST /api/v1/preferences
     */
    public function store(UserSchedulePreferencesRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check if preferences already exist
            $existingPreferences = UserSchedulePreference::where('user_id', $user->id)->first();
            if ($existingPreferences) {
                return response()->json([
                    'success' => false,
                    'message' => 'Preferences already exist. Use PUT to update.'
                ], 400);
            }

            $preferences = UserSchedulePreference::create([
                'user_id' => $user->id,
                'preferred_import_format' => $request->preferred_import_format,
                'default_template_id' => $request->default_template_id,
                'timezone_preference' => $request->timezone_preference,
                'date_format_preference' => $request->date_format_preference,
                'time_format_preference' => $request->time_format_preference,
                'ai_auto_categorize' => $request->ai_auto_categorize,
                'ai_auto_priority' => $request->ai_auto_priority,
                'ai_confidence_threshold' => $request->ai_confidence_threshold,
                'default_event_duration_minutes' => $request->default_event_duration_minutes,
                'default_priority' => $request->default_priority,
                'default_category_id' => $request->default_category_id,
                'notify_on_import_completion' => $request->notify_on_import_completion,
                'notify_on_parsing_errors' => $request->notify_on_parsing_errors,
                'custom_field_mappings' => $request->custom_field_mappings,
                'custom_keywords' => $request->custom_keywords,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Preferences created successfully',
                'data' => new UserSchedulePreferencesResource($preferences->load(['defaultTemplate', 'defaultCategory']))
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user preferences
     * PUT /api/v1/preferences
     */
    public function update(UserSchedulePreferencesRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $preferences = UserSchedulePreference::getOrCreateForUser($user);

            $preferences->update([
                'preferred_import_format' => $request->preferred_import_format,
                'default_template_id' => $request->default_template_id,
                'timezone_preference' => $request->timezone_preference,
                'date_format_preference' => $request->date_format_preference,
                'time_format_preference' => $request->time_format_preference,
                'ai_auto_categorize' => $request->ai_auto_categorize,
                'ai_auto_priority' => $request->ai_auto_priority,
                'ai_confidence_threshold' => $request->ai_confidence_threshold,
                'default_event_duration_minutes' => $request->default_event_duration_minutes,
                'default_priority' => $request->default_priority,
                'default_category_id' => $request->default_category_id,
                'notify_on_import_completion' => $request->notify_on_import_completion,
                'notify_on_parsing_errors' => $request->notify_on_parsing_errors,
                'custom_field_mappings' => $request->custom_field_mappings,
                'custom_keywords' => $request->custom_keywords,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Preferences updated successfully',
                'data' => new UserSchedulePreferencesResource($preferences->fresh(['defaultTemplate', 'defaultCategory']))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user preferences (reset to defaults)
     * DELETE /api/v1/preferences
     */
    public function destroy(): JsonResponse
    {
        try {
            $user = Auth::user();
            $preferences = UserSchedulePreference::where('user_id', $user->id)->first();
            
            if ($preferences) {
                $preferences->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Preferences reset to defaults'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a custom keyword
     * POST /api/v1/preferences/keywords
     */
    public function addKeyword(Request $request): JsonResponse
    {
        $request->validate([
            'keyword' => 'required|string|max:100',
        ]);

        try {
            $user = Auth::user();
            $preferences = UserSchedulePreference::getOrCreateForUser($user);
            
            $preferences->addCustomKeyword($request->keyword);

            return response()->json([
                'success' => true,
                'message' => 'Keyword added successfully',
                'data' => [
                    'custom_keywords' => $preferences->fresh()->custom_keywords
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add keyword',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove a custom keyword
     * DELETE /api/v1/preferences/keywords/{keyword}
     */
    public function removeKeyword($keyword): JsonResponse
    {
        try {
            $user = Auth::user();
            $preferences = UserSchedulePreference::getOrCreateForUser($user);
            
            $preferences->removeCustomKeyword($keyword);

            return response()->json([
                'success' => true,
                'message' => 'Keyword removed successfully',
                'data' => [
                    'custom_keywords' => $preferences->fresh()->custom_keywords
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove keyword',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update field mapping
     * POST /api/v1/preferences/field-mappings
     */
    public function updateFieldMapping(Request $request): JsonResponse
    {
        $request->validate([
            'field' => 'required|string|max:100',
            'mapping' => 'required|string|max:100',
        ]);

        try {
            $user = Auth::user();
            $preferences = UserSchedulePreference::getOrCreateForUser($user);
            
            $preferences->updateFieldMapping($request->field, $request->mapping);

            return response()->json([
                'success' => true,
                'message' => 'Field mapping updated successfully',
                'data' => [
                    'custom_field_mappings' => $preferences->fresh()->custom_field_mappings
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update field mapping',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get default preference values
     * GET /api/v1/preferences/defaults
     */
    public function getDefaults(): JsonResponse
    {
        try {
            $defaults = [
                'preferred_import_format' => 'csv',
                'timezone_preference' => 'Asia/Ho_Chi_Minh',
                'date_format_preference' => 'dd/mm/yyyy',
                'time_format_preference' => 'HH:mm',
                'ai_auto_categorize' => true,
                'ai_auto_priority' => true,
                'ai_confidence_threshold' => 0.7,
                'default_event_duration_minutes' => 60,
                'default_priority' => 3,
                'notify_on_import_completion' => true,
                'notify_on_parsing_errors' => true,
                'custom_field_mappings' => [],
                'custom_keywords' => [],
            ];

            // Get available options
            $availableOptions = [
                'import_formats' => ['csv', 'excel', 'txt', 'json'],
                'date_formats' => [
                    'dd/mm/yyyy',
                    'mm/dd/yyyy',
                    'yyyy-mm-dd',
                    'dd-mm-yyyy',
                    'mm-dd-yyyy'
                ],
                'time_formats' => [
                    'HH:mm',
                    'HH:mm:ss',
                    'h:mm AM/PM',
                    'h:mm:ss AM/PM'
                ],
                'timezones' => [
                    'Asia/Ho_Chi_Minh',
                    'UTC',
                    'America/New_York',
                    'Europe/London',
                    'Asia/Tokyo',
                    'Australia/Sydney'
                ],
                'priority_levels' => [
                    1 => 'Very Low',
                    2 => 'Low',
                    3 => 'Medium',
                    4 => 'High',
                    5 => 'Very High'
                ],
                'duration_presets' => [15, 30, 45, 60, 90, 120, 180, 240, 480],
                'confidence_thresholds' => [0.5, 0.6, 0.7, 0.8, 0.9],
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'defaults' => $defaults,
                    'available_options' => $availableOptions,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch defaults',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}