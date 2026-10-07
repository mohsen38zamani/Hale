<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/brand/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/brand/apple-touch-icon.png">
    <title>استودیوی ساخت محتوا | حله</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="dashboard-page">
    {{-- Ambient Aurora --}}
    <div class="aurora-mesh" aria-hidden="true">
        <div class="aurora-orb aurora-orb-1" style="opacity: 0.25;"></div>
        <div class="aurora-orb aurora-orb-2" style="opacity: 0.25;"></div>
    </div>

    <div class="app-shell" style="position: relative; z-index: 1;">
        <header class="topbar">
            <a class="brand" href="/" aria-label="حله - پلتفرم هوشمند تولید محتوا">
                <img src="/images/brand/icon-rounded.png" alt="لوگو حله" class="brand-logo-img" width="32" height="32">
                <span>حله</span>
            </a>
            <div class="topbar-actions">
                <a class="btn-glass" href="/dashboard" style="font-size: 13px; padding: 6px 16px;">
                    بازگشت به داشبورد
                </a>
                <div class="credit-pill">
                    <span>موجودی:</span>
                    <strong data-credit>--</strong>
                    <span data-quota hidden style="font-size: 11px; color: var(--text-muted); font-weight: 600;"></span>
                    <a href="/pricing" style="font-size: 12px; margin-right: 4px; color: var(--primary);">+ شارژ</a>
                </div>
            </div>
        </header>

        <main class="builder-main">
            <div style="margin-bottom: 32px;">
                <div class="badge-glow" style="margin-bottom: 8px;">
                    <span>محیط کار هوشمند · CREATIVE STUDIO</span>
                </div>
                <h1 style="font-size: clamp(28px, 4vw, 42px); font-weight: 800; margin: 0 0 10px;">
                    صحنه ویدیویی و تبلیغاتی محصولت را بساز
                </h1>
                <p style="color: var(--text-secondary); font-size: 15px; margin: 0;">
                    چند انتخاب ساده؛ الگوریتم هوش مصنوعی خروجی متناسب با الگوریتم اینستاگرام را آماده می‌کند.
                </p>
            </div>

            <div class="builder-layout">
                {{-- Form Controls Column --}}
                <div class="builder-panel">
                    {{-- Permanent seasonal theme status + picker, above the studio form --}}
                    <x-season-picker />

                    <form class="builder-form" data-builder-form
                        data-default-character="{{ config('creative.character_consistency_default') }}"
                        data-text-strict="{{ config('creative.text_suppression.strict') }}"
                        data-text-permissive="{{ config('creative.text_suppression.permissive') }}"
                        data-text-keywords="{{ json_encode(config('creative.text_suppression.request_keywords'), JSON_UNESCAPED_UNICODE) }}"
                        data-scene-effects="{{ json_encode(config('creative.effects'), JSON_UNESCAPED_UNICODE) }}"
                        data-summary-template="{{ config('creative.summary_template') }}">
                        {{-- Step 1: Product --}}
                        <div style="margin-bottom: 24px;">
                            <label style="display: block; font-size: 14px; font-weight: 700; color: #FFFFFF; margin-bottom: 8px;">
                                ۱. محصول مورد نظر را انتخاب کن:
                            </label>
                            <select name="product_id" data-product-select required style="width: 100%; background: rgba(255,255,255,0.04); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 12px 16px; color: #FFFFFF; font-size: 14px; outline: 0;">
                                <option value="">انتخاب محصول از کتابخانه...</option>
                            </select>
                        </div>

                        {{-- Magic Auto-Best Button --}}
                        <div class="auto-best-row" style="margin-bottom: 28px;">
                            <button type="button" class="btn-aurora" data-auto-best style="width: 100%; justify-content: center; padding: 12px; border-radius: 12px; font-size: 14px; background: linear-gradient(135deg, #7C3AED, #DB2777);">
                                ✨ خودت بهترینش رو بساز (پیشنهاد هوشمند)
                            </button>
                            <small style="display: block; text-align: center; color: var(--text-muted); font-size: 11px; margin-top: 6px;">
                                تحلیل خودکار دسته‌بندی کالا و تنظیم فرمت، نور و سبک بهینه
                            </small>
                        </div>

                        {{-- Step 2: Goal --}}
                        <fieldset style="border: 0; padding: 0; margin: 0 0 28px 0;">
                            <legend style="font-size: 14px; font-weight: 700; color: #FFFFFF; margin-bottom: 8px;">
                                ۲. هدف این کمپین چیست؟
                                <x-impact-badge control="goal" />
                            </legend>
                            <small class="impact-hint" data-impact-hint>هدف، جملهٔ Objective پرامپت را می‌سازد؛ نوع خروجی و پیام اصلی تصویر را تعیین می‌کند.</small>
                            <div class="choice-grid" data-goals></div>
                        </fieldset>

                        {{-- Step 3: Style --}}
                        <fieldset style="border: 0; padding: 0; margin: 0 0 28px 0;">
                            <legend style="font-size: 14px; font-weight: 700; color: #FFFFFF; margin-bottom: 8px;">
                                ۳. سبک و فضای بصری را انتخاب کن:
                                <x-impact-badge control="style" />
                            </legend>
                            <small class="impact-hint" data-impact-hint>سبک کلی، ترکیب‌بندی، پالت رنگ و حال‌وهوای صحنه را می‌سازد و بیشترین اثر را روی خروجی نهایی دارد.</small>
                            <div class="choice-grid style-choices" data-styles></div>
                        </fieldset>

                        {{-- Step 4: Format --}}
                        <fieldset style="border: 0; padding: 0; margin: 0 0 28px 0;">
                            <legend style="font-size: 14px; font-weight: 700; color: #FFFFFF; margin-bottom: 8px;">
                                ۴. فرمت خروجی:
                                <x-impact-badge control="format" />
                            </legend>
                            <small class="impact-hint" data-impact-hint>نسبت ابعاد، کراپ و چیدمان نهایی تصویر را جابه‌جا می‌کند؛ جای محصول در قاب عوض می‌شود.</small>
                            <div class="choice-grid" data-formats></div>
                        </fieldset>

                        {{-- Environment --}}
                        <div data-environment-field style="margin-bottom: 24px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; color: #FFFFFF; margin-bottom: 8px;">
                                پس‌زمینه و محیط صحنه:
                                <x-impact-badge control="environment" />
                            </label>
                            <select name="environment" data-environment style="width: 100%; background: rgba(255,255,255,0.04); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 10px 14px; color: #FFFFFF; font-size: 13px; outline: 0;"></select>
                            <small class="impact-hint" data-impact-hint>محیط، پس‌زمینه و فضای صحنه را عوض می‌کند؛ محصول روی همان پایه با فضای تازه نمایش داده می‌شود.</small>
                        </div>

                        {{-- Output Quality Tier --}}
                        <div style="margin-bottom: 24px;">
                            <label for="quality" style="display: block; font-size: 13px; font-weight: 600; color: #FFFFFF; margin-bottom: 8px;">
                                🎞️ کیفیت خروجی:
                            </label>
                            <select name="quality" data-quality style="width: 100%; background: rgba(255,255,255,0.04); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 10px 14px; color: #FFFFFF; font-size: 13px; outline: 0;">
                                <option value="standard">استاندارد · ۱K · ۱۰ Credit</option>
                                <option value="premium" data-paid-only>پرمیوم · ۲K · ۲۵ Credit (پلن‌های پولی)</option>
                            </select>
                        </div>

                        {{-- Video Duration --}}
                        <div data-duration-field hidden style="margin-bottom: 24px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; color: var(--accent-pink); margin-bottom: 8px;">
                                ⏱️ مدت زمان ویدیوی Reels:
                            </label>
                            <select name="video_duration_seconds" data-duration style="width: 100%; background: rgba(255,255,255,0.04); border: 1px solid rgba(236,72,153,0.3); border-radius: 12px; padding: 10px 14px; color: #FFFFFF; font-size: 13px; outline: 0;"></select>
                        </div>

                        {{-- Step 5: Scene & Studio Physical Controls (Surfaces, Props, Angles, Lighting) --}}
                        <div class="scene-controls-panel" data-scene-controls-panel>
                            <div class="scene-controls-header" data-scene-toggle role="button" tabindex="0" aria-expanded="true">
                                <h3>
                                    <span>🎛️</span>
                                    <span>کنترل‌های فیزیکی صحنه (اختیاری)</span>
                                </h3>
                                <span class="toggle-icon" data-scene-toggle-icon>▲</span>
                            </div>
                            <div class="scene-controls-body" data-scene-controls-body>
                                {{-- 1. Surfaces & Pedestals --}}
                                <div class="scene-control-group">
                                    <label class="group-title">جنس سطح و پایه کالا (Surfaces & Pedestals): <x-impact-badge control="surface" /></label>
                                    <small class="impact-hint" data-impact-hint>جنس پایه، متریال زیر محصول و بازتاب‌هایش را تغییر می‌دهد؛ چیدمان کلی صحنه دست‌نخورده می‌ماند.</small>
                                    <div class="choice-grid compact-grid" data-surfaces></div>
                                </div>

                                {{-- 2. Props & Accents --}}
                                <div class="scene-control-group">
                                    <label class="group-title">آبجکت‌های مکمل و اکسسوری (Props & Accents): <x-impact-badge control="props" /></label>
                                    <small class="impact-hint" data-impact-hint>اکسسوری‌ها لهجه‌های تزئینی (برگ، قطره، مه) اضافه می‌کنند و ترکیب اصلی صحنه را دست نمی‌زنند.</small>
                                    <div class="choice-grid compact-grid" data-props></div>
                                </div>

                                {{-- 3. Camera Angles --}}
                                <div class="scene-control-group">
                                    <label class="group-title">تنظیم زاویه دوربین (Camera Angles): <x-impact-badge control="camera_angle" /></label>
                                    <small class="impact-hint" data-impact-hint>زاویه دوربین، پرسپکتیو و خط افق صحنه را تعیین می‌کند و خروجی را کاملاً عوض می‌کند.</small>
                                    <div class="choice-grid compact-grid" data-camera-angles></div>
                                </div>

                                {{-- 4. Lighting Setup --}}
                                <div class="scene-control-group">
                                    <label class="group-title">کنترل نورپردازی استودیو (Lighting Setup): <x-impact-badge control="lighting_setup" /></label>
                                    <small class="impact-hint" data-impact-hint>نورپردازی، سایه‌ها و درخشش سطح را تنظیم می‌کند؛ فضا و حس صحنه عوض می‌شود ولی چیدمان نه.</small>
                                    <div class="choice-grid compact-grid" data-lighting-setups></div>
                                </div>

                                {{-- 5. Character & Model Consistency --}}
                                <div class="scene-control-group">
                                    <label class="group-title">ثبات کاراکتر و مدل انسانی (Character Consistency): <x-impact-badge control="character_consistency" /></label>
                                    <small class="impact-hint" data-impact-hint>قفل هویت چهره و مدل در تولیدهای متوالی، یا اجازه به هوش مصنوعی برای خلق مدل‌های جدید و متنوع.</small>
                                    <div class="choice-grid compact-grid" data-character-consistencies></div>
                                </div>
                            </div>
                        </div>

                        {{-- Step 6: Custom Prompt / Scene Details (Optional) --}}
                        <div style="margin-bottom: 24px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <label for="custom_prompt" style="font-size: 13px; font-weight: 600; color: #FFFFFF;">
                                    ✨ توضیحات و پرامپت دلخواه صحنه (اختیاری):
                                </label>
                                <span style="font-size: 11px; color: var(--text-muted);" data-custom-prompt-counter>۰ / ۱۰۰۰</span>
                            </div>
                            <textarea
                                id="custom_prompt"
                                name="custom_prompt"
                                data-custom-prompt
                                rows="3"
                                maxlength="1000"
                                placeholder="مثلاً: روی صخره مرطوب بازالت، میان گل‌های ارکیده صورتی و مه‌آلودگی ملایم با انعکاس نور..."
                                style="width: 100%; box-sizing: border-box; background: rgba(255,255,255,0.04); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 12px 14px; color: #FFFFFF; font-size: 13px; outline: 0; resize: vertical; min-height: 84px; line-height: 1.6; font-family: inherit; transition: border-color 0.2s, box-shadow 0.2s;"
                            ></textarea>
                            <small style="display: block; color: var(--text-muted); font-size: 11px; margin-top: 6px; line-height: 1.5;">
                                المان‌های محیطی، اشیاء مکمل، نور دلخواه یا تم صحنه را به فارسی یا انگلیسی بنویسید تا هوش مصنوعی آن را در صحنه تلفیق کند.
                            </small>
                        </div>

                        {{-- Saved Studio Templates --}}
                        <div class="template-toolbar" data-template-list style="margin-bottom: 20px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px;">
                                <span style="font-size: 12px; font-weight: 700; color: var(--text-muted);">قالب‌های ذخیره‌شده</span>
                                <button class="small-button" type="button" data-template-save style="font-size: 12px; padding: 5px 10px;">＋ ذخیره به‌عنوان قالب</button>
                            </div>
                            <div class="template-chips" data-template-chips>
                                <small data-template-empty style="color: var(--text-muted); font-size: 12px;">هنوز قالبی نداری؛ ترکیب دلخواهت را بساز و ذخیره کن تا بعداً با یک کلیک اعمال شود.</small>
                            </div>
                            <div class="template-save-row" data-template-save-row hidden>
                                <input class="template-name-input" data-template-name type="text" maxlength="60" placeholder="نام قالب، مثلاً کمپین لوکس" aria-label="نام قالب">
                                <button class="small-button" type="button" data-template-confirm>ثبت</button>
                                <button class="small-button" type="button" data-template-cancel>انصراف</button>
                            </div>
                            <p class="form-message" data-template-message role="status" style="margin: 8px 0 0; font-size: 12px;"></p>
                        </div>

                        {{-- Credit Estimate Badge --}}
                        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-subtle); border-radius: 14px; padding: 14px 18px; margin-bottom: 20px;">
                            <p class="estimate-message" data-credit-estimate aria-live="polite" style="margin: 0; font-size: 13px; color: var(--text-secondary); font-weight: 600;"></p>
                        </div>

                        <p class="form-message" data-builder-message role="alert" style="margin-bottom: 16px;"></p>

                        <button class="btn-aurora full-button" type="submit" data-generate style="padding: 14px; font-size: 15px; border-radius: 14px;">
                            شروع ساخت محتوا ✦
                        </button>
                    </form>
                </div>

                {{-- Live Interactive Studio Preview Canvas --}}
                <div class="builder-panel studio-canvas-panel" data-canvas-panel>
                    {{-- Top Status Bar --}}
                    <div class="canvas-topbar">
                        <div class="canvas-status-indicator">
                            <span class="live-dot" aria-hidden="true"></span>
                            <span>بوم تعاملی استودیو · LIVE PREVIEW</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <button class="campaign-badge" type="button" data-campaign-badge aria-pressed="false" hidden></button>
                            <div class="canvas-format-chip" data-canvas-format-chip>۱:۱ · پست اینستاگرام</div>
                        </div>
                    </div>

                    {{-- Stage Viewport --}}
                    <div class="studio-stage-wrapper">
                        <div class="studio-stage ratio-1-1" data-canvas-stage>
                            {{-- Dynamic Atmosphere & Lighting Layer --}}
                            <div class="stage-atmosphere style-luxury" data-canvas-atmosphere></div>

                            {{-- Style & Environment Indicator Badge --}}
                            <div class="stage-badge" data-canvas-style-badge>سبک: لوکس · محیط: استودیو</div>

                            {{-- 3D Pedestal / Grounding Shadow --}}
                            <div class="stage-ground" data-canvas-ground></div>

                            {{-- Product Staging Area --}}
                            <div class="stage-product" data-canvas-product>
                                <img data-canvas-product-img src="" alt="" style="display: none;">
                                <div class="stage-placeholder" data-canvas-placeholder>
                                    <div class="stage-placeholder-icon">✦</div>
                                    <div class="stage-placeholder-title" data-canvas-placeholder-title>محصول را انتخاب کن</div>
                                    <div class="stage-placeholder-sub">پیش‌نمایش زنده کات‌اوت، نورپردازی و تم صحنه در این کادر نمایش داده می‌شود.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Scene Summary Bar (redesign 4): one Persian sentence
                         describing the configured scene, written live by
                         updateSceneSummary() from the config template. --}}
                    <div class="scene-summary" data-scene-summary role="status">
                        <span class="scene-summary-label">خلاصهٔ صحنه</span>
                        <span class="scene-summary-text" data-scene-summary-text>چیدمان صحنه پس از بارگذاری گزینه‌ها اینجا نوشته می‌شود.</span>
                    </div>

                    {{-- Prompt Inspector Box --}}
                    <div class="prompt-inspector" data-prompt-inspector>
                        <div class="inspector-header">
                            <div class="inspector-title">
                                <span>🔍 بازرس پرامپت هوش مصنوعی (Prompt Inspector)</span>
                            </div>
                            <button type="button" class="btn-copy-prompt" data-copy-prompt title="کپی متن پرامپت">
                                📋 کپی پرامپت
                            </button>
                        </div>
                        <div class="inspector-chips" data-inspector-chips>
                            <span class="inspector-chip" data-chip-product>محصول: انتخاب نشده</span>
                            <span class="inspector-chip" data-chip-goal>هدف: معرفی محصول</span>
                            <span class="inspector-chip" data-chip-style>سبک: لوکس</span>
                            <span class="inspector-chip" data-chip-env>محیط: استودیو</span>
                            <span class="inspector-chip" data-chip-surface>پایه: استودیویی</span>
                            <span class="inspector-chip" data-chip-props>اکسسوری: ساده</span>
                            <span class="inspector-chip" data-chip-camera>دوربین: روبرو</span>
                            <span class="inspector-chip" data-chip-lighting>نور: سافت‌باکس</span>
                            <span class="inspector-chip" data-chip-character>کاراکتر: مدل جدید</span>
                            <span class="inspector-chip" data-chip-format>فرمت: ۱:۱</span>
                        </div>
                        <div class="inspector-code" data-inspector-code>
                            منتظر انتخاب محصول و تنظیمات صحنه...
                        </div>
                    </div>

                    {{-- Compact Pro Tips Footer --}}
                    <div style="background: rgba(255,255,255,0.02); border-radius: 12px; padding: 12px 14px; border: 1px solid rgba(255,255,255,0.04); display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 16px;">💡</span>
                        <p style="margin: 0; font-size: 11px; line-height: 1.5; color: var(--text-muted);">
                            نور و پس‌زمینهٔ این بوم به صورت ریل‌تایم با انتخاب‌های شما تغییر می‌کند تا قبل از مصرف اعتبار، کیفیت خروجی را بررسی کنید.
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
