{{--
    Permanent seasonal theme control, rendered above the studio form and inside
    the dashboard theme section. The status bar always shows the active theme
    (name, colour swatches) with "change" and "off" buttons, and the picker
    lists every configured theme with a palette and an effect preview.
    State lives in localStorage under hale-season-theme (auto | off | {key}).
--}}
<div class="season-status" data-season-status hidden>
    <button class="season-theme-badge" type="button" data-season-badge hidden></button>
    <span class="season-swatches" data-season-swatches hidden aria-hidden="true"></span>
    <span class="season-status-text" data-season-status-text hidden>تم فصلی فعال نیست</span>

    <span class="season-status-actions">
        <button class="season-status-btn" type="button" data-season-change>تغییر تم</button>
        <button class="season-status-btn" type="button" data-season-toggle aria-pressed="false">خاموش</button>
    </span>

    <div class="season-picker" data-season-picker hidden>
        <div class="season-picker-head">
            <strong>تم فصلی و کمپینی</strong>
            <button class="season-status-btn" type="button" data-season-picker-close aria-label="بستن پنل تم">×</button>
        </div>

        <div class="season-picker-grid" data-season-picker-grid>
            <button class="season-tile season-tile-fixed" type="button" data-season-choice="auto">
                <span class="season-tile-preview season-tile-preview-plain" aria-hidden="true"></span>
                <span class="season-tile-name">🗓️ خودکار</span>
                <span class="season-tile-effect">پیروی از تقویم</span>
            </button>
            <button class="season-tile season-tile-fixed" type="button" data-season-choice="off">
                <span class="season-tile-preview season-tile-preview-plain" aria-hidden="true"></span>
                <span class="season-tile-name">🚫 خاموش</span>
                <span class="season-tile-effect">بدون تم فصلی</span>
            </button>

            @foreach (config('seasons.themes') as $theme)
                <button class="season-tile" type="button" data-season-choice="{{ $theme['key'] }}" data-decor="{{ $theme['decor'] }}" data-palette="{{ implode(',', $theme['palette']) }}" style="--season-bg: {{ $theme['palette'][0] }}; --season-accent: {{ $theme['palette'][1] }}; --season-glow: {{ $theme['palette'][2] }};">
                    <span class="season-tile-preview" data-decor="{{ $theme['decor'] }}" aria-hidden="true"></span>
                    <span class="season-tile-name">{{ $theme['emoji'] }} {{ $theme['name'] }}</span>
                    <span class="season-swatches season-tile-swatches" aria-hidden="true">
                        <i style="background: {{ $theme['palette'][0] }};"></i>
                        <i style="background: {{ $theme['palette'][1] }};"></i>
                        <i style="background: {{ $theme['palette'][2] }};"></i>
                    </span>
                    <span class="season-tile-effect">{{ ['snowfall' => 'برف', 'rain' => 'باران', 'vignette' => 'هاله نور', 'sparkle' => 'درخشش'][$theme['decor']] ?? $theme['decor'] }}</span>
                </button>
            @endforeach
        </div>

        <p class="season-picker-note">
            تم فصلی روی بوم استودیو اعمال می‌شود و انتخاب تو بین صفحات و پس از رفرش حفظ می‌ماند؛ استایل کمپینی فقط با فعال کردن چیپ کمپین به پرامپت اضافه می‌شود.
        </p>
    </div>
</div>
