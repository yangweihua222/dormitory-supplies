<?php

namespace App\Http\Requests;

use App\Enums\Identity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'username' => ['required', 'string', 'max:50'],
            'employee_no' => ['required', 'string', 'max:32', Rule::unique('users', 'employee_no')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'identity' => ['required', new Enum(Identity::class)],
            'password' => ['nullable', 'string', 'min:6', 'max:64'],
            'menu_keys' => ['required', 'array', 'min:1'],
            'menu_keys.*' => ['string', Rule::exists('menus', 'key')],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => '请输入用户名',
            'employee_no.required' => '请输入工号',
            'employee_no.unique' => '该工号已存在',
            'identity.required' => '请选择身份',
            'password.min' => '密码至少 6 位',
            'menu_keys.required' => '请勾选菜单权限',
            'menu_keys.min' => '请至少勾选一项菜单权限',
        ];
    }
}
