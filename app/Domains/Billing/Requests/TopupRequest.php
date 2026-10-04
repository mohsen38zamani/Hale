<?php

namespace App\Domains\Billing\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'pack' => ['required', 'string', Rule::in(array_keys(config('credits.packs')))],
        ];
    }

    public function messages(): array
    {
        return [
            'pack.required' => 'انتخاب بسته اعتبار الزامی است.',
            'pack.in' => 'بسته اعتبار انتخاب‌شده معتبر نیست.',
        ];
    }
}
