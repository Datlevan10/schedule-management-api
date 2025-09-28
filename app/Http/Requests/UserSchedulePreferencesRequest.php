<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserSchedulePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'preferred_import_format' => 'required|in:csv,excel,json,txt',
            'default_template_id' => 'nullable|exists:schedule_templates,id',
            'timezone_preference' => 'nullable|string|max:50',
            'date_format_preference' => 'nullable|string|max:20',
            'time_format_preference' => 'nullable|string|max:20',
            'ai_auto_categorize' => 'nullable|boolean',
            'ai_auto_priority' => 'nullable|boolean',
            'ai_confidence_threshold' => 'nullable|numeric|between:0,1',
            'default_event_duration_minutes' => 'nullable|integer|min:5|max:1440',
            'default_priority' => 'nullable|integer|min:1|max:5',
            'default_category_id' => 'nullable|exists:event_categories,id',
            'notify_on_import_completion' => 'nullable|boolean',
            'notify_on_parsing_errors' => 'nullable|boolean',
            'custom_field_mappings' => 'nullable|array',
            'custom_keywords' => 'nullable|array',
            'custom_keywords.*' => 'string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'preferred_import_format.required' => 'Import format preference is required',
            'preferred_import_format.in' => 'Import format must be one of: csv, excel, json, txt',
            'default_template_id.exists' => 'Selected template does not exist',
            'ai_confidence_threshold.between' => 'AI confidence threshold must be between 0 and 1',
            'default_event_duration_minutes.min' => 'Event duration must be at least 5 minutes',
            'default_event_duration_minutes.max' => 'Event duration cannot exceed 24 hours (1440 minutes)',
            'default_priority.min' => 'Priority must be between 1 and 5',
            'default_priority.max' => 'Priority must be between 1 and 5',
            'default_category_id.exists' => 'Selected category does not exist',
            'custom_keywords.*.max' => 'Each keyword cannot exceed 100 characters',
        ];
    }
}