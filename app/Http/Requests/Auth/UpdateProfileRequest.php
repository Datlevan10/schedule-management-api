<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'name' => 'sometimes|required|string|max:255',
            'profession_id' => 'sometimes|required|exists:professions,id',
            'profession_level' => 'nullable|in:student,resident,junior,senior,expert',
            'workplace' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:100',
            'work_schedule' => 'nullable|array',
            'work_habits' => 'nullable|array',
            'notification_preferences' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required',
            'profession_id.required' => 'Please select your profession',
            'profession_id.exists' => 'Selected profession is invalid',
            'profession_level.in' => 'Invalid profession level selected',
        ];
    }
}
