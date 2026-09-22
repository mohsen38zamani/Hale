<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>وضعیت و نتیجه تولید | Hale Studio</title>
    @vite(['resources/css/app.css', 'resources/css/landing.css', 'resources/js/app.js'])
</head>
<body class="dashboard-page">
    {{-- Ambient Glow --}}
    <div class="aurora-mesh" aria-hidden="true">
        <div class="aurora-orb aurora-orb-1" style="opacity: 0.25;"></div>
        <div class="aurora-orb aurora-orb-3" style="opacity: 0.25;"></div>
    </div>

    <div class="app-shell" style="position: relative; z-index: 1;">
        <header class="topbar">
            <a class="brand" href="/">H<span>•</span>le</a>
            <div class="topbar-actions">
                <a class="btn-glass" href="/dashboard" style="font-size: 13px; padding: 6px 16px;">
                    بازگشت به داشبورد
                </a>
            </div>
        </header>

        <main class="generation-page" data-generation-id="{{ $generationId }}" style="max-width: 960px; margin: 0 auto; padding: 48px 0 80px;">
            <div class="badge-glow" style="margin-bottom: 12px;">
                <span>خروجی شناسه #{{ $generationId }}</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 40px; align-items: center; margin-bottom: 40px;">
                {{-- Status & Meta Column --}}
                <div>
                    <h1 data-generation-title style="font-size: clamp(28px, 4vw, 42px); font-weight: 800; line-height: 1.2; margin: 0 0 14px;">
                        در حال پردازش هوشمند...
                    </h1>
                    <p data-generation-copy style="color: var(--text-secondary); font-size: 15px; line-height: 1.7; margin: 0 0 24px;">
                        موتور هوش مصنوعی در حال بازسازی فضا، تنظیم پرتوهای نور و متریال خروجی است.
                    </p>

                    {{-- Progress Bar --}}
                    <div class="progress-track" style="background: rgba(255,255,255,0.06); border: 1px solid var(--border-subtle); height: 10px; border-radius: 9999px; overflow: hidden; margin-bottom: 14px;">
                        <span data-generation-progress style="display: block; height: 100%; width: 30%; background: var(--aurora-gradient); border-radius: 9999px; transition: width 0.3s ease;"></span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
                        <span data-generation-status style="color: var(--accent-amber); font-weight: 600;">در صف پردازش...</span>
                        <span class="generation-credit" data-generation-credit style="color: var(--text-muted);">بررسی اعتبار...</span>
                    </div>
                </div>

                {{-- Result / Preview Frame --}}
                <div class="result-frame" data-result-frame style="background: rgba(15,18,28,0.8); border: 1px solid var(--border-subtle); border-radius: 24px; min-height: 380px; display: flex; align-items: center; justify-content: center; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.8); position: relative;">
                    <div class="result-placeholder" style="text-align: center; padding: 40px;">
                        <span style="font-size: 48px; color: var(--primary); display: block; margin-bottom: 12px;">✦</span>
                        <strong style="color: #FFFFFF; font-size: 16px; display: block; margin-bottom: 6px;">خروجی به زودی آماده می‌شود</strong>
                        <small style="color: var(--text-muted); font-size: 12px;">سیستم به محض آماده‌سازی فایل را بارگذاری می‌کند.</small>
                    </div>
                </div>
            </div>

            {{-- Result Actions Buttons --}}
            <div class="result-actions" data-result-actions hidden style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 20px; padding: 24px; display: flex; flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between;">
                <div style="display: flex; gap: 12px; align-items: center;">
                    <a class="btn-aurora" data-download style="padding: 10px 22px; font-size: 14px;">
                        <span>دانلود با کیفیت اصلی 4K</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                    </a>
                    <button class="btn-glass" data-retry hidden style="font-size: 13px;">تلاش مجدد</button>
                    <button class="btn-glass" data-regenerate style="font-size: 13px;">تولید دوباره ↺</button>
                </div>

                <div style="display: flex; gap: 8px; align-items: center;">
                    <span style="font-size: 12px; color: var(--text-muted); margin-left: 8px;">بازخورد کیفیت:</span>
                    <button class="small-button" data-feedback="positive" style="color: #34D399;">👍 عالی بود</button>
                    <button class="small-button" data-feedback="negative" style="color: #F87171;">👎 نیاز به تغییر</button>
                </div>
            </div>

            <p class="form-message" data-generation-message role="alert" style="margin-top: 18px; text-align: center;"></p>
        </main>
    </div>
</body>
</html>
