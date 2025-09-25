<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
            'profession_id' => 'required|exists:professions,id',
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
            'email.required' => 'Email is required',
            'email.unique' => 'This email is already registered',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'password.confirmed' => 'Password confirmation does not match',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number',
            'profession_id.required' => 'Please select your profession',
            'profession_id.exists' => 'Selected profession is invalid',
            'profession_level.in' => 'Invalid profession level selected',
        ];
    }
}