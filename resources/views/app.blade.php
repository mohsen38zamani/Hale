<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Hale') }} | استودیوی محتوای تبلیغاتی</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        <header class="topbar">
            <a class="brand" href="/" aria-label="Hale">H<span>•</span>le</a>
            <div class="topbar-actions">
                <span class="status-dot"><i></i> آماده برای ساخت</span>
                <button class="text-button" data-open-auth="login">ورود</button>
            </div>
        </header>

        <main>
            <section class="hero-section">
                <div class="hero-copy">
                    <p class="eyebrow">AI CONTENT STUDIO / ۰۱</p>
                    <h1>عکس محصولت،<br><em>یک قدم تا انتشار.</em></h1>
                    <p class="hero-lede">برای محصولت محتوای تبلیغاتی آماده بساز. بدون عکاس، بدون Prompt، با کنترل کامل روی سبک و فرمت.</p>
                    <div class="hero-actions">
                        <button class="primary-button" data-open-auth="register">شروع ساخت رایگان <span>←</span></button>
                        <a class="quiet-link" href="#how-it-works">چطور کار می‌کند؟</a>
                    </div>
                    <div class="trust-row"><span>✓ ۳۰ Credit رایگان</span><span>✓ خروجی آماده اینستاگرام</span></div>
                </div>
                <div class="hero-art" aria-label="نمونه فضای ساخت محتوای Hale">
                    <div class="art-label">CREATIVE / 2026</div>
                    <div class="art-product"><div class="bottle-cap"></div><div class="bottle"><b>HALE</b><small>BOTANICAL<br>OBJECT 01</small></div></div>
                    <div class="art-note">LIGHT / FORM<br><strong>01 — 04</strong></div>
                    <div class="art-stamp">H</div>
                </div>
            </section>

            <section class="feature-strip" id="how-it-works">
                <div><span>۰۱</span><strong>محصولت را آپلود کن</strong><p>یک عکس کافی است.</p></div>
                <div><span>۰۲</span><strong>سبکت را انتخاب کن</strong><p>Luxury تا Minimal.</p></div>
                <div><span>۰۳</span><strong>خروجی را دریافت کن</strong><p>آماده انتشار و دانلود.</p></div>
            </section>
        </main>
    </div>

    <div class="auth-modal" data-auth-modal hidden>
        <div class="modal-backdrop" data-close-auth></div>
        <section class="auth-panel" role="dialog" aria-modal="true" aria-labelledby="auth-title">
            <button class="close-button" data-close-auth aria-label="بستن">×</button>
            <p class="eyebrow">WELCOME TO HALE</p>
            <h2 id="auth-title">شروع ساخت</h2>
            <p class="modal-copy">با ایمیل یا شماره موبایل وارد شو.</p>
            <div class="auth-tabs"><button class="active" data-auth-tab="login">ورود</button><button data-auth-tab="register">ثبت‌نام</button></div>
            <form data-auth-form>
                <label data-name-field hidden>نام و نام خانوادگی<input name="name" autocomplete="name" placeholder="مثلاً سارا احمدی"></label>
                <label>ایمیل یا شماره موبایل<input name="identifier" autocomplete="username" inputmode="email" placeholder="sara@example.com یا +98912..."></label>
                <label>رمز عبور<input name="password" type="password" autocomplete="current-password" placeholder="حداقل ۸ کاراکتر"></label>
                <label data-confirm-field hidden>تکرار رمز عبور<input name="password_confirmation" type="password" autocomplete="new-password"></label>
                <p class="form-message" data-form-message role="alert"></p>
                <button class="primary-button full-button" type="submit" data-submit-auth>ورود به Hale <span>←</span></button>
            </form>
            <p class="modal-footnote">با ثبت‌نام، مسیر ساخت اولین محتوایت شروع می‌شود.</p>
        </section>
    </div>
</body>
</html>
