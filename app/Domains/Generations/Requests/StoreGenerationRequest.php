<?php

namespace App\Domains\Generations\Requests;

use App\Domains\AI\Services\PromptModerator;
use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Enums\CreativeStyle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'goal' => ['required', Rule::enum(CreativeGoal::class)],
            'style' => ['required', Rule::enum(CreativeStyle::class)],
            'format' => ['required', Rule::enum(CreativeFormat::class)],
            'environment' => ['nullable', Rule::in(config('creative.environments'))],
            'video_duration_seconds' => [Rule::requiredIf(fn (): bool => in_array($this->input('format'), ['instagram_reel', 'tiktok'], true)), 'nullable', 'integer', Rule::in(config('creative.video_durations'))],
            'custom_prompt' => ['nullable', 'string', 'max:1000'],
            'surface' => ['nullable', 'string', Rule::in(array_keys(config('creative.surfaces')))],
            'props' => ['nullable', 'string', Rule::in(array_keys(config('creative.props')))],
            'camera_angle' => ['nullable', 'string', Rule::in(array_keys(config('creative.camera_angles')))],
            'lighting_setup' => ['nullable', 'string', Rule::in(array_keys(config('creative.lighting_setups')))],
            'settings' => ['sometimes', 'array'],
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

    public function messages(): array
    {
        return [
            'product_id.required' => 'انتخاب محصول الزامی است.',
            'product_id.exists' => 'محصول انتخاب‌شده یافت نشد.',
            'goal.required' => 'انتخاب هدف تولید محتوا الزامی است.',
            'goal.enum' => 'هدف انتخاب‌شده نامعتبر است.',
            'style.required' => 'انتخاب سبک طراحی الزامی است.',
            'style.enum' => 'سبک انتخاب‌شده نامعتبر است.',
            'format.required' => 'انتخاب قالب خروجی الزامی است.',
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
            'settings.array' => 'تنظیمات باید به صورت ساختار معتبر ارسال شوند.',
        ];
    }
}
