<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFeatureHighlightRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Add proper admin authorization later
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'icon_file' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            'icon_url' => 'nullable|string|url|max:255',
            'order' => 'sometimes|required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'is_active' => $this->toBoolean($this->is_active),
            'order' => $this->input('order') ? (int) $this->input('order') : null,
        ]);
    }

    /**
     * Convert string to boolean for validation
     */
    private function toBoolean($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        if (is_bool($value)) {
            return $value;
        }
        
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Feature title is required.',
            'title.max' => 'Feature title must not exceed 255 characters.',
            'description.required' => 'Feature description is required.',
            'icon_file.image' => 'Icon file must be an image.',
            'icon_file.mimes' => 'Icon file must be a file of type: jpeg, png, jpg, svg.',
            'icon_file.max' => 'Icon file must not exceed 2MB.',
            'icon_url.url' => 'Icon URL must be a valid URL.',
            'order.required' => 'Display order is required.',
            'order.min' => 'Display order must be at least 1.',
        ];
    }
}
