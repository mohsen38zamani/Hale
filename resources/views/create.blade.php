<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>استودیوی ساخت محتوا | Hale</title>
    @vite(['resources/css/app.css', 'resources/css/landing.css', 'resources/js/app.js'])
</head>
<body class="dashboard-page">
    {{-- Ambient Aurora --}}
    <div class="aurora-mesh" aria-hidden="true">
        <div class="aurora-orb aurora-orb-1" style="opacity: 0.25;"></div>
        <div class="aurora-orb aurora-orb-2" style="opacity: 0.25;"></div>
    </div>

    <div class="app-shell" style="position: relative; z-index: 1;">
        <header class="topbar">
            <a class="brand" href="/">H<span>•</span>le</a>
            <div class="topbar-actions">
                <a class="btn-glass" href="/dashboard" style="font-size: 13px; padding: 6px 16px;">
                    بازگشت به داشبورد
                </a>
                <div class="credit-pill">
                    <span>موجودی:</span>
                    <strong data-credit>--</strong>
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
                    <form class="builder-form" data-builder-form>
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
                            </legend>
                            <div class="choice-grid" data-goals></div>
                        </fieldset>

                        {{-- Step 3: Style --}}
                        <fieldset style="border: 0; padding: 0; margin: 0 0 28px 0;">
                            <legend style="font-size: 14px; font-weight: 700; color: #FFFFFF; margin-bottom: 8px;">
                                ۳. سبک و فضای بصری را انتخاب کن:
                            </legend>
                            <div class="choice-grid style-choices" data-styles></div>
                        </fieldset>

                        {{-- Step 4: Format --}}
                        <fieldset style="border: 0; padding: 0; margin: 0 0 28px 0;">
                            <legend style="font-size: 14px; font-weight: 700; color: #FFFFFF; margin-bottom: 8px;">
                                ۴. فرمت خروجی:
                            </legend>
                            <div class="choice-grid" data-formats></div>
                        </fieldset>

                        {{-- Environment --}}
                        <div data-environment-field style="margin-bottom: 24px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 8px;">
                                پس‌زمینه و محیط صحنه:
                            </label>
                            <select name="environment" data-environment style="width: 100%; background: rgba(255,255,255,0.04); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 10px 14px; color: #FFFFFF; font-size: 13px; outline: 0;"></select>
                        </div>

                        {{-- Video Duration --}}
                        <div data-duration-field hidden style="margin-bottom: 24px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; color: var(--accent-pink); margin-bottom: 8px;">
                                ⏱️ مدت زمان ویدیوی Reels:
                            </label>
                            <select name="video_duration_seconds" data-duration style="width: 100%; background: rgba(255,255,255,0.04); border: 1px solid rgba(236,72,153,0.3); border-radius: 12px; padding: 10px 14px; color: #FFFFFF; font-size: 13px; outline: 0;"></select>
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

                {{-- Studio Guide / Preview Sidebar --}}
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <div class="builder-panel" style="background: rgba(139,92,246,0.05); border-color: rgba(139,92,246,0.2);">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                            <span style="font-size: 20px;">💡</span>
                            <strong style="font-size: 15px; color: #FFFFFF;">نکات طلایی برای بهترین نتیجه:</strong>
                        </div>
                        <ul style="color: var(--text-secondary); font-size: 13px; line-height: 1.8; padding-right: 18px; margin: 0;">
                            <li>عکس اولیه دارای پس‌زمینه ساده، خروجی‌های بسیار تمیزتری به همراه دارد.</li>
                            <li>برای کالاهای لوکس (عطر، طلا، ساعت)، سبک <b>سینمایی یا لوکس</b> با نور ملایم بهترین کنتراست را ایجاد می‌کند.</li>
                            <li>فرمت <b>۹:۱۶</b> به طور پیش‌فرض خروجی ویدیویی کوتاه تولید می‌کند که تعامل استوری و اکسپلور را تا ۳ برابر بالا می‌برد.</li>
                            <li>در صورت هرگونه خطا در تولید، اعتبار رزروشده فوراً و اتوماتیک به حسابتان بازمی‌گردد.</li>
                        </ul>
                    </div>

                    <div class="builder-panel" style="text-align: center; padding: 24px;">
                        <span style="font-size: 28px;">⚡</span>
                        <h4 style="font-size: 16px; margin: 8px 0 4px; color: #FFFFFF;">پردازش ابری بدون توقف</h4>
                        <p style="color: var(--text-muted); font-size: 12px; line-height: 1.6; margin: 0;">
                            پس از زدن دکمه ساخت، جاب شما بلافاصله وارد صف پردازش می‌شود و حتی با بستن تب، نتیجه آماده خواهد شد.
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
