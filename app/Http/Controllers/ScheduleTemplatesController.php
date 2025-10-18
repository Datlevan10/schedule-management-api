<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleTemplateRequest;
use App\Http\Resources\ScheduleTemplateResource;
use App\Models\ScheduleTemplate;
use App\Services\TemplateGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ScheduleTemplatesController extends Controller
{
    protected $templateService;

    public function __construct(TemplateGenerationService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Display a listing of schedule templates
     * GET /api/v1/templates
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $query = ScheduleTemplate::with(['profession', 'creator']);

            // Admin can see all, users see only their profession + global
            if (!$user->isAdmin()) {
                if ($user->profession_id) {
                    $query->where(function ($q) use ($user) {
                        $q->whereNull('profession_id')
                          ->orWhere('profession_id', $user->profession_id);
                    });
                } else {
                    $query->whereNull('profession_id');
                }
            }

            // Filter by profession
            if ($request->has('profession_id')) {
                $query->where('profession_id', $request->profession_id);
            }

            // Filter by template type
            if ($request->has('template_type')) {
                $query->where('template_type', $request->template_type);
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $templates = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => ScheduleTemplateResource::collection($templates),
                'meta' => [
                    'current_page' => $templates->currentPage(),
                    'last_page' => $templates->lastPage(),
                    'per_page' => $templates->perPage(),
                    'total' => $templates->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created template
     * POST /api/v1/templates
     */
    public function store(ScheduleTemplateRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $template = ScheduleTemplate::create([
                'profession_id' => $request->profession_id,
                'created_by' => $user->id,
                'name' => $request->name,
                'description' => $request->description,
                'template_type' => $request->template_type,
                'field_mapping' => $request->field_mapping,
                'required_fields' => $request->required_fields,
                'optional_fields' => $request->optional_fields,
                'default_values' => $request->default_values,
                'date_formats' => $request->date_formats,
                'time_formats' => $request->time_formats,
                'keyword_patterns' => $request->keyword_patterns,
                'validation_rules' => $request->validation_rules,
                'ai_processing_rules' => $request->ai_processing_rules,
                'is_active' => $request->is_active ?? true,
                'is_default' => $request->is_default ?? false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template created successfully',
                'data' => new ScheduleTemplateResource($template->load(['profession', 'creator']))
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified template
     * GET /api/v1/templates/{id}
     */
    public function show($id): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $query = ScheduleTemplate::with(['profession', 'creator']);

            // Apply access control
            if (!$user->isAdmin()) {
                if ($user->profession_id) {
                    $query->where(function ($q) use ($user) {
                        $q->whereNull('profession_id')
                          ->orWhere('profession_id', $user->profession_id);
                    });
                } else {
                    $query->whereNull('profession_id');
                }
            }

            $template = $query->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new ScheduleTemplateResource($template)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified template
     * PUT /api/v1/templates/{id}
     */
    public function update(ScheduleTemplateRequest $request, $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $template = ScheduleTemplate::findOrFail($id);

            // Check permissions
            if (!$user->isAdmin() && $template->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this template'
                ], 403);
            }

            $template->update([
                'profession_id' => $request->profession_id,
                'name' => $request->name,
                'description' => $request->description,
                'template_type' => $request->template_type,
                'field_mapping' => $request->field_mapping,
                'required_fields' => $request->required_fields,
                'optional_fields' => $request->optional_fields,
                'default_values' => $request->default_values,
                'date_formats' => $request->date_formats,
                'time_formats' => $request->time_formats,
                'keyword_patterns' => $request->keyword_patterns,
                'validation_rules' => $request->validation_rules,
                'ai_processing_rules' => $request->ai_processing_rules,
                'is_active' => $request->is_active,
                'is_default' => $request->is_default,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully',
                'data' => new ScheduleTemplateResource($template->fresh(['profession', 'creator']))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified template
     * DELETE /api/v1/templates/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $template = ScheduleTemplate::findOrFail($id);

            // Check permissions
            if (!$user->isAdmin() && $template->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this template'
                ], 403);
            }

            // Check if template is in use
            $usageCount = $template->userPreferences()->count();
            if ($usageCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete template. It is currently used by {$usageCount} user(s)."
                ], 400);
            }

            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate field mapping
     * POST /api/v1/templates/validate-mapping
     */
    public function validateMapping(Request $request): JsonResponse
    {
        $request->validate([
            'field_mapping' => 'required|array',
            'required_fields' => 'required|array',
            'sample_data' => 'nullable|array',
        ]);

        try {
            $fieldMapping = $request->field_mapping;
            $requiredFields = $request->required_fields;
            $sampleData = $request->sample_data ?? [];

            $errors = [];
            $warnings = [];

            // Check if all required fields are mapped
            foreach ($requiredFields as $field) {
                if (!isset($fieldMapping[$field])) {
                    $errors[] = "Required field '{$field}' is not mapped";
                }
            }

            // Validate mapping targets exist in sample data
            if (!empty($sampleData)) {
                foreach ($fieldMapping as $targetField => $sourceField) {
                    if (!isset($sampleData[$sourceField])) {
                        $warnings[] = "Source field '{$sourceField}' not found in sample data";
                    }
                }
            }

            // Check for duplicate mappings
            $duplicates = array_diff_assoc($fieldMapping, array_unique($fieldMapping));
            if (!empty($duplicates)) {
                foreach ($duplicates as $field => $source) {
                    $warnings[] = "Multiple fields mapped to same source: '{$source}'";
                }
            }

            return response()->json([
                'success' => count($errors) === 0,
                'valid' => count($errors) === 0,
                'errors' => $errors,
                'warnings' => $warnings,
                'message' => count($errors) === 0 ? 'Mapping is valid' : 'Mapping has errors'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate a template
     * POST /api/v1/templates/{id}/duplicate
     */
    public function duplicate($id, Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $originalTemplate = ScheduleTemplate::findOrFail($id);

            // Check access permissions
            if (!$user->isAdmin() && $originalTemplate->profession_id !== $user->profession_id && $originalTemplate->profession_id !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to duplicate this template'
                ], 403);
            }

            $duplicatedTemplate = $originalTemplate->replicate();
            $duplicatedTemplate->name = $request->name;
            $duplicatedTemplate->description = $request->description ?? $originalTemplate->description . ' (Copy)';
            $duplicatedTemplate->created_by = $user->id;
            $duplicatedTemplate->is_default = false;
            $duplicatedTemplate->usage_count = 0;
            $duplicatedTemplate->success_rate = null;
            $duplicatedTemplate->save();

            return response()->json([
                'success' => true,
                'message' => 'Template duplicated successfully',
                'data' => new ScheduleTemplateResource($duplicatedTemplate->load(['profession', 'creator']))
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate template',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}