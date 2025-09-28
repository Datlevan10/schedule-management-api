<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSchedulePreferencesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'preferred_import_format' => $this->preferred_import_format,
            'default_template_id' => $this->default_template_id,
            'default_template' => $this->whenLoaded('defaultTemplate', function () {
                return [
                    'id' => $this->defaultTemplate->id,
                    'name' => $this->defaultTemplate->name,
                    'template_type' => $this->defaultTemplate->template_type,
                ];
            }),
            'timezone_preference' => $this->timezone_preference,
            'date_format_preference' => $this->date_format_preference,
            'time_format_preference' => $this->time_format_preference,
            'ai_settings' => [
                'auto_categorize' => $this->ai_auto_categorize,
                'auto_priority' => $this->ai_auto_priority,
                'confidence_threshold' => $this->ai_confidence_threshold,
            ],
            'defaults' => [
                'event_duration_minutes' => $this->default_event_duration_minutes,
                'priority' => $this->default_priority,
                'category_id' => $this->default_category_id,
                'category' => $this->whenLoaded('defaultCategory', function () {
                    return [
                        'id' => $this->defaultCategory->id,
                        'name' => $this->defaultCategory->name,
                        'color' => $this->defaultCategory->color,
                    ];
                }),
            ],
            'notifications' => [
                'import_completion' => $this->notify_on_import_completion,
                'parsing_errors' => $this->notify_on_parsing_errors,
            ],
            'customizations' => [
                'field_mappings' => $this->custom_field_mappings,
                'keywords' => $this->custom_keywords,
                'keywords_count' => is_array($this->custom_keywords) ? count($this->custom_keywords) : 0,
            ],
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'summary' => [
                'has_custom_template' => !is_null($this->default_template_id),
                'has_custom_keywords' => !empty($this->custom_keywords),
                'has_custom_mappings' => !empty($this->custom_field_mappings),
                'ai_enabled' => $this->ai_auto_categorize || $this->ai_auto_priority,
            ],
        ];
    }
}