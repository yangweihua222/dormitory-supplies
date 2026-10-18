<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_no' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:64'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_no.required' => '请输入工号',
            'password.required' => '请输入密码',
        ];
    }
}
