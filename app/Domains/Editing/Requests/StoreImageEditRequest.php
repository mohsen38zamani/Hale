<?php

namespace App\Domains\Editing\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImageEditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Operation-specific payloads:
     *  - remove_bg: background=transparent|white (default transparent)
     *  - upscale:   target=hd|2k|4k (default hd; 2k/4k need a premium plan)
     *  - expand:    aspect_ratio is mandatory (the canvas target)
     *  - shadow:    effect=shadow|reflection (default shadow)
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'source_type' => ['required', 'string', 'in:generation,product'],
            'source_id' => ['required', 'integer', 'min:1'],
            'operation' => ['required', 'string', 'in:remove_bg,upscale,expand,shadow'],
            'background' => ['nullable', 'string', 'in:transparent,white'],
            'target' => ['nullable', 'string', 'in:hd,2k,4k'],
            'aspect_ratio' => ['required_if:operation,expand', 'nullable', 'string', 'in:1:1,4:5,3:4,1:1.91,16:9,9:16'],
            'effect' => ['nullable', 'string', 'in:shadow,reflection'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_type.required' => 'منبع ویرایش الزامی است.',
            'source_type.in' => 'منبع ویرایش باید خروجی یا محصول باشد.',
            'source_id.required' => 'شناسه منبع ویرایش الزامی است.',
            'operation.required' => 'عملیات ویرایش را انتخاب کنید.',
            'operation.in' => 'عملیات ویرایش معتبر نیست.',
            'background.in' => 'پس‌زمینه باید شفاف یا سفید باشد.',
            'target.in' => 'وضوح مقصد باید hd، 2k یا 4k باشد.',
            'aspect_ratio.required_if' => 'نسبت تصویر مقصد برای بسط کادر الزامی است.',
            'aspect_ratio.in' => 'نسبت تصویر مقصد معتبر نیست.',
            'effect.in' => 'افکت باید سایه یا رفلکس باشد.',
        ];
    }
}
