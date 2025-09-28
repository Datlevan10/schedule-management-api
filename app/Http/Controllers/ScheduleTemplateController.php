<?php

namespace App\Http\Controllers;

use App\Http\Resources\ScheduleImportTemplateResource;
use App\Models\ScheduleImportTemplate;
use App\Services\TemplateGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ScheduleTemplateController extends Controller
{
    protected $templateService;

    public function __construct(TemplateGenerationService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Display a listing of templates
     * GET /api/v1/schedule-templates
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = ScheduleImportTemplate::active()
                ->with(['profession', 'creator']);

            // Filter by profession (user's profession + global templates)
            if ($user->profession_id) {
                $query->applicableFor($user->profession_id);
            } else {
                $query->global();
            }

            // Filter by file type
            if ($request->has('file_type')) {
                $query->fileType($request->file_type);
            }

            // Filter by profession ID (admin use)
            if ($request->has('profession_id') && $user->isAdmin()) {
                $query->forProfession($request->profession_id);
            }

            // Search by name
            if ($request->has('search')) {
                $query->where('template_name', 'LIKE', '%' . $request->search . '%');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'template_name');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $templates = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => ScheduleImportTemplateResource::collection($templates),
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
     * Display the specified template
     * GET /api/v1/schedule-templates/{id}
     */
    public function show($id): JsonResponse
    {
        try {
            $template = ScheduleImportTemplate::active()
                ->with(['profession', 'creator'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new ScheduleImportTemplateResource($template)
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
     * Download template file
     * GET /api/v1/schedule-templates/{id}/download
     */
    public function download($id, Request $request): Response
    {
        $request->validate([
            'type' => 'required|in:template,sample,instructions'
        ]);

        try {
            $template = ScheduleImportTemplate::active()->findOrFail($id);
            
            // Generate files if they don't exist
            if (!$template->hasGeneratedFiles()) {
                $this->templateService->generateTemplateFiles($template);
                $template->refresh();
            }

            // Increment download count
            $template->incrementDownloadCount();

            $type = $request->get('type');
            $filePath = null;
            $fileName = null;

            switch ($type) {
                case 'template':
                    $filePath = $template->template_file_path;
                    $fileName = "{$template->template_name}_template.{$template->file_type}";
                    break;
                case 'sample':
                    $filePath = $template->sample_data_file_path;
                    $fileName = "{$template->template_name}_sample.{$template->file_type}";
                    break;
                case 'instructions':
                    $filePath = $template->instructions_file_path;
                    $fileName = "{$template->template_name}_instructions.md";
                    break;
            }

            if (!$filePath || !Storage::disk('public')->exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            $content = Storage::disk('public')->get($filePath);
            $mimeType = $this->getMimeType($template->file_type, $type);

            return response($content)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', "attachment; filename=\"{$fileName}\"");

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate template preview
     * GET /api/v1/schedule-templates/{id}/preview
     */
    public function preview($id, Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:template,sample,instructions'
        ]);

        try {
            $template = ScheduleImportTemplate::active()->findOrFail($id);
            $type = $request->get('type');
            $content = '';

            switch ($type) {
                case 'template':
                    $content = $template->generateTemplateContent();
                    break;
                case 'sample':
                    $content = $template->generateTemplateContent();
                    break;
                case 'instructions':
                    $content = $template->generateInstructions();
                    break;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'content' => $content,
                    'type' => $type,
                    'file_type' => $template->file_type,
                    'template_name' => $template->template_name,
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
     * Rate a template
     * POST /api/v1/schedule-templates/{id}/rate
     */
    public function rate($id, Request $request): JsonResponse
    {
        $request->validate([
            'rating' => 'required|numeric|min:1|max:5',
            'comment' => 'nullable|string|max:500'
        ]);

        try {
            $template = ScheduleImportTemplate::active()->findOrFail($id);
            
            // In a real implementation, you'd store individual ratings
            // For now, we'll just update the average
            $template->updateUserRating($request->rating);

            return response()->json([
                'success' => true,
                'message' => 'Template rated successfully',
                'data' => [
                    'new_rating' => $template->user_feedback_rating,
                    'download_count' => $template->download_count,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rate template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get templates for user's profession
     * GET /api/v1/schedule-templates/my-profession
     */
    public function myProfessionTemplates(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user->profession_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User profession not set'
                ], 400);
            }

            $templates = ScheduleImportTemplate::active()
                ->forProfession($user->profession_id)
                ->with(['profession', 'creator'])
                ->orderBy('is_default', 'desc')
                ->orderBy('template_name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => ScheduleImportTemplateResource::collection($templates)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profession templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get default templates
     * GET /api/v1/schedule-templates/defaults
     */
    public function defaultTemplates(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $query = ScheduleImportTemplate::active()
                ->default()
                ->with(['profession', 'creator']);

            // Filter by user's profession if set
            if ($user->profession_id) {
                $query->where(function ($q) use ($user) {
                    $q->whereNull('profession_id')
                      ->orWhere('profession_id', $user->profession_id);
                });
            } else {
                $query->global();
            }

            $templates = $query->orderBy('template_name')->get();

            return response()->json([
                'success' => true,
                'data' => ScheduleImportTemplateResource::collection($templates)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch default templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate template files (Admin only)
     * POST /api/v1/schedule-templates/{id}/generate
     */
    public function generateFiles($id): JsonResponse
    {
        try {
            // Check admin permission
            if (!Auth::user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $template = ScheduleImportTemplate::findOrFail($id);
            
            // Generate template files
            $this->templateService->generateTemplateFiles($template);

            return response()->json([
                'success' => true,
                'message' => 'Template files generated successfully',
                'data' => new ScheduleImportTemplateResource($template->fresh())
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate template files',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get template statistics
     * GET /api/v1/schedule-templates/statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $query = ScheduleImportTemplate::active();
            
            if ($user->profession_id) {
                $query->applicableFor($user->profession_id);
            }

            $stats = [
                'total_templates' => $query->count(),
                'by_file_type' => $query->groupBy('file_type')
                    ->selectRaw('file_type, count(*) as count')
                    ->pluck('count', 'file_type'),
                'most_downloaded' => $query->orderBy('download_count', 'desc')
                    ->limit(5)
                    ->get(['id', 'template_name', 'download_count']),
                'highest_rated' => $query->whereNotNull('user_feedback_rating')
                    ->orderBy('user_feedback_rating', 'desc')
                    ->limit(5)
                    ->get(['id', 'template_name', 'user_feedback_rating']),
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
     * Search templates
     * GET /api/v1/schedule-templates/search
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2',
            'file_type' => 'nullable|in:csv,excel,json,txt',
            'profession_id' => 'nullable|integer|exists:professions,id'
        ]);

        try {
            $user = Auth::user();
            $searchQuery = $request->get('query');
            
            $query = ScheduleImportTemplate::active()
                ->with(['profession', 'creator'])
                ->where(function ($q) use ($searchQuery) {
                    $q->where('template_name', 'LIKE', "%{$searchQuery}%")
                      ->orWhere('template_description', 'LIKE', "%{$searchQuery}%");
                });

            // Apply filters
            if ($request->has('file_type')) {
                $query->fileType($request->file_type);
            }

            if ($request->has('profession_id')) {
                $query->forProfession($request->profession_id);
            } elseif ($user->profession_id) {
                $query->applicableFor($user->profession_id);
            }

            $templates = $query->orderBy('download_count', 'desc')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => ScheduleImportTemplateResource::collection($templates),
                'meta' => [
                    'search_query' => $searchQuery,
                    'total_results' => $templates->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get MIME type for file download
     */
    protected function getMimeType(string $fileType, string $downloadType): string
    {
        if ($downloadType === 'instructions') {
            return 'text/markdown';
        }

        return match ($fileType) {
            'csv' => 'text/csv',
            'json' => 'application/json',
            'txt' => 'text/plain',
            'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }
}