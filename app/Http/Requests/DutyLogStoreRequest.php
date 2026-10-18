<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DutyLogStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'log_date' => ['required', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:undone,done'],
        ];
    }

    public function messages(): array
    {
        return [
            'log_date.required' => '请选择日志日期',
            'log_date.date_format' => '日期格式错误',
            'status.in' => '状态值错误',
        ];
    }
}
