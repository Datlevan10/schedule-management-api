<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'profession_id' => $this->profession_id,
            'profession' => $this->whenLoaded('profession', function () {
                return [
                    'id' => $this->profession->id,
                    'name' => $this->profession->name,
                ];
            }),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'name' => $this->name,
            'description' => $this->description,
            'template_type' => $this->template_type,
            'field_mapping' => $this->field_mapping,
            'required_fields' => $this->required_fields,
            'optional_fields' => $this->optional_fields,
            'default_values' => $this->default_values,
            'date_formats' => $this->date_formats,
            'time_formats' => $this->time_formats,
            'keyword_patterns' => $this->keyword_patterns,
            'validation_rules' => $this->validation_rules,
            'ai_processing_rules' => $this->ai_processing_rules,
            'usage_count' => $this->usage_count,
            'success_rate' => $this->success_rate ? round($this->success_rate, 2) : null,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'stats' => [
                'total_uses' => $this->usage_count,
                'success_percentage' => $this->success_rate ? round($this->success_rate, 1) : 0,
                'is_popular' => $this->usage_count > 50,
                'is_reliable' => $this->success_rate && $this->success_rate >= 0.8,
            ],
        ];
    }
}