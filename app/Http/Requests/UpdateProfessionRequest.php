<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfessionRequest extends FormRequest
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
        $professionId = $this->route('profession');
        
        return [
            'name' => 'sometimes|string|max:255|unique:professions,name,' . $professionId,
            'display_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'default_categories' => 'nullable|array',
            'default_priorities' => 'nullable|array',
            'ai_keywords' => 'nullable|array',
        ];
    }
}
