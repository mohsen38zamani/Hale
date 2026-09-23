<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>میز کار استودیو | Hale</title>
    @vite(['resources/css/app.css', 'resources/css/landing.css', 'resources/js/app.js'])
</head>
<body class="dashboard-page">
    {{-- Ambient Glow --}}
    <div class="aurora-mesh" aria-hidden="true">
        <div class="aurora-orb aurora-orb-1" style="opacity: 0.25;"></div>
        <div class="aurora-orb aurora-orb-2" style="opacity: 0.25;"></div>
    </div>

    <div class="app-shell" style="position: relative; z-index: 1;">
        <header class="topbar">
            <a class="brand" href="/">H<span>•</span>le</a>
            <div class="topbar-actions">
                <a href="/create" class="btn-aurora" style="padding: 8px 18px; font-size: 13px;">
                    <span>+ ساخت محتوای جدید</span>
                </a>
                <div class="credit-pill">
                    <span>اعتبار کیف پول:</span>
                    <strong data-credit>--</strong>
                    <a href="/pricing" style="font-size: 12px; margin-right: 4px; color: var(--primary);">+ شارژ</a>
                </div>
                <button class="btn-glass" data-install-pwa hidden style="padding: 6px 14px; font-size: 13px;">
                    📲 نصب Hale
                </button>
                <button class="btn-glass" data-logout style="padding: 6px 14px; font-size: 13px;">
                    خروج
                </button>
            </div>
        </header>

        <main class="dashboard-main">
            {{-- Welcome & Heading --}}
            <div class="dashboard-heading">
                <div>
                    <div class="badge-glow" style="margin-bottom: 8px;">
                        <span>میز کار اختصاصی</span>
                    </div>
                    <h1>امروز چه محتوایی خلق می‌کنید؟</h1>
                    <p data-welcome>در حال همگام‌سازی استودیو...</p>
                </div>
            </div>

            {{-- Quick Action Cards --}}
            <section class="create-grid">
                <a class="create-card" href="/create">
                    <div style="font-size: 24px; margin-bottom: 12px;">📸</div>
                    <strong>عکاسی محصول استودیویی</strong>
                    <small>تبدیل عکس موبایلی به صحنه لوکس با نورپردازی هالیوودی (فرمت 1:1)</small>
                    <div style="margin-top: 18px; color: var(--primary); font-size: 13px; font-weight: 600;">ورود به استودیو ↗</div>
                </a>

                <a class="create-card" href="/create">
                    <div style="font-size: 24px; margin-bottom: 12px;">🎬</div>
                    <strong>ویدیوی متحرک Reels و Story</strong>
                    <small>تولید ریلز عمودی ۹:۱۶ با افکت‌های نوری و ترنزیشن‌های حرکتی محصول</small>
                    <div style="margin-top: 18px; color: var(--accent-pink); font-size: 13px; font-weight: 600;">ساخت ریلز اینستاگرام ↗</div>
                </a>

                <button class="create-card" data-open-product style="background: transparent; border: 1px dashed var(--border-highlight); cursor: pointer; text-align: right;">
                    <div style="font-size: 24px; margin-bottom: 12px;">📦</div>
                    <strong>افزودن محصول جدید به کتابخانه</strong>
                    <small>آپلود عکس خام جدید برای استفاده در تمامی سناریوهای آینده</small>
                    <div style="margin-top: 18px; color: var(--accent-cyan); font-size: 13px; font-weight: 600;">+ بارگذاری فایل</div>
                </button>
            </section>

            {{-- Product Library Section --}}
            <section class="product-library" style="margin-bottom: 60px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding-bottom: 18px; margin-bottom: 24px;">
                    <div>
                        <h2 style="font-size: 22px; font-weight: 800; margin: 0 0 4px;">کتابخانه محصولات</h2>
                        <p style="color: var(--text-muted); font-size: 13px; margin: 0;">محصولات ذخیره‌شده جهت تولید نامحدود محتوا</p>
                    </div>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <input class="glass-input" data-product-search type="search" placeholder="جستجوی محصول..." aria-label="جستجوی محصول" style="background: rgba(255,255,255,0.04); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 8px 14px; font-size: 13px; color: #FFFFFF; outline: 0; min-width: 200px;">
                        <button class="btn-aurora" data-open-product style="padding: 8px 16px; font-size: 13px;">
                            + افزودن کالا
                        </button>
                    </div>
                </div>

                <div class="product-grid" data-product-grid>
                    <p class="empty-state" style="color: var(--text-muted); padding: 32px 0;">در حال بارگذاری کتابخانه محصولات...</p>
                </div>

                <div class="library-pagination" data-product-pagination hidden style="display: flex; justify-content: center; align-items: center; gap: 16px; margin-top: 24px;">
                    <button class="small-button" data-product-prev>صفحه قبلی</button>
                    <span data-product-page style="color: var(--text-muted); font-size: 13px;"></span>
                    <button class="small-button" data-product-next>صفحه بعدی</button>
                </div>
            </section>

            {{-- Recent Generations Section --}}
            <section class="generation-history" style="margin-bottom: 60px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding-bottom: 18px; margin-bottom: 24px;">
                    <div>
                        <h2 style="font-size: 22px; font-weight: 800; margin: 0 0 4px;">تاریخچه آخرین خروجی‌ها</h2>
                        <p style="color: var(--text-muted); font-size: 13px; margin: 0;">وضعیت تصاویر و ویدیوهای ثبت‌شده در صف</p>
                    </div>
                    <a class="btn-glass" href="/create" style="padding: 6px 14px; font-size: 12px;">
                        + ساخت جدید
                    </a>
                </div>

                <div class="generation-list" data-generation-list>
                    <p class="empty-state" style="color: var(--text-muted); padding: 24px 0;">در حال بارگذاری سابقه محتواها...</p>
                </div>
            </section>

            {{-- Notification Center --}}
            <section class="notification-center" style="margin-bottom: 60px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding-bottom: 18px; margin-bottom: 24px;">
                    <div>
                        <h2 style="font-size: 22px; font-weight: 800; margin: 0 0 4px;">اعلان‌های سیستم</h2>
                        <p style="color: var(--text-muted); font-size: 13px; margin: 0;">پیام‌های وضعیت تراکنش‌ها و آماده‌سازی محتوا</p>
                    </div>
                    <button class="small-button" data-read-all-notifications>خواندن همه</button>
                </div>

                <div class="notification-list" data-notification-list>
                    <p class="empty-state" style="color: var(--text-muted); padding: 20px 0;">در حال دریافت اعلان‌ها...</p>
                </div>
            </section>
        </main>
    </div>

    {{-- Product Upload / Edit Modal --}}
    <div class="product-modal auth-modal" data-product-modal hidden>
        <div class="modal-backdrop" data-close-product></div>
        <section class="auth-panel" role="dialog" aria-modal="true" style="max-width: 480px;">
            <button class="close-button" data-close-product aria-label="بستن">×</button>
            <h2 data-product-form-title style="font-size: 24px; font-weight: 800; margin: 0 0 8px;">محصول جدید</h2>
            <p class="modal-copy" style="color: var(--text-secondary); font-size: 13px; margin: 0 0 20px;">اطلاعات کالا و تصویر خام اولیه را بارگذاری کنید.</p>
            
            <form data-product-form>
                <label>
                    نام محصول
                    <input name="name" required placeholder="مثلاً عطر شب فرانسوی" style="margin-top: 6px;">
                </label>
                <label style="margin-top: 16px;">
                    توضیحات کوتاه
                    <input name="description" placeholder="رنگ، رایحه، جنس یا هر نکتهٔ شاخص محصول" style="margin-top: 6px;">
                </label>
                <label class="file-label" style="margin-top: 16px;">
                    عکس خام محصول (JPG یا PNG)
                    <input name="image" type="file" accept="image/jpeg,image/png,image/webp" style="margin-top: 6px; padding: 8px 12px; background: rgba(255,255,255,0.02);">
                </label>
                <p class="form-message" data-product-message role="alert"></p>
                <button class="btn-aurora full-button" type="submit" data-product-submit style="border-radius: 12px; padding: 12px;">
                    افزودن به کتابخانه <span>←</span>
                </button>
            </form>
        </section>
    </div>
</body>
</html>
