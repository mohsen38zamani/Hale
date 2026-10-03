<?php

namespace App\Domains\Generations\Requests;

use App\Domains\AI\Services\PromptModerator;
use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Enums\CreativeStyle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bulk catalog processing: many products, one shared preset. Any setting
 * left out falls back to CreativeEngine::autoBest() for that product.
 */
class BulkGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1', 'max:20'],
            'product_ids.*' => ['integer', 'distinct'],
            'goal' => ['sometimes', Rule::enum(CreativeGoal::class)],
            'style' => ['sometimes', Rule::enum(CreativeStyle::class)],
            'format' => ['sometimes', Rule::enum(CreativeFormat::class)],
            'environment' => ['sometimes', Rule::in(config('creative.environments'))],
            'video_duration_seconds' => [Rule::requiredIf(fn (): bool => in_array($this->input('format'), ['instagram_reel', 'tiktok'], true)), 'nullable', 'integer', Rule::in(config('creative.video_durations'))],
            'custom_prompt' => ['nullable', 'string', 'max:1000'],
            'surface' => ['sometimes', Rule::in(array_keys(config('creative.surfaces')))],
            'props' => ['sometimes', Rule::in(array_keys(config('creative.props')))],
            'camera_angle' => ['sometimes', Rule::in(array_keys(config('creative.camera_angles')))],
            'lighting_setup' => ['sometimes', Rule::in(array_keys(config('creative.lighting_setups')))],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->filled('custom_prompt')) {
                $moderator = app(PromptModerator::class);
                if (! $moderator->passes((string) $this->input('custom_prompt'))) {
                    $validator->errors()->add('custom_prompt', 'توضیحات دلخواه با قوانین محتوایی سازگار نیست.');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_ids.required' => 'انتخاب حداقل یک محصول الزامی است.',
            'product_ids.array' => 'فهرست محصولات باید آرایه باشد.',
            'product_ids.min' => 'حداقل یک محصول را انتخاب کنید.',
            'product_ids.max' => 'حداکثر ۲۰ محصول را می‌توانید هم‌زمان پردازش کنید.',
            'product_ids.distinct' => 'هر محصول فقط یک بار می‌تواند در فهرست باشد.',
            'product_ids.*.integer' => 'شناسه محصول نامعتبر است.',
            'goal.enum' => 'هدف انتخاب‌شده نامعتبر است.',
            'style.enum' => 'سبک انتخاب‌شده نامعتبر است.',
            'format.enum' => 'قالب خروجی انتخاب‌شده نامعتبر است.',
            'environment.in' => 'محیط صحنه انتخاب‌شده نامعتبر است.',
            'video_duration_seconds.required' => 'برای تولید ویدیو، تعیین مدت زمان الزامی است.',
            'video_duration_seconds.in' => 'مدت زمان ویدیوی انتخاب‌شده معتبر نیست.',
            'custom_prompt.string' => 'توضیحات دلخواه باید به صورت متن باشد.',
            'custom_prompt.max' => 'توضیحات دلخواه نمی‌تواند بیش از ۱۰۰۰ کاراکتر باشد.',
            'surface.in' => 'جنس سطح یا پایه انتخاب‌شده نامعتبر است.',
            'props.in' => 'اکسسوری صحنه انتخاب‌شده نامعتبر است.',
            'camera_angle.in' => 'زاویه دوربین انتخاب‌شده نامعتبر است.',
            'lighting_setup.in' => 'نورپردازی انتخاب‌شده نامعتبر است.',
        ];
    }
}
