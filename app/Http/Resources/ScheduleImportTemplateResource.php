<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleImportTemplateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'profession_id' => $this->profession_id,
            'template_name' => $this->template_name,
            'template_description' => $this->template_description,
            'file_type' => $this->file_type,
            'template_version' => $this->template_version,
            
            'sample_data' => [
                'title' => $this->sample_title,
                'description' => $this->sample_description,
                'start_datetime' => $this->sample_start_datetime,
                'end_datetime' => $this->sample_end_datetime,
                'location' => $this->sample_location,
                'priority' => $this->sample_priority,
                'category' => $this->sample_category,
                'keywords' => $this->sample_keywords,
            ],
            
            'format_specifications' => [
                'date_format_example' => $this->date_format_example,
                'time_format_example' => $this->time_format_example,
                'required_columns' => $this->required_columns,
                'optional_columns' => $this->optional_columns,
                'column_descriptions' => $this->column_descriptions,
                'all_columns' => $this->all_columns,
            ],
            
            'ai_processing_info' => [
                'keywords_examples' => $this->ai_keywords_examples,
                'priority_detection_rules' => $this->priority_detection_rules,
                'category_mapping_examples' => $this->category_mapping_examples,
            ],
            
            'usage_statistics' => [
                'download_count' => $this->download_count,
                'success_import_rate' => $this->success_import_rate,
                'user_feedback_rating' => $this->user_feedback_rating,
                'popularity_score' => $this->when($this->download_count > 0, function () {
                    return round(($this->download_count * 0.7) + (($this->user_feedback_rating ?? 0) * 0.3), 2);
                }),
            ],
            
            'file_information' => [
                'has_generated_files' => $this->hasGeneratedFiles(),
                'template_file_path' => $this->template_file_path,
                'sample_data_file_path' => $this->sample_data_file_path,
                'instructions_file_path' => $this->instructions_file_path,
                'download_urls' => [
                    'template' => $this->getTemplateDownloadUrl(),
                    'sample_data' => $this->getSampleDataDownloadUrl(),
                    'instructions' => $this->getInstructionsDownloadUrl(),
                ],
            ],
            
            'status' => [
                'is_active' => $this->is_active,
                'is_default' => $this->is_default,
                'recommended' => $this->when($this->success_import_rate >= 80 && $this->download_count >= 10, true),
            ],
            
            'profession' => $this->when($this->relationLoaded('profession'), function () {
                return [
                    'id' => $this->profession->id,
                    'name' => $this->profession->name,
                    'display_name' => $this->profession->display_name,
                ];
            }),
            
            'creator' => $this->when($this->relationLoaded('creator'), function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ];
            }),
            
            'metadata' => [
                'created_at' => $this->created_at->toIso8601String(),
                'updated_at' => $this->updated_at->toIso8601String(),
                'created_at_human' => $this->created_at->diffForHumans(),
                'updated_at_human' => $this->updated_at->diffForHumans(),
            ],
        ];
    }
}