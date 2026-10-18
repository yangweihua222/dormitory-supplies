<?php

namespace App\Http\Requests;

use App\Enums\ShiftType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ShiftUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $shiftId = $this->route('shift')?->id;

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('shifts', 'name')->whereNull('deleted_at')->ignore($shiftId)],
            'check_in_time' => ['required', 'date_format:H:i:s'],
            'check_out_time' => ['required', 'date_format:H:i:s'],
            'type' => ['required', new Enum(ShiftType::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '请输入班次名称',
            'name.unique' => '该班次名称已存在',
            'check_in_time.required' => '请选择签到时间',
            'check_in_time.date_format' => '签到时间格式错误',
            'check_out_time.required' => '请选择签退时间',
            'check_out_time.date_format' => '签退时间格式错误',
            'type.required' => '请选择班次类型',
        ];
    }
}
