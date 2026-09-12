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
}