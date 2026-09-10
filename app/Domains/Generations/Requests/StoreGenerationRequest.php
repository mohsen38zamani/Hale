<?php

namespace App\Domains\Generations\Requests;

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
        return ['product_id' => ['required', 'integer', 'exists:products,id'], 'goal' => ['required', Rule::enum(CreativeGoal::class)], 'style' => ['required', Rule::enum(CreativeStyle::class)], 'format' => ['required', Rule::enum(CreativeFormat::class)], 'environment' => ['nullable', Rule::in(config('creative.environments'))], 'video_duration_seconds' => ['nullable', 'integer', Rule::in(config('creative.video_durations'))], 'settings' => ['sometimes', 'array']];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (in_array($this->input('format'), ['instagram_reel', 'tiktok'], true) && ! $this->filled('video_duration_seconds')) {
                $validator->errors()->add('video_duration_seconds', 'مدت ویدئو الزامی است.');
            }
        }];
    }
}
