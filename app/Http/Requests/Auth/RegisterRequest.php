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
            'name.required' => 'Tên là bắt buộc',
            'email.required' => 'Email là bắt buộc',
            'email.unique' => 'Email này đã được đăng ký',
            'password.required' => 'Mật khẩu là bắt buộc',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự',
            'password.confirmed' => 'Mật khẩu không khớp',
            'password.regex' => 'Mật khẩu phải chứa ít nhất một chữ cái viết hoa, một chữ cái viết thường và một số',
            'profession_id.required' => 'Vui lòng chọn nghề nghiệp của bạn',
            'profession_id.exists' => 'Nghề nghiệp đã chọn không hợp lệ',
            'profession_level.in' => 'Cấp bậc nghề nghiệp đã chọn không hợp lệ',
        ];
    }
}
