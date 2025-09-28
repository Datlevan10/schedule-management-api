<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ParsingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rule_name' => 'required|string|max:255',
            'profession_id' => 'nullable|exists:professions,id',
            'rule_type' => 'required|in:keyword_detection,pattern_matching,priority_calculation,category_assignment',
            'rule_pattern' => 'required|string',
            'rule_action' => 'required|array',
            'conditions' => 'nullable|array',
            'priority_order' => 'nullable|integer|min:1|max:1000',
            'positive_examples' => 'nullable|array',
            'positive_examples.*' => 'string',
            'negative_examples' => 'nullable|array',
            'negative_examples.*' => 'string',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'rule_name.required' => 'Rule name is required',
            'rule_name.max' => 'Rule name cannot exceed 255 characters',
            'profession_id.exists' => 'Selected profession does not exist',
            'rule_type.required' => 'Rule type is required',
            'rule_type.in' => 'Rule type must be one of: keyword_detection, pattern_matching, priority_calculation, category_assignment',
            'rule_pattern.required' => 'Rule pattern is required',
            'rule_action.required' => 'Rule action is required',
            'rule_action.array' => 'Rule action must be a valid JSON object',
            'priority_order.min' => 'Priority order must be at least 1',
            'priority_order.max' => 'Priority order cannot exceed 1000',
            'positive_examples.array' => 'Positive examples must be an array',
            'negative_examples.array' => 'Negative examples must be an array',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('rule_pattern')) {
            $pattern = $this->input('rule_pattern');
            
            // Validate regex pattern if it looks like one
            if (preg_match('/^\/.*\/[gimx]*$/', $pattern)) {
                try {
                    preg_match($pattern, 'test');
                } catch (\Exception $e) {
                    $this->merge([
                        'rule_pattern_error' => 'Invalid regex pattern: ' . $e->getMessage()
                    ]);
                }
            }
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('rule_pattern_error')) {
                $validator->errors()->add('rule_pattern', $this->input('rule_pattern_error'));
            }
        });
    }
}