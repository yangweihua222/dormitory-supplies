<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => '请选择人员',
            'user_ids.min' => '至少选择一名人员',
            'user_ids.*.exists' => '人员不存在',
            'start_date.required' => '请选择开始日期',
            'end_date.required' => '请选择结束日期',
            'end_date.after_or_equal' => '结束日期不能早于开始日期',
            'shift_id.required' => '请选择班次',
        ];
    }
}
