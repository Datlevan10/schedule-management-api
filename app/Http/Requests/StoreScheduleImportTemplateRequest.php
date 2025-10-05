<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleImportTemplateRequest extends FormRequest
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
            'template_name' => 'required|string|max:255',
            'template_description' => 'nullable|string',
            'file_type' => 'required|in:csv,xlsx,xls',
            'required_columns' => 'required|array',
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
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'generate_files' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'profession_id.exists' => 'The selected profession does not exist.',
            'template_name.required' => 'Template name is required.',
            'file_type.required' => 'File type is required.',
            'file_type.in' => 'File type must be one of: csv, xlsx, xls.',
            'required_columns.required' => 'At least one required column must be specified.',
            'required_columns.array' => 'Required columns must be an array.',
            'sample_priority.between' => 'Priority must be between 1 and 5.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values if not provided
        $this->merge([
            'is_active' => $this->is_active ?? true,
            'is_default' => $this->is_default ?? false,
            'sample_priority' => $this->sample_priority ?? 1,
        ]);
    }
}