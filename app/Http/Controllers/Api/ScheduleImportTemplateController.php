<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleImportTemplateRequest;
use App\Http\Requests\UpdateScheduleImportTemplateRequest;
use App\Http\Resources\ScheduleImportTemplateResource;
use App\Models\ScheduleImportTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ScheduleImportTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ScheduleImportTemplate::query();

        // Filter by profession_id
        if ($request->has('profession_id')) {
            $query->where('profession_id', $request->profession_id);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by default status
        if ($request->has('is_default')) {
            $query->where('is_default', $request->boolean('is_default'));
        }

        // Filter by file type
        if ($request->has('file_type')) {
            $query->where('file_type', $request->file_type);
        }

        // Include profession relationship
        $query->with('profession');

        // Pagination
        $perPage = $request->get('per_page', 15);
        $templates = $query->paginate($perPage);

        return ScheduleImportTemplateResource::collection($templates);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreScheduleImportTemplateRequest $request): ScheduleImportTemplateResource
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();

        // If this is set as default, unset other defaults for the same profession
        if ($request->boolean('is_default')) {
            ScheduleImportTemplate::where('profession_id', $data['profession_id'])
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $template = ScheduleImportTemplate::create($data);

        // Generate template files if needed
        if ($request->boolean('generate_files')) {
            $this->generateTemplateFiles($template);
        }

        return new ScheduleImportTemplateResource($template->load('profession'));
    }

    /**
     * Display the specified resource.
     */
    public function show(ScheduleImportTemplate $scheduleImportTemplate): ScheduleImportTemplateResource
    {
        return new ScheduleImportTemplateResource(
            $scheduleImportTemplate->load(['profession', 'creator'])
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateScheduleImportTemplateRequest $request, ScheduleImportTemplate $scheduleImportTemplate): ScheduleImportTemplateResource
    {
        $data = $request->validated();

        // If setting as default, unset other defaults for the same profession
        if ($request->has('is_default') && $request->boolean('is_default')) {
            ScheduleImportTemplate::where('profession_id', $scheduleImportTemplate->profession_id)
                ->where('id', '!=', $scheduleImportTemplate->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $scheduleImportTemplate->update($data);

        // Regenerate template files if requested
        if ($request->boolean('regenerate_files')) {
            $this->generateTemplateFiles($scheduleImportTemplate);
        }

        return new ScheduleImportTemplateResource(
            $scheduleImportTemplate->fresh()->load('profession')
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ScheduleImportTemplate $scheduleImportTemplate): JsonResponse
    {
        // Delete associated files
        $this->deleteTemplateFiles($scheduleImportTemplate);

        $scheduleImportTemplate->delete();

        return response()->json([
            'message' => 'Schedule import template deleted successfully'
        ]);
    }

    /**
     * Download template file
     */
    public function download(ScheduleImportTemplate $scheduleImportTemplate): BinaryFileResponse|JsonResponse
    {
        // Check if file actually exists, generate if not
        $filePath = $scheduleImportTemplate->template_file_path 
            ? storage_path('app/public/' . $scheduleImportTemplate->template_file_path)
            : null;

        if (!$filePath || !file_exists($filePath)) {
            // Generate template file
            $this->generateTemplateFiles($scheduleImportTemplate);
            $scheduleImportTemplate->refresh();
            $filePath = storage_path('app/public/' . $scheduleImportTemplate->template_file_path);
        }

        if (!file_exists($filePath)) {
            return response()->json([
                'message' => 'Template file not found'
            ], 404);
        }

        // Increment download count
        $scheduleImportTemplate->incrementDownloadCount();

        $fileName = $scheduleImportTemplate->template_name . '.' . $scheduleImportTemplate->file_type;

        return response()->download($filePath, $fileName);
    }

    /**
     * Download sample data file
     */
    public function downloadSample(ScheduleImportTemplate $scheduleImportTemplate): BinaryFileResponse|JsonResponse
    {
        if (!$scheduleImportTemplate->sample_data_file_path) {
            return response()->json([
                'message' => 'Sample data file not found'
            ], 404);
        }

        $filePath = storage_path('app/public/' . $scheduleImportTemplate->sample_data_file_path);

        if (!file_exists($filePath)) {
            return response()->json([
                'message' => 'Sample data file not found'
            ], 404);
        }

        $fileName = $scheduleImportTemplate->template_name . '_sample.' . $scheduleImportTemplate->file_type;

        return response()->download($filePath, $fileName);
    }

    /**
     * Download instructions file
     */
    public function downloadInstructions(ScheduleImportTemplate $scheduleImportTemplate): BinaryFileResponse|JsonResponse
    {
        if (!$scheduleImportTemplate->instructions_file_path) {
            // Generate instructions file if it doesn't exist
            $this->generateInstructionsFile($scheduleImportTemplate);
            $scheduleImportTemplate->refresh();
        }

        $filePath = storage_path('app/public/' . $scheduleImportTemplate->instructions_file_path);

        if (!file_exists($filePath)) {
            return response()->json([
                'message' => 'Instructions file not found'
            ], 404);
        }

        $fileName = $scheduleImportTemplate->template_name . '_instructions.md';

        return response()->download($filePath, $fileName);
    }

    /**
     * Generate template files
     */
    protected function generateTemplateFiles(ScheduleImportTemplate $template): void
    {
        $directory = 'schedule_templates/' . $template->id;
        Storage::disk('public')->makeDirectory($directory);

        // Generate template file
        $templateContent = $template->generateTemplateContent();
        $templatePath = $directory . '/template.' . $template->file_type;
        Storage::disk('public')->put($templatePath, $templateContent);
        $template->update(['template_file_path' => $templatePath]);

        // Generate sample data file (with more rows)
        $sampleContent = $this->generateSampleDataContent($template);
        $samplePath = $directory . '/sample.' . $template->file_type;
        Storage::disk('public')->put($samplePath, $sampleContent);
        $template->update(['sample_data_file_path' => $samplePath]);

        // Generate instructions file
        $this->generateInstructionsFile($template);
    }

    /**
     * Generate instructions file
     */
    protected function generateInstructionsFile(ScheduleImportTemplate $template): void
    {
        $directory = 'schedule_templates/' . $template->id;
        Storage::disk('public')->makeDirectory($directory);

        $instructionsContent = $template->generateInstructions();
        $instructionsPath = $directory . '/instructions.md';
        Storage::disk('public')->put($instructionsPath, $instructionsContent);
        $template->update(['instructions_file_path' => $instructionsPath]);
    }

    /**
     * Generate sample data content with multiple rows
     */
    protected function generateSampleDataContent(ScheduleImportTemplate $template): string
    {
        if ($template->file_type === 'csv') {
            $columns = $template->all_columns;
            $rows = [];
            
            // Header row
            $rows[] = implode(',', $columns);
            
            // Generate 5 sample rows
            for ($i = 1; $i <= 5; $i++) {
                $row = [];
                foreach ($columns as $column) {
                    $row[] = $this->getSampleValueForColumn($column, $i);
                }
                $rows[] = implode(',', $row);
            }
            
            return implode("\n", $rows);
        }
        
        // For other file types, return basic template
        return $template->generateTemplateContent();
    }

    /**
     * Get sample value for a column with variation
     */
    protected function getSampleValueForColumn(string $column, int $index): string
    {
        $samples = [
            'date' => ['2024-01-15', '2024-01-16', '2024-01-17', '2024-01-18', '2024-01-19'],
            'subject' => ['Mathematics', 'Physics', 'Chemistry', 'Biology', 'English'],
            'title' => ['Morning Meeting', 'Project Review', 'Team Standup', 'Client Call', 'Training Session'],
            'description' => [
                'Discuss daily objectives',
                'Review project progress',
                'Team synchronization',
                'Client requirements discussion',
                'New feature training'
            ],
            'start_time' => ['09:00', '10:30', '14:00', '15:30', '16:00'],
            'end_time' => ['10:00', '11:30', '15:00', '16:30', '17:00'],
            'location' => ['Room A', 'Room B', 'Conference Hall', 'Online', 'Training Center'],
            'teacher' => ['Dr. Smith', 'Prof. Johnson', 'Dr. Brown', 'Prof. Davis', 'Dr. Wilson'],
            'room' => ['101', '102', '201', '202', 'Lab 1'],
            'priority' => ['High', 'Medium', 'Low', 'High', 'Medium'],
            'category' => ['Meeting', 'Class', 'Workshop', 'Seminar', 'Training'],
            'keywords' => ['urgent', 'important', 'review', 'planning', 'discussion'],
            'notes' => ['Bring laptop', 'Prepare presentation', 'Review documents', 'Team meeting', 'Important discussion']
        ];

        $index = ($index - 1) % 5; // Ensure we stay within array bounds
        
        if (isset($samples[$column])) {
            return $samples[$column][$index];
        }
        
        return "Sample {$column} {$index}";
    }

    /**
     * Delete template files
     */
    protected function deleteTemplateFiles(ScheduleImportTemplate $template): void
    {
        $directory = 'schedule_templates/' . $template->id;
        
        if (Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->deleteDirectory($directory);
        }
    }

    /**
     * Get templates by profession with applicable global templates
     */
    public function getByProfession(Request $request, $professionId): AnonymousResourceCollection
    {
        $templates = ScheduleImportTemplate::applicableFor($professionId)
            ->active()
            ->with('profession')
            ->get();

        return ScheduleImportTemplateResource::collection($templates);
    }

    /**
     * Update template statistics after successful import
     */
    public function updateStatistics(Request $request, ScheduleImportTemplate $scheduleImportTemplate): JsonResponse
    {
        $request->validate([
            'success' => 'required|boolean',
            'rating' => 'nullable|numeric|min:1|max:5'
        ]);

        // Update success rate (simplified - in production, track individual imports)
        if ($request->has('success')) {
            $currentRate = $scheduleImportTemplate->success_import_rate ?? 0;
            $newRate = $request->boolean('success') ? 
                min(100, $currentRate + 5) : 
                max(0, $currentRate - 5);
            $scheduleImportTemplate->updateSuccessRate($newRate);
        }

        // Update user rating if provided
        if ($request->has('rating')) {
            $scheduleImportTemplate->updateUserRating($request->rating);
        }

        return response()->json([
            'message' => 'Template statistics updated successfully'
        ]);
    }
}