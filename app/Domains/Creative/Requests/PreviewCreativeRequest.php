<?php

namespace App\Domains\Creative\Requests;

use App\Domains\Creative\Enums\CreativeGoal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewCreativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'goal' => ['sometimes', Rule::enum(CreativeGoal::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'انتخاب محصول الزامی است.',
            'product_id.exists' => 'محصول انتخاب‌شده یافت نشد.',
            'goal.enum' => 'هدف انتخاب‌شده نامعتبر است.',
        ];
    }
}
