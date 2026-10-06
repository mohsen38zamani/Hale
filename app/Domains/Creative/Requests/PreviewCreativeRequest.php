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
            'campaign' => ['sometimes', 'boolean'],
            'season_theme' => ['nullable', 'string', Rule::in(array_column(config('seasons.themes'), 'key'))],
            'character_consistency' => ['nullable', 'string', Rule::in(array_keys(config('creative.character_consistencies')))],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'انتخاب محصول الزامی است.',
            'product_id.exists' => 'محصول انتخاب‌شده یافت نشد.',
            'goal.enum' => 'هدف انتخاب‌شده نامعتبر است.',
            'campaign.boolean' => 'وضعیت کمپین فصلی نامعتبر است.',
            'season_theme.in' => 'تم فصلی انتخاب‌شده نامعتبر است.',
            'character_consistency.in' => 'تنظیمات ثبات کاراکتر انتخاب‌شده نامعتبر است.',
        ];
    }
}
