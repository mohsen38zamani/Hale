<?php

namespace App\Domains\Editing\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVariantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Variants take no free-text instruction on purpose: a variant asks the
     * model to diverge, and a written instruction asks it to obey. Mixing the
     * two produces prompts that argue with themselves, so the axis of
     * divergence is picked from EditPromptBuilder::TAKES instead.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'source_type' => ['required', 'string', 'in:generation,product,edit'],
            'source_id' => ['required', 'integer', 'min:1'],
            'count' => ['required', 'integer', 'min:2', 'max:4'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_type.required' => 'منبع ویرایش الزامی است.',
            'source_type.in' => 'منبع ویرایش باید خروجی، محصول یا یک ویرایش قبلی باشد.',
            'source_id.required' => 'شناسه منبع ویرایش الزامی است.',
            'count.required' => 'تعداد واریانت‌ها را مشخص کنید.',
            'count.min' => 'برای ساخت واریانت حداقل ۲ تا انتخاب کنید.',
            'count.max' => 'حداکثر ۴ واریانت در هر درخواست ساخته می‌شود.',
        ];
    }
}
