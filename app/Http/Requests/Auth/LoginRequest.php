<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Bắt ​​buộc phải có email',
            'email.email' => 'Vui lòng nhập địa chỉ email hợp lệ',
            'email.exists' => 'Không tìm thấy tài khoản nào với địa chỉ email này',
            'password.required' => 'Bắt ​​buộc phải có mật khẩu',
        ];
    }
}
