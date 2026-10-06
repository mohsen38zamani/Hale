<?php

namespace App\Domains\Templates\Controllers;

use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Enums\CreativeStyle;
use App\Domains\Templates\Models\GenerationTemplate;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TemplateController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $templates = $request->user()->generationTemplates()
            ->latest()
            ->paginate(min($request->integer('per_page', 15), 50));

        return $this->success($templates);
    }

    public function store(Request $request): JsonResponse
    {
        $template = $request->user()->generationTemplates()->create(
            $request->validate($this->rules($request), $this->messages())
        );

        return $this->success($template, 201);
    }

    public function show(Request $request, int $template): JsonResponse
    {
        return $this->success($this->owned($request, $template));
    }

    public function update(Request $request, int $template): JsonResponse
    {
        $item = $this->owned($request, $template);
        $item->update($request->validate($this->rules($request), $this->messages()));

        return $this->success($item);
    }

    public function destroy(Request $request, int $template): JsonResponse
    {
        $this->owned($request, $template)->delete();

        return $this->success(['deleted' => true]);
    }

    private function owned(Request $request, int $template): GenerationTemplate
    {
        return $request->user()->generationTemplates()->findOrFail($template);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(Request $request): array
    {
        $isUpdate = $request->isMethod('PUT') || $request->isMethod('PATCH');

        $rules = [
            'name' => $isUpdate ? ['sometimes', 'required', 'string', 'max:60'] : ['required', 'string', 'max:60'],
        ];

        // Settings are replaced as a whole: absent on PUT keeps the old value,
        // present must be a complete valid preset.
        if (! $isUpdate || $request->has('settings')) {
            $rules += [
                'settings' => ['required', 'array'],
                'settings.product_id' => ['prohibited'],
                'settings.goal' => ['required', Rule::enum(CreativeGoal::class)],
                'settings.style' => ['required', Rule::enum(CreativeStyle::class)],
                'settings.format' => ['required', Rule::enum(CreativeFormat::class)],
                'settings.environment' => ['nullable', Rule::in(config('creative.environments'))],
                'settings.video_duration_seconds' => ['nullable', 'integer', Rule::in(config('creative.video_durations'))],
                'settings.custom_prompt' => ['nullable', 'string', 'max:1000'],
                'settings.surface' => ['nullable', Rule::in(array_keys(config('creative.surfaces')))],
                'settings.props' => ['nullable', Rule::in(array_keys(config('creative.props')))],
                'settings.camera_angle' => ['nullable', Rule::in(array_keys(config('creative.camera_angles')))],
                'settings.lighting_setup' => ['nullable', Rule::in(array_keys(config('creative.lighting_setups')))],
                'settings.character_consistency' => ['nullable', Rule::in(array_keys(config('creative.character_consistencies')))],
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.required' => 'نام قالب الزامی است.',
            'name.sometimes' => 'نام قالب باید متن باشد.',
            'name.max' => 'نام قالب حداکثر ۶۰ کاراکتر است.',
            'settings.required' => 'تنظیمات قالب الزامی است.',
            'settings.array' => 'تنظیمات قالب باید ساختار معتبری داشته باشد.',
            'settings.product_id.prohibited' => 'قالب‌ها به محصول خاصی وابسته نیستند و product_id نمی‌پذیرند.',
            'settings.goal.required' => 'هدف قالب الزامی است.',
            'settings.goal.enum' => 'هدف انتخاب‌شده نامعتبر است.',
            'settings.style.required' => 'سبک قالب الزامی است.',
            'settings.style.enum' => 'سبک انتخاب‌شده نامعتبر است.',
            'settings.format.required' => 'قالب خروجی الزامی است.',
            'settings.format.enum' => 'قالب خروجی انتخاب‌شده نامعتبر است.',
            'settings.environment.in' => 'محیط صحنه انتخاب‌شده نامعتبر است.',
            'settings.video_duration_seconds.in' => 'مدت زمان ویدیوی انتخاب‌شده معتبر نیست.',
            'settings.custom_prompt.max' => 'متن دلخواه قالب نمی‌تواند بیش از ۱۰۰۰ کاراکتر باشد.',
            'settings.surface.in' => 'جنس سطح یا پایه انتخاب‌شده نامعتبر است.',
            'settings.props.in' => 'اکسسوری صحنه انتخاب‌شده نامعتبر است.',
            'settings.camera_angle.in' => 'زاویه دوربین انتخاب‌شده نامعتبر است.',
            'settings.lighting_setup.in' => 'نورپردازی انتخاب‌شده نامعتبر است.',
            'settings.character_consistency.in' => 'ثبات کاراکتر انتخاب‌شده در قالب نامعتبر است.',
        ];
    }
}
