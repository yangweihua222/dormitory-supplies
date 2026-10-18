<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DutyLogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'in:phone,consult,abnormal'],
            // 电话接听
            'caller' => ['nullable', 'string', 'max:100'],
            'is_need_callback' => ['nullable', 'boolean'],
            'contact' => ['nullable', 'string', 'max:50'],
            'is_recalled' => ['nullable', 'boolean'],
            // 咨询接待
            'visitor' => ['nullable', 'string', 'max:100'],
            'matter' => ['nullable', 'string', 'max:255'],
            'is_need_transfer' => ['nullable', 'boolean'],
            'transfer_to' => ['nullable', 'string', 'max:100'],
            'is_finished' => ['nullable', 'boolean'],
            // 异常情况
            'description' => ['nullable', 'string'],
            'is_reported' => ['nullable', 'boolean'],
            'handling' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'category.required' => '请选择分类',
            'category.in' => '分类值错误',
        ];
    }
}
