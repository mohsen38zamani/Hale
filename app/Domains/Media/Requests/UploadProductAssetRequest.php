<?php

namespace App\Domains\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadProductAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'انتخاب و آپلود تصویر محصول الزامی است.',
            'image.image' => 'فایل ارسالی باید یک تصویر معتبر باشد.',
            'image.mimes' => 'فرمت تصویر باید یکی از پسوندهای jpg، jpeg، png یا webp باشد.',
            'image.max' => 'حجم فایل تصویر نمی‌تواند بیش از ۱۰ مگابایت باشد.',
        ];
    }
}
