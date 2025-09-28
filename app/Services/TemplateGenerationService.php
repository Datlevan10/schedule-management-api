<?php

namespace App\Services;

use App\Models\ScheduleImportTemplate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemplateGenerationService
{
    /**
     * Generate all template files for a template
     */
    public function generateTemplateFiles(ScheduleImportTemplate $template): void
    {
        $baseDir = "templates/{$template->id}";
        
        // Ensure directory exists
        Storage::disk('public')->makeDirectory($baseDir);
        
        // Generate template file
        $templateContent = $template->generateTemplateContent();
        $templatePath = "{$baseDir}/template.{$template->file_type}";
        Storage::disk('public')->put($templatePath, $templateContent);
        
        // Generate sample data file (with multiple examples)
        $sampleContent = $this->generateSampleDataFile($template);
        $samplePath = "{$baseDir}/sample.{$template->file_type}";
        Storage::disk('public')->put($samplePath, $sampleContent);
        
        // Generate instructions file
        $instructionsContent = $template->generateInstructions();
        $instructionsPath = "{$baseDir}/instructions.md";
        Storage::disk('public')->put($instructionsPath, $instructionsContent);
        
        // Update template with file paths
        $template->update([
            'template_file_path' => $templatePath,
            'sample_data_file_path' => $samplePath,
            'instructions_file_path' => $instructionsPath,
        ]);
    }

    /**
     * Generate sample data file with multiple examples
     */
    protected function generateSampleDataFile(ScheduleImportTemplate $template): string
    {
        switch ($template->file_type) {
            case 'csv':
                return $this->generateSampleCsv($template);
            case 'json':
                return $this->generateSampleJson($template);
            case 'txt':
                return $this->generateSampleText($template);
            case 'excel':
                return $this->generateSampleCsv($template); // Placeholder
            default:
                return '';
        }
    }

    /**
     * Generate sample CSV with multiple rows
     */
    protected function generateSampleCsv(ScheduleImportTemplate $template): string
    {
        $columns = $template->all_columns;
        $header = implode(',', $columns);
        
        $sampleRows = [
            $this->generateSampleRow($template, [
                'title' => 'Team Meeting',
                'description' => 'Weekly team sync meeting',
                'start_datetime' => '2024-01-15 09:00:00',
                'end_datetime' => '2024-01-15 10:00:00',
                'location' => 'Conference Room A',
                'priority' => 'High',
                'category' => 'Meeting',
                'keywords' => 'team, sync, weekly'
            ]),
            $this->generateSampleRow($template, [
                'title' => 'Client Presentation',
                'description' => 'Present quarterly results to client',
                'start_datetime' => '2024-01-16 14:00:00',
                'end_datetime' => '2024-01-16 15:30:00',
                'location' => 'Client Office',
                'priority' => 'High',
                'category' => 'Presentation',
                'keywords' => 'client, presentation, quarterly'
            ]),
            $this->generateSampleRow($template, [
                'title' => 'Training Session',
                'description' => 'New employee onboarding training',
                'start_datetime' => '2024-01-17 10:00:00',
                'end_datetime' => '2024-01-17 12:00:00',
                'location' => 'Training Room',
                'priority' => 'Medium',
                'category' => 'Training',
                'keywords' => 'training, onboarding, new employee'
            ]),
        ];
        
        return $header . "\n" . implode("\n", $sampleRows);
    }

    /**
     * Generate sample JSON with multiple entries
     */
    protected function generateSampleJson(ScheduleImportTemplate $template): string
    {
        $sampleData = [
            $this->generateSampleObject($template, [
                'title' => 'Team Meeting',
                'description' => 'Weekly team sync meeting',
                'start_datetime' => '2024-01-15 09:00:00',
                'end_datetime' => '2024-01-15 10:00:00',
                'location' => 'Conference Room A',
                'priority' => 'High',
                'category' => 'Meeting',
                'keywords' => 'team, sync, weekly'
            ]),
            $this->generateSampleObject($template, [
                'title' => 'Client Presentation',
                'description' => 'Present quarterly results to client',
                'start_datetime' => '2024-01-16 14:00:00',
                'end_datetime' => '2024-01-16 15:30:00',
                'location' => 'Client Office',
                'priority' => 'High',
                'category' => 'Presentation',
                'keywords' => 'client, presentation, quarterly'
            ]),
            $this->generateSampleObject($template, [
                'title' => 'Training Session',
                'description' => 'New employee onboarding training',
                'start_datetime' => '2024-01-17 10:00:00',
                'end_datetime' => '2024-01-17 12:00:00',
                'location' => 'Training Room',
                'priority' => 'Medium',
                'category' => 'Training',
                'keywords' => 'training, onboarding, new employee'
            ]),
        ];
        
        return json_encode($sampleData, JSON_PRETTY_PRINT);
    }

    /**
     * Generate sample text with multiple lines
     */
    protected function generateSampleText(ScheduleImportTemplate $template): string
    {
        $lines = [];
        $lines[] = "# Schedule Import Sample Data - Text Format";
        $lines[] = "# Each line represents one event";
        $lines[] = "# Format: Title | Description | Start Date | End Date | Location | Priority";
        $lines[] = "";
        
        $sampleEvents = [
            [
                'title' => 'Team Meeting',
                'description' => 'Weekly team sync meeting',
                'start_datetime' => '2024-01-15 09:00',
                'end_datetime' => '2024-01-15 10:00',
                'location' => 'Conference Room A',
                'priority' => 'High'
            ],
            [
                'title' => 'Client Presentation',
                'description' => 'Present quarterly results to client',
                'start_datetime' => '2024-01-16 14:00',
                'end_datetime' => '2024-01-16 15:30',
                'location' => 'Client Office',
                'priority' => 'High'
            ],
            [
                'title' => 'Training Session',
                'description' => 'New employee onboarding training',
                'start_datetime' => '2024-01-17 10:00',
                'end_datetime' => '2024-01-17 12:00',
                'location' => 'Training Room',
                'priority' => 'Medium'
            ],
        ];
        
        foreach ($sampleEvents as $event) {
            $line = implode(' | ', [
                $event['title'],
                $event['description'],
                $event['start_datetime'],
                $event['end_datetime'],
                $event['location'],
                $event['priority']
            ]);
            $lines[] = $line;
        }
        
        return implode("\n", $lines);
    }

    /**
     * Generate a sample row for CSV
     */
    protected function generateSampleRow(ScheduleImportTemplate $template, array $sampleData): string
    {
        $columns = $template->all_columns;
        $row = [];
        
        foreach ($columns as $column) {
            $value = $sampleData[$column] ?? $template->getSampleValueForColumn($column);
            $row[] = '"' . str_replace('"', '""', $value) . '"';
        }
        
        return implode(',', $row);
    }

    /**
     * Generate a sample object for JSON
     */
    protected function generateSampleObject(ScheduleImportTemplate $template, array $sampleData): array
    {
        $columns = $template->all_columns;
        $object = [];
        
        foreach ($columns as $column) {
            $object[$column] = $sampleData[$column] ?? $template->getSampleValueForColumn($column);
        }
        
        return $object;
    }

    /**
     * Create default templates for a profession
     */
    public function createDefaultTemplatesForProfession(int $professionId, int $createdBy): void
    {
        $templates = [
            [
                'template_name' => 'Medical Schedule CSV',
                'template_description' => 'Standard CSV template for medical professionals scheduling',
                'file_type' => 'csv',
                'required_columns' => ['title', 'start_datetime', 'end_datetime'],
                'optional_columns' => ['description', 'location', 'priority', 'patient_id'],
                'sample_title' => 'Patient Consultation',
                'sample_description' => 'Follow-up consultation with patient',
                'sample_start_datetime' => '2024-01-15 09:00:00',
                'sample_end_datetime' => '2024-01-15 09:30:00',
                'sample_location' => 'Room 102',
                'sample_priority' => 'High',
                'date_format_example' => 'YYYY-MM-DD HH:mm:ss',
                'time_format_example' => 'HH:mm:ss',
                'ai_keywords_examples' => [
                    'urgent' => ['urgent', 'emergency', 'critical', 'asap'],
                    'routine' => ['routine', 'regular', 'standard', 'normal'],
                    'follow_up' => ['follow-up', 'follow up', 'review', 'check-up']
                ],
                'priority_detection_rules' => [
                    'High' => ['urgent', 'emergency', 'critical'],
                    'Medium' => ['follow-up', 'consultation'],
                    'Low' => ['routine', 'regular']
                ],
                'is_default' => true,
            ],
            [
                'template_name' => 'Medical Schedule JSON',
                'template_description' => 'JSON format template for medical appointment scheduling',
                'file_type' => 'json',
                'required_columns' => ['title', 'start_datetime', 'end_datetime'],
                'optional_columns' => ['description', 'location', 'priority', 'patient_id', 'notes'],
                'sample_title' => 'Surgery Consultation',
                'sample_description' => 'Pre-operative consultation',
                'sample_start_datetime' => '2024-01-15 14:00:00',
                'sample_end_datetime' => '2024-01-15 15:00:00',
                'sample_location' => 'Consultation Room 3',
                'sample_priority' => 'High',
                'date_format_example' => 'YYYY-MM-DD HH:mm:ss',
                'time_format_example' => 'HH:mm:ss',
                'ai_keywords_examples' => [
                    'surgery' => ['surgery', 'operation', 'procedure'],
                    'consultation' => ['consultation', 'meeting', 'appointment'],
                    'emergency' => ['emergency', 'urgent', 'stat']
                ],
                'is_default' => false,
            ],
        ];

        foreach ($templates as $templateData) {
            $templateData['profession_id'] = $professionId;
            $templateData['created_by'] = $createdBy;
            $templateData['column_descriptions'] = $this->getColumnDescriptions();
            
            $template = ScheduleImportTemplate::create($templateData);
            
            // Generate files for the template
            $this->generateTemplateFiles($template);
        }
    }

    /**
     * Get standard column descriptions
     */
    protected function getColumnDescriptions(): array
    {
        return [
            'title' => 'Event title or subject (required)',
            'description' => 'Detailed description of the event',
            'start_datetime' => 'Start date and time (required)',
            'end_datetime' => 'End date and time (required)',
            'location' => 'Event location or venue',
            'priority' => 'Priority level (High, Medium, Low)',
            'category' => 'Event category or type',
            'keywords' => 'Keywords for AI detection (comma-separated)',
            'patient_id' => 'Patient ID (for medical appointments)',
            'notes' => 'Additional notes or comments',
        ];
    }

    /**
     * Generate templates for all professions
     */
    public function generateTemplatesForAllProfessions(int $createdBy): void
    {
        // This would typically iterate through all professions
        // For now, we'll create some basic templates
        
        $globalTemplates = [
            [
                'template_name' => 'Basic Schedule CSV',
                'template_description' => 'Basic CSV template for general schedule import',
                'file_type' => 'csv',
                'required_columns' => ['title', 'start_datetime'],
                'optional_columns' => ['description', 'end_datetime', 'location', 'priority'],
                'sample_title' => 'Important Meeting',
                'sample_description' => 'Team meeting to discuss project progress',
                'sample_start_datetime' => '2024-01-15 10:00:00',
                'sample_end_datetime' => '2024-01-15 11:00:00',
                'sample_location' => 'Meeting Room 1',
                'sample_priority' => 'Medium',
                'date_format_example' => 'YYYY-MM-DD HH:mm:ss or DD/MM/YYYY HH:mm',
                'time_format_example' => 'HH:mm:ss or HH:mm',
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'template_name' => 'Basic Schedule JSON',
                'template_description' => 'JSON template for general schedule import',
                'file_type' => 'json',
                'required_columns' => ['title', 'start_datetime'],
                'optional_columns' => ['description', 'end_datetime', 'location', 'priority', 'category'],
                'sample_title' => 'Project Review',
                'sample_description' => 'Review project milestones and deliverables',
                'sample_start_datetime' => '2024-01-16 15:00:00',
                'sample_end_datetime' => '2024-01-16 16:30:00',
                'sample_location' => 'Conference Room B',
                'sample_priority' => 'High',
                'date_format_example' => 'YYYY-MM-DD HH:mm:ss',
                'time_format_example' => 'HH:mm:ss',
                'is_default' => false,
                'is_active' => true,
            ],
        ];

        foreach ($globalTemplates as $templateData) {
            $templateData['profession_id'] = null; // Global template
            $templateData['created_by'] = $createdBy;
            $templateData['column_descriptions'] = $this->getColumnDescriptions();
            $templateData['ai_keywords_examples'] = [
                'urgent' => ['urgent', 'asap', 'critical', 'important'],
                'meeting' => ['meeting', 'conference', 'discussion', 'sync'],
                'deadline' => ['deadline', 'due', 'deliverable', 'milestone']
            ];
            $templateData['priority_detection_rules'] = [
                'High' => ['urgent', 'critical', 'important', 'asap'],
                'Medium' => ['meeting', 'review', 'sync'],
                'Low' => ['optional', 'nice to have', 'low priority']
            ];
            
            $template = ScheduleImportTemplate::create($templateData);
            
            // Generate files for the template
            $this->generateTemplateFiles($template);
        }
    }

    /**
     * Regenerate files for all templates
     */
    public function regenerateAllTemplateFiles(): void
    {
        $templates = ScheduleImportTemplate::active()->get();
        
        foreach ($templates as $template) {
            try {
                $this->generateTemplateFiles($template);
            } catch (\Exception $e) {
                // Log error but continue with other templates
                \Log::error("Failed to regenerate files for template {$template->id}: " . $e->getMessage());
            }
        }
    }
}