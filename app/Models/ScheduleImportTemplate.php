<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ScheduleImportTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'profession_id',
        'template_name',
        'template_description',
        'file_type',
        'template_version',
        'sample_title',
        'sample_description',
        'sample_start_datetime',
        'sample_end_datetime',
        'sample_location',
        'sample_priority',
        'sample_category',
        'sample_keywords',
        'date_format_example',
        'time_format_example',
        'required_columns',
        'optional_columns',
        'column_descriptions',
        'template_file_path',
        'sample_data_file_path',
        'instructions_file_path',
        'ai_keywords_examples',
        'priority_detection_rules',
        'category_mapping_examples',
        'download_count',
        'success_import_rate',
        'user_feedback_rating',
        'is_active',
        'is_default',
        'created_by',
    ];

    protected $casts = [
        'required_columns' => 'array',
        'optional_columns' => 'array',
        'column_descriptions' => 'array',
        'ai_keywords_examples' => 'array',
        'priority_detection_rules' => 'array',
        'category_mapping_examples' => 'array',
        'success_import_rate' => 'decimal:2',
        'user_feedback_rating' => 'decimal:1',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get the profession this template belongs to
     */
    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class);
    }

    /**
     * Get the user who created this template
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default templates
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope for specific file type
     */
    public function scopeFileType($query, $type)
    {
        return $query->where('file_type', $type);
    }

    /**
     * Scope for profession-specific templates
     */
    public function scopeForProfession($query, $professionId)
    {
        return $query->where('profession_id', $professionId);
    }

    /**
     * Scope for global templates (no profession)
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('profession_id');
    }

    /**
     * Scope for applicable templates (global + profession-specific)
     */
    public function scopeApplicableFor($query, $professionId)
    {
        return $query->where(function ($q) use ($professionId) {
            $q->whereNull('profession_id')
              ->orWhere('profession_id', $professionId);
        });
    }

    /**
     * Get all column names (required + optional)
     */
    public function getAllColumnsAttribute(): array
    {
        return array_merge(
            $this->required_columns ?? [],
            $this->optional_columns ?? []
        );
    }

    /**
     * Get sample data row
     */
    public function getSampleDataAttribute(): array
    {
        return [
            'title' => $this->sample_title,
            'description' => $this->sample_description,
            'start_datetime' => $this->sample_start_datetime,
            'end_datetime' => $this->sample_end_datetime,
            'location' => $this->sample_location,
            'priority' => $this->sample_priority,
            'category' => $this->sample_category,
            'keywords' => $this->sample_keywords,
        ];
    }

    /**
     * Increment download count
     */
    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
    }

    /**
     * Update success rate
     */
    public function updateSuccessRate(float $rate): void
    {
        $this->update(['success_import_rate' => round($rate, 2)]);
    }

    /**
     * Update user feedback rating
     */
    public function updateUserRating(float $rating): void
    {
        // This would typically calculate an average from multiple ratings
        $this->update(['user_feedback_rating' => round($rating, 1)]);
    }

    /**
     * Generate template file content based on file type
     */
    public function generateTemplateContent(): string
    {
        switch ($this->file_type) {
            case 'csv':
                return $this->generateCsvTemplate();
            case 'json':
                return $this->generateJsonTemplate();
            case 'txt':
                return $this->generateTextTemplate();
            case 'excel':
                return $this->generateExcelTemplate();
            default:
                return '';
        }
    }

    /**
     * Generate CSV template
     */
    protected function generateCsvTemplate(): string
    {
        $columns = $this->all_columns;
        $header = implode(',', $columns);
        
        // Add sample data row
        $sampleRow = [];
        foreach ($columns as $column) {
            $sampleRow[] = $this->getSampleValueForColumn($column);
        }
        
        return $header . "\n" . implode(',', $sampleRow);
    }

    /**
     * Generate JSON template
     */
    protected function generateJsonTemplate(): string
    {
        $sampleData = [];
        foreach ($this->all_columns as $column) {
            $sampleData[$column] = $this->getSampleValueForColumn($column);
        }
        
        return json_encode([$sampleData], JSON_PRETTY_PRINT);
    }

    /**
     * Generate text template
     */
    protected function generateTextTemplate(): string
    {
        $lines = [];
        $lines[] = "# Schedule Import Template - Text Format";
        $lines[] = "# Each line represents one event";
        $lines[] = "# Format: Title | Description | Start Date | End Date | Location | Priority";
        $lines[] = "";
        
        $sampleLine = implode(' | ', [
            $this->sample_title ?? 'Sample Event Title',
            $this->sample_description ?? 'Sample description',
            $this->sample_start_datetime ?? '2024-01-15 09:00',
            $this->sample_end_datetime ?? '2024-01-15 10:00',
            $this->sample_location ?? 'Conference Room A',
            $this->sample_priority ?? 'High'
        ]);
        
        $lines[] = $sampleLine;
        
        return implode("\n", $lines);
    }

    /**
     * Generate Excel template (placeholder)
     */
    protected function generateExcelTemplate(): string
    {
        // For Excel, we'd use a library like PhpSpreadsheet
        // For now, return CSV format
        return $this->generateCsvTemplate();
    }

    /**
     * Get sample value for a column
     */
    protected function getSampleValueForColumn(string $column): string
    {
        $mapping = [
            'title' => $this->sample_title ?? 'Sample Event Title',
            'description' => $this->sample_description ?? 'Sample event description',
            'start_datetime' => $this->sample_start_datetime ?? '2024-01-15 09:00:00',
            'end_datetime' => $this->sample_end_datetime ?? '2024-01-15 10:00:00',
            'location' => $this->sample_location ?? 'Conference Room A',
            'priority' => $this->sample_priority ?? 'High',
            'category' => $this->sample_category ?? 'Meeting',
            'keywords' => $this->sample_keywords ?? 'urgent, meeting, team',
        ];
        
        return $mapping[$column] ?? "Sample {$column}";
    }

    /**
     * Generate instructions content
     */
    public function generateInstructions(): string
    {
        $instructions = [];
        
        $instructions[] = "# {$this->template_name} - Import Instructions";
        $instructions[] = "";
        $instructions[] = "## Description";
        $instructions[] = $this->template_description ?? "No description available.";
        $instructions[] = "";
        
        $instructions[] = "## File Format";
        $instructions[] = "- File Type: " . strtoupper($this->file_type);
        $instructions[] = "- Version: {$this->template_version}";
        $instructions[] = "";
        
        if ($this->date_format_example) {
            $instructions[] = "## Date Format";
            $instructions[] = "Use format: {$this->date_format_example}";
            $instructions[] = "";
        }
        
        if ($this->time_format_example) {
            $instructions[] = "## Time Format";
            $instructions[] = "Use format: {$this->time_format_example}";
            $instructions[] = "";
        }
        
        if ($this->required_columns) {
            $instructions[] = "## Required Columns";
            foreach ($this->required_columns as $column) {
                $description = $this->column_descriptions[$column] ?? "No description";
                $instructions[] = "- **{$column}**: {$description}";
            }
            $instructions[] = "";
        }
        
        if ($this->optional_columns) {
            $instructions[] = "## Optional Columns";
            foreach ($this->optional_columns as $column) {
                $description = $this->column_descriptions[$column] ?? "No description";
                $instructions[] = "- **{$column}**: {$description}";
            }
            $instructions[] = "";
        }
        
        if ($this->ai_keywords_examples) {
            $instructions[] = "## AI Keywords Examples";
            $instructions[] = "The AI system recognizes these keywords for better parsing:";
            foreach ($this->ai_keywords_examples as $category => $keywords) {
                $keywordList = is_array($keywords) ? implode(', ', $keywords) : $keywords;
                $instructions[] = "- **{$category}**: {$keywordList}";
            }
            $instructions[] = "";
        }
        
        if ($this->priority_detection_rules) {
            $instructions[] = "## Priority Detection";
            $instructions[] = "AI detects priority based on these rules:";
            foreach ($this->priority_detection_rules as $priority => $rules) {
                $ruleText = is_array($rules) ? implode(', ', $rules) : $rules;
                $instructions[] = "- **{$priority}**: {$ruleText}";
            }
            $instructions[] = "";
        }
        
        return implode("\n", $instructions);
    }

    /**
     * Check if template has files generated
     */
    public function hasGeneratedFiles(): bool
    {
        return !empty($this->template_file_path) && 
               !empty($this->sample_data_file_path) && 
               !empty($this->instructions_file_path);
    }

    /**
     * Get download URL for template file
     */
    public function getTemplateDownloadUrl(): ?string
    {
        if (!$this->template_file_path) {
            return null;
        }
        
        return Storage::disk('public')->url($this->template_file_path);
    }

    /**
     * Get download URL for sample data file
     */
    public function getSampleDataDownloadUrl(): ?string
    {
        if (!$this->sample_data_file_path) {
            return null;
        }
        
        return Storage::disk('public')->url($this->sample_data_file_path);
    }

    /**
     * Get download URL for instructions file
     */
    public function getInstructionsDownloadUrl(): ?string
    {
        if (!$this->instructions_file_path) {
            return null;
        }
        
        return Storage::disk('public')->url($this->instructions_file_path);
    }
}