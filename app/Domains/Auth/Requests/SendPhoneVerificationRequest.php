<?php

namespace App\Domains\Auth\Requests;

use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class SendPhoneVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/', 'unique:users,phone,'.$this->user()->id],
        ];
    }
}
