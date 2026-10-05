<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/brand/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/brand/apple-touch-icon.png">
    <title>میز کار استودیو | حله</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="dashboard-page">
    {{-- Ambient Glow --}}
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
                <a href="/create" class="btn-aurora" style="padding: 8px 18px; font-size: 13px;">
                    <span>+ ساخت محتوای جدید</span>
                </a>
                <div class="credit-pill">
                    <span>اعتبار کیف پول:</span>
                    <strong data-credit>--</strong>
                    <span data-quota hidden style="font-size: 11px; color: var(--text-muted); font-weight: 600;"></span>
                    <a href="/pricing" style="font-size: 12px; margin-right: 4px; color: var(--primary);">+ شارژ</a>
                </div>
                <button class="btn-glass" data-install-pwa hidden style="padding: 6px 14px; font-size: 13px;">
                    📲 نصب حله
                </button>
                <button class="btn-glass" data-logout style="padding: 6px 14px; font-size: 13px;">
                    خروج
                </button>
            </div>
        </header>

        <main class="dashboard-main">
            {{-- Email Verification Banner --}}
            <div data-unverified-banner hidden style="margin-bottom: 24px; padding: 14px 20px; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 14px; display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 10px; font-size: 13px; color: #FCD34D;">
                    <span style="font-size: 18px;">⚠️</span>
                    <span>ایمیل شما هنوز تأیید نشده است. برای امکان ساخت محتوا و خرید اشتراک، لطفاً ایمیل خود را تأیید کنید.</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button class="small-button" data-resend-verification style="background: rgba(245, 158, 11, 0.25); border-color: rgba(245, 158, 11, 0.5); color: #FFF; cursor: pointer;">ارسال مجدد ایمیل فعال‌سازی</button>
                    <span data-resend-status style="font-size: 12px; color: #FCD34D;"></span>
                </div>
            </div>

            {{-- Email Verified Success Banner --}}
            <div data-verified-success-banner hidden style="margin-bottom: 24px; padding: 14px 20px; background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.35); border-radius: 14px; display: flex; align-items: center; gap: 10px; font-size: 13px; color: #6EE7B7;">
                <span style="font-size: 18px;">✓</span>
                <span>ایمیل شما با موفقیت تأیید شد. اکنون دسترسی کامل به استودیو و ساخت محتوا دارید!</span>
            </div>

            {{-- Account Banned Warning Banner --}}
            <div data-banned-banner hidden style="margin-bottom: 24px; padding: 14px 20px; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 14px; display: flex; align-items: center; gap: 10px; font-size: 13px; color: #FCA5A5;">
                <span style="font-size: 18px;">⛔</span>
                <span data-banned-message>حساب کاربری شما مسدود شده است. برای اطلاعات بیشتر با پشتیبانی تماس بگیرید.</span>
            </div>

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
                        <label class="bulk-select-all" data-bulk-all-wrap hidden>
                            <input type="checkbox" data-bulk-all aria-label="انتخاب همه محصولات"> انتخاب همه
                        </label>
                        <button class="small-button" type="button" data-bulk-toggle aria-pressed="false" title="انتخاب چند محصول و ساخت هم‌زمان خروجی"> پردازش دسته‌ای</button>
                        <button class="small-button" type="button" data-products-favorite-filter aria-pressed="false" title="نمایش فقط محصولات موردعلاقه">★ موردعلاقه‌ها</button>
                        <input class="glass-input" data-product-search type="search" placeholder="جستجوی محصول..." aria-label="جستجوی محصول" style="background: rgba(255,255,255,0.04); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 8px 14px; font-size: 13px; color: #FFFFFF; outline: 0; min-width: 200px;">
                        <button class="btn-aurora" data-open-product style="padding: 8px 16px; font-size: 13px;">
                            + افزودن کالا
                        </button>
                    </div>
                </div>

                <div class="product-grid" data-product-grid>
                    <p class="empty-state" style="color: var(--text-muted); padding: 32px 0;">در حال بارگذاری کتابخانه محصولات...</p>
                </div>

                <div class="bulk-bar" data-bulk-bar hidden>
                    <span class="bulk-count" data-bulk-count>۰ محصول انتخاب شده</span>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button class="btn-aurora" type="button" data-bulk-run style="padding: 8px 16px; font-size: 13px;">ساخت خروجی‌ها ✦</button>
                        <button class="small-button" type="button" data-bulk-cancel>انصراف</button>
                    </div>
                    <p class="form-message" data-bulk-message role="status" style="margin: 0; font-size: 13px;"></p>
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

                <div class="filter-bar" data-history-filters role="group" aria-label="فیلتر تاریخچه خروجی‌ها">
                    <input class="filter-search" type="search" data-history-search placeholder="جستجو در نام محصول یا پرامپت..." aria-label="جستجو در تاریخچه">
                    <select class="filter-select" data-history-type aria-label="نوع خروجی">
                        <option value="">همه نوع‌ها</option>
                        <option value="image">تصویر</option>
                        <option value="video">ویدیو</option>
                    </select>
                    <select class="filter-select" data-history-status aria-label="وضعیت خروجی">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="completed">آماده</option>
                        <option value="processing">در حال ساخت</option>
                        <option value="queued">در صف</option>
                        <option value="failed">ناموفق</option>
                        <option value="cancelled">لغو شد</option>
                    </select>
                    <input class="filter-date" type="date" data-history-from aria-label="از تاریخ">
                    <input class="filter-date" type="date" data-history-to aria-label="تا تاریخ">
                    <button class="small-button" type="button" data-history-favorite aria-pressed="false" title="نمایش فقط خروجی‌های موردعلاقه">★ موردعلاقه‌ها</button>
                    <button class="small-button" type="button" data-history-reset>پاک‌کردن فیلترها</button>
                </div>

                <div class="generation-list" data-generation-list>
                    <p class="empty-state" style="color: var(--text-muted); padding: 24px 0;">در حال بارگذاری سابقه محتواها...</p>
                </div>
            </section>

            {{-- Content Calendar --}}
            <section class="content-calendar" data-calendar style="margin-bottom: 60px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding-bottom: 18px; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h2 style="font-size: 22px; font-weight: 800; margin: 0 0 4px;">تقویم محتوا</h2>
                        <p style="color: var(--text-muted); font-size: 13px; margin: 0;">زمان‌بندی انتشار خروجی‌ها و مدیریت کمپین‌ها</p>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <button class="small-button" type="button" data-cal-prev aria-label="ماه قبل">→</button>
                        <strong data-cal-title style="font-size: 14px; min-width: 130px; text-align: center;"></strong>
                        <button class="small-button" type="button" data-cal-next aria-label="ماه بعد">←</button>
                        <button class="btn-glass" type="button" data-campaign-open style="padding: 6px 14px; font-size: 12px;">+ کمپین جدید</button>
                    </div>
                </div>

                <p class="form-message" data-cal-message role="status" style="margin: 0 0 10px; font-size: 13px;"></p>

                <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; margin-bottom: 6px; text-align: center; font-size: 11px; color: var(--text-muted);">
                    <span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span>
                </div>
                <div class="calendar-grid" data-cal-grid style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px;"></div>

                <div data-cal-day hidden style="margin-top: 18px; border: 1px solid var(--border-subtle); border-radius: 16px; padding: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 10px; flex-wrap: wrap;">
                        <strong data-cal-day-title style="font-size: 14px;"></strong>
                        <button class="small-button" type="button" data-cal-day-close>بستن ✕</button>
                    </div>
                    <div data-cal-day-list style="display: flex; flex-direction: column; gap: 8px;"></div>
                </div>

                <div data-campaign-form hidden style="margin-top: 18px; border: 1px solid var(--border-subtle); border-radius: 16px; padding: 18px; display: flex; flex-direction: column; gap: 12px;">
                    <strong style="font-size: 14px;">کمپین زمان‌بندی‌شده</strong>
                    <p style="margin: 0; font-size: 12px; color: var(--text-muted);">هر خروجی انتخاب‌شده یک روز در تقویم ثبت می‌شود؛ فاصلهٔ روزها را می‌توانید تنظیم کنید.</p>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <input type="text" data-campaign-name placeholder="نام کمپین (مثلاً هفتهٔ فروش)" maxlength="100" aria-label="نام کمپین" style="flex: 1; min-width: 180px; background: rgba(255,255,255,0.04); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 8px 12px; color: #FFFFFF; font-size: 13px; outline: 0;">
                        <input type="date" data-campaign-start aria-label="تاریخ شروع" style="background: rgba(255,255,255,0.04); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 8px 10px; color: #FFFFFF; font-size: 13px; outline: 0;">
                        <label style="font-size: 12px; color: var(--text-muted); display: flex; gap: 6px; align-items: center;">فاصلهٔ روزها
                            <input type="number" data-campaign-interval value="1" min="1" max="30" aria-label="فاصلهٔ روزها" style="width: 64px; background: rgba(255,255,255,0.04); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 6px 8px; color: #FFFFFF; font-size: 13px; outline: 0;">
                        </label>
                    </div>
                    <div data-campaign-options style="max-height: 190px; overflow: auto; border: 1px dashed var(--border-subtle); border-radius: 12px; padding: 10px; display: flex; flex-direction: column; gap: 6px; font-size: 13px;">
                        <span class="empty-state" style="font-size: 12px;">در حال دریافت خروجی‌های آماده...</span>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <button class="btn-aurora" type="button" data-campaign-save style="padding: 8px 16px; font-size: 13px;">زمان‌بندی کن ✦</button>
                        <button class="small-button" type="button" data-campaign-cancel>انصراف</button>
                        <p class="form-message" data-campaign-message style="margin: 0; font-size: 13px;"></p>
                    </div>
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
                    <button class="small-button" type="button" data-push-subscribe aria-pressed="false" hidden title="اعلان فوری روی مرورگر">🔔 اعلان فوری</button>
                </div>

                <div class="notification-list" data-notification-list>
                    <p class="empty-state" style="color: var(--text-muted); padding: 20px 0;">در حال دریافت اعلان‌ها...</p>
                </div>
            </section>

            {{-- Brand Kit --}}
            <section class="brand-kit-section" data-brand-kit style="margin-bottom: 60px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding-bottom: 18px; margin-bottom: 24px;">
                    <div>
                        <h2 style="font-size: 22px; font-weight: 800; margin: 0 0 4px;">کیت برند من</h2>
                        <p style="color: var(--text-muted); font-size: 13px; margin: 0;">رنگ‌ها، لحن و شعار برند در پرامپت همهٔ تولیدهات اعمال می‌شود</p>
                    </div>
                </div>
                <div class="brand-kit-grid">
                    <label class="brand-field">
                        <span>نام برند</span>
                        <input type="text" data-brand-name maxlength="60" placeholder="مثلاً برند آریا">
                    </label>
                    <label class="brand-field">
                        <span>رنگ اصلی</span>
                        <input type="color" data-brand-color="primary_color" value="#0E0F12">
                    </label>
                    <label class="brand-field">
                        <span>رنگ ثانویه</span>
                        <input type="color" data-brand-color="secondary_color" value="#FFFFFF">
                    </label>
                    <label class="brand-field">
                        <span>رنگ تأکیدی</span>
                        <input type="color" data-brand-color="accent_color" value="#D4AF37">
                    </label>
                    <label class="brand-field">
                        <span>فونت</span>
                        <input type="text" data-brand-font maxlength="40" placeholder="مثلاً Vazirmatn">
                    </label>
                    <label class="brand-field">
                        <span>لحن برند</span>
                        <input type="text" data-brand-tone maxlength="60" placeholder="مثلاً لوکس و مینیمال">
                    </label>
                    <label class="brand-field brand-field-wide">
                        <span>شعار برند (Tagline)</span>
                        <input type="text" data-brand-tagline maxlength="160" placeholder="مثلاً زیبایی در جزئیات">
                    </label>
                </div>
                <div style="display: flex; align-items: center; gap: 14px; margin-top: 18px;">
                    <button class="small-button" type="button" data-brand-save>ذخیرهٔ کیت برند</button>
                    <p class="form-message" data-brand-message style="margin: 0; font-size: 13px;"></p>
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
