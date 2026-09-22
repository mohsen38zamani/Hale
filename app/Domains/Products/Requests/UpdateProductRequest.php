<?php

namespace App\Domains\Products\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'in:active,archived'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'نام محصول نمی‌تواند خالی باشد.',
            'name.max' => 'نام محصول نمی‌تواند بیش از ۱۵۰ کاراکتر باشد.',
            'description.max' => 'توضیحات محصول نمی‌تواند بیش از ۵۰۰۰ کاراکتر باشد.',
            'status.in' => 'وضعیت محصول باید یکی از موارد active یا archived باشد.',
        ];
    }
}
