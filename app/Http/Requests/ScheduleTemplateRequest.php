<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'profession_id' => 'nullable|exists:professions,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'template_type' => 'required|in:csv,excel,json,text',
            'field_mapping' => 'required|array',
            'field_mapping.*' => 'string',
            'required_fields' => 'required|array',
            'required_fields.*' => 'string',
            'optional_fields' => 'nullable|array',
            'optional_fields.*' => 'string',
            'default_values' => 'nullable|array',
            'date_formats' => 'nullable|array',
            'date_formats.*' => 'string',
            'time_formats' => 'nullable|array', 
            'time_formats.*' => 'string',
            'keyword_patterns' => 'nullable|array',
            'validation_rules' => 'nullable|array',
            'ai_processing_rules' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Template name is required',
            'template_type.required' => 'Template type is required',
            'template_type.in' => 'Template type must be one of: csv, excel, json, text',
            'field_mapping.required' => 'Field mapping is required',
            'required_fields.required' => 'Required fields list is required',
            'profession_id.exists' => 'Selected profession does not exist',
        ];
    }
}