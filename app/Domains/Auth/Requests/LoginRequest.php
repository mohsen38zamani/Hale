<?php

namespace App\Domains\Auth\Requests;

use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('identifier') && ! str_contains((string) $this->identifier, '@')) {
            $this->merge([
                'identifier' => PhoneNormalizer::normalize((string) $this->identifier),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100']
        ];
    }
}
