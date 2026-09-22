<?php

namespace App\Domains\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,'.$this->user()->id],
            'current_password' => ['required_with:password', 'string'],
            'password' => ['sometimes', 'required', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'نام نمی‌تواند خالی باشد.',
            'name.max' => 'نام نمی‌تواند بیش از ۱۰۰ کاراکتر باشد.',
            'email.required' => 'ایمیل نمی‌تواند خالی باشد.',
            'email.email' => 'فرمت ایمیل واردشده نامعتبر است.',
            'email.unique' => 'این ایمیل توسط کاربر دیگری ثبت شده است.',
            'current_password.required_with' => 'برای تغییر رمز عبور، وارد کردن رمز عبور فعلی الزامی است.',
            'password.required' => 'وارد کردن رمز عبور جدید الزامی است.',
            'password.min' => 'رمز عبور جدید باید حداقل ۸ کاراکتر باشد.',
            'password.confirmed' => 'تکرار رمز عبور جدید با مقدار واردشده مطابقت ندارد.',
        ];
    }
}
