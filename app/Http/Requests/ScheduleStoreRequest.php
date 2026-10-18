<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => '请选择人员',
            'user_id.exists' => '人员不存在',
            'date.required' => '请选择日期',
            'date.date_format' => '日期格式错误',
            'shift_id.required' => '请选择班次',
            'shift_id.exists' => '班次不存在',
        ];
    }
}
