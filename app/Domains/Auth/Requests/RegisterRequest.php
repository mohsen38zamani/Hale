<?php

namespace App\Domains\Auth\Requests;

use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone') && $this->phone !== null) {
            $this->merge([
                'phone' => PhoneNormalizer::normalize($this->phone),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{10,15}$/', 'required_without:email', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'وارد کردن نام و نام خانوادگی الزامی است.',
            'name.max' => 'نام نمی‌تواند بیش از ۱۰۰ کاراکتر باشد.',
            'email.email' => 'فرمت ایمیل واردشده نامعتبر است.',
            'email.unique' => 'این ایمیل قبلاً در سیستم ثبت شده است. لطفاً وارد شوید.',
            'email.required_without' => 'وارد کردن حداقل یکی از ایمیل یا شماره موبایل الزامی است.',
            'phone.regex' => 'شماره موبایل واردشده نامعتبر است (مثال: ۰۹۱۲۳۴۵۶۷۸۹).',
            'phone.unique' => 'این شماره موبایل قبلاً ثبت شده است. لطفاً وارد شوید.',
            'phone.required_without' => 'وارد کردن حداقل یکی از ایمیل یا شماره موبایل الزامی است.',
            'password.required' => 'وارد کردن رمز عبور الزامی است.',
            'password.min' => 'رمز عبور باید حداقل ۸ کاراکتر باشد.',
            'password.confirmed' => 'تکرار رمز عبور با رمز عبور واردشده مطابقت ندارد.',
        ];
    }
}
