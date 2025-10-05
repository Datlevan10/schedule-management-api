<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleImportTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'profession_id' => 'nullable|exists:professions,id',
            'template_name' => 'sometimes|string|max:255',
            'template_description' => 'nullable|string',
            'file_type' => 'sometimes|in:csv,xlsx,xls',
            'required_columns' => 'sometimes|array',
            'required_columns.*' => 'string',
            'optional_columns' => 'nullable|array',
            'optional_columns.*' => 'string',
            'column_descriptions' => 'nullable|array',
            'column_descriptions.*' => 'string',
            'template_file_path' => 'nullable|string|max:500',
            'sample_data_file_path' => 'nullable|string|max:500',
            'instructions_file_path' => 'nullable|string|max:500',
            'sample_title' => 'nullable|string|max:255',
            'sample_description' => 'nullable|string',
            'sample_location' => 'nullable|string|max:255',
            'sample_keywords' => 'nullable|array',
            'sample_keywords.*' => 'string',
            'sample_category' => 'nullable|string|max:100',
            'sample_priority' => 'nullable|integer|between:1,5',
            'ai_keywords_examples' => 'nullable|array',
            'priority_detection_rules' => 'nullable|array',
            'category_mapping_examples' => 'nullable|array',
            'download_count' => 'nullable|integer|min:0',
            'success_import_rate' => 'nullable|numeric|between:0,100',
            'user_feedback_rating' => 'nullable|numeric|between:0,5',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'regenerate_files' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'profession_id.exists' => 'The selected profession does not exist.',
            'file_type.in' => 'File type must be one of: csv, xlsx, xls.',
            'required_columns.array' => 'Required columns must be an array.',
            'sample_priority.between' => 'Priority must be between 1 and 5.',
            'success_import_rate.between' => 'Success import rate must be between 0 and 100.',
            'user_feedback_rating.between' => 'User feedback rating must be between 0 and 5.',
        ];
    }
}