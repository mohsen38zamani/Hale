<?php

namespace App\Domains\Billing\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'plan_key' => ['required', 'string', Rule::in(array_keys(config('plans'))), Rule::notIn(['free'])],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_key.required' => 'انتخاب پلن اشتراک الزامی است.',
            'plan_key.in' => 'پلن اشتراک انتخاب‌شده معتبر نیست.',
            'plan_key.not_in' => 'امکان خرید پلن رایگان وجود ندارد. لطفاً یکی از پلن‌های اشتراکی را انتخاب کنید.',
        ];
    }
}
