<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminCustomerReportingTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AdminCustomerReportingTemplateController extends Controller
{
    /**
     * Display a listing of templates
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = AdminCustomerReportingTemplate::with('creator');

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Filter by frequency
            if ($request->has('frequency')) {
                $query->byFrequency($request->frequency);
            }

            // Filter by default
            if ($request->has('is_default')) {
                $query->where('is_default', $request->boolean('is_default'));
            }

            // Search by name
            if ($request->has('search')) {
                $query->where('template_name', 'like', '%' . $request->search . '%');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $templates = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $templates->items(),
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
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'template_name' => 'required|string|max:255|unique:admin_customer_reporting_templates',
            'description' => 'nullable|string',
            'customer_fields' => 'required|array|min:1',
            'customer_fields.*' => 'string',
            'report_filters' => 'nullable|array',
            'aggregation_rules' => 'nullable|array',
            'report_frequency' => 'required|in:daily,weekly,monthly,yearly',
            'notification_settings' => 'nullable|array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'customer_limit' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->all();
            $data['created_by'] = Auth::id();

            // If setting as default, unset other defaults
            if ($request->boolean('is_default')) {
                AdminCustomerReportingTemplate::where('is_default', true)->update(['is_default' => false]);
            }

            $template = AdminCustomerReportingTemplate::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Template created successfully',
                'data' => $template->load('creator')
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
     */
    public function show($id): JsonResponse
    {
        try {
            $template = AdminCustomerReportingTemplate::with('creator')->findOrFail($id);

            // Add additional info
            $templateData = $template->toArray();
            $templateData['customer_count'] = $template->getCustomerCount();
            $templateData['is_at_limit'] = $template->isAtCustomerLimit();

            return response()->json([
                'success' => true,
                'data' => $templateData
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
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'template_name' => 'sometimes|string|max:255|unique:admin_customer_reporting_templates,template_name,' . $id,
            'description' => 'nullable|string',
            'customer_fields' => 'sometimes|array|min:1',
            'customer_fields.*' => 'string',
            'report_filters' => 'nullable|array',
            'aggregation_rules' => 'nullable|array',
            'report_frequency' => 'sometimes|in:daily,weekly,monthly,yearly',
            'notification_settings' => 'nullable|array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'customer_limit' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $template = AdminCustomerReportingTemplate::findOrFail($id);

            // If setting as default, unset other defaults
            if ($request->has('is_default') && $request->boolean('is_default')) {
                AdminCustomerReportingTemplate::where('id', '!=', $id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $template->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully',
                'data' => $template->fresh()->load('creator')
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
     */
    public function destroy($id): JsonResponse
    {
        try {
            $template = AdminCustomerReportingTemplate::findOrFail($id);
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
     * Generate report using template
     */
    public function generateReport($id): JsonResponse
    {
        try {
            $template = AdminCustomerReportingTemplate::findOrFail($id);

            if (!$template->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template is not active'
                ], 400);
            }

            $reportData = $template->generateReportData();
            $template->markAsGenerated();

            return response()->json([
                'success' => true,
                'message' => 'Report generated successfully',
                'data' => $reportData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStats(): JsonResponse
    {
        try {
            $totalCustomers = User::count();
            $activeCustomers = User::where('is_active', true)->count();
            $customersByProfession = User::with('profession')
                ->get()
                ->groupBy('profession.name')
                ->map->count();

            $stats = [
                'total_customers' => $totalCustomers,
                'active_customers' => $activeCustomers,
                'inactive_customers' => $totalCustomers - $activeCustomers,
                'customers_by_profession' => $customersByProfession,
                'available_fields' => [
                    'name', 'email', 'profession_id', 'profession_level',
                    'workplace', 'department', 'is_active', 'created_at'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clone an existing template
     */
    public function cloneTemplate($id): JsonResponse
    {
        try {
            $originalTemplate = AdminCustomerReportingTemplate::findOrFail($id);

            $clonedData = $originalTemplate->toArray();
            unset($clonedData['id'], $clonedData['created_at'], $clonedData['updated_at']);
            
            $clonedData['template_name'] = $originalTemplate->template_name . ' (Copy)';
            $clonedData['is_default'] = false;
            $clonedData['created_by'] = Auth::id();
            $clonedData['total_reports_generated'] = 0;
            $clonedData['last_generated_at'] = null;

            $clonedTemplate = AdminCustomerReportingTemplate::create($clonedData);

            return response()->json([
                'success' => true,
                'message' => 'Template cloned successfully',
                'data' => $clonedTemplate->load('creator')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clone template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle template active status
     */
    public function toggleActive($id): JsonResponse
    {
        try {
            $template = AdminCustomerReportingTemplate::findOrFail($id);
            $template->update(['is_active' => !$template->is_active]);

            return response()->json([
                'success' => true,
                'message' => 'Template status updated successfully',
                'data' => $template->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle template status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
