<?php

namespace App\Domains\Products\Requests;

use App\Domains\Creative\Enums\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'in:active,archived'],
            'category' => ['nullable', 'string', Rule::enum(ProductCategory::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'وارد کردن نام محصول الزامی است.',
            'name.max' => 'نام محصول نمی‌تواند بیش از ۱۵۰ کاراکتر باشد.',
            'description.max' => 'توضیحات محصول نمی‌تواند بیش از ۵۰۰۰ کاراکتر باشد.',
            'status.in' => 'وضعیت محصول باید یکی از موارد active یا archived باشد.',
            'category.enum' => 'صنف محصول انتخاب‌شده نامعتبر است.',
        ];
    }
}
