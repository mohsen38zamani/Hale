<?php

namespace App\Domains\Creative\Requests;

use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Enums\CreativeStyle;
use App\Domains\Creative\Enums\FidelityMode;
use App\Domains\Creative\Enums\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Everything the studio already decided, plus the target model.
 *
 * The inspector quotes the prompt the user is looking at, so the request
 * carries the same settings a generation would; anything left out still falls
 * back to autoBest() in the controller. Targets of both kinds are accepted
 * here - a `copy` target has a compiled prompt to show even though the
 * generation endpoint will refuse to run it.
 */
class PreviewCreativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Every "sometimes" rule is also `nullable`: the browser sends
            // the studio's empty fields as explicit JSON null, and a null
            // must mean "not decided yet" (autoBest decides), not a
            // validation error - Laravel runs enum/in rules on explicit
            // null unless the field is nullable.
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'goal' => ['sometimes', 'nullable', Rule::enum(CreativeGoal::class)],
            'style' => ['sometimes', 'nullable', Rule::enum(CreativeStyle::class)],
            'format' => ['sometimes', 'nullable', Rule::enum(CreativeFormat::class)],
            'environment' => ['nullable', Rule::in(config('creative.environments'))],
            'category' => ['nullable', 'string', Rule::enum(ProductCategory::class)],
            'fidelity_mode' => ['nullable', 'string', Rule::enum(FidelityMode::class)],
            'video_duration_seconds' => ['nullable', 'integer', Rule::in(config('creative.video_durations'))],
            'custom_prompt' => ['nullable', 'string', 'max:1000'],
            'surface' => ['nullable', 'string', Rule::in(array_keys(config('creative.surfaces')))],
            'props' => ['nullable', 'string', Rule::in(array_keys(config('creative.props')))],
            'camera_angle' => ['nullable', 'string', Rule::in(array_keys(config('creative.camera_angles')))],
            'lighting_setup' => ['nullable', 'string', Rule::in(array_keys(config('creative.lighting_setups')))],
            'character_consistency' => ['nullable', 'string', Rule::in(array_keys(config('creative.character_consistencies')))],
            'campaign' => ['sometimes', 'nullable', 'boolean'],
            'season_theme' => ['nullable', 'string', Rule::in(array_column(config('seasons.themes'), 'key'))],
            'target_ai' => ['nullable', 'string', Rule::in(array_keys(config('creative.target_ais')))],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'انتخاب محصول الزامی است.',
            'product_id.exists' => 'محصول انتخاب‌شده یافت نشد.',
            'goal.enum' => 'هدف انتخاب‌شده نامعتبر است.',
            'style.enum' => 'سبک انتخاب‌شده نامعتبر است.',
            'format.enum' => 'قالب خروجی انتخاب‌شده نامعتبر است.',
            'environment.in' => 'محیط صحنه انتخاب‌شده نامعتبر است.',
            'category.enum' => 'صنف محصول انتخاب‌شده نامعتبر است.',
            'fidelity_mode.enum' => 'حالت حفظ اصالت انتخاب‌شده نامعتبر است.',
            'video_duration_seconds.integer' => 'مدت زمان ویدیو باید عدد باشد.',
            'video_duration_seconds.in' => 'مدت زمان ویدیوی انتخاب‌شده معتبر نیست.',
            'custom_prompt.string' => 'توضیحات دلخواه باید به صورت متن باشد.',
            'custom_prompt.max' => 'توضیحات دلخواه نمی‌تواند بیش از ۱۰۰۰ کاراکتر باشد.',
            'surface.in' => 'جنس سطح یا پایه انتخاب‌شده نامعتبر است.',
            'props.in' => 'اکسسوری صحنه انتخاب‌شده نامعتبر است.',
            'camera_angle.in' => 'زاویه دوربین انتخاب‌شده نامعتبر است.',
            'lighting_setup.in' => 'نورپردازی انتخاب‌شده نامعتبر است.',
            'character_consistency.in' => 'تنظیمات ثبات کاراکتر انتخاب‌شده نامعتبر است.',
            'campaign.boolean' => 'وضعیت کمپین فصلی نامعتبر است.',
            'season_theme.in' => 'تم فصلی انتخاب‌شده نامعتبر است.',
            'target_ai.in' => 'مدل هوش مصنوعی انتخاب‌شده نامعتبر است.',
        ];
    }
}
