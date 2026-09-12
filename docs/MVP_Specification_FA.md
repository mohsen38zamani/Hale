<div dir="rtl">

# MVP — Hale (AI Content Studio)

**نوع سند:** MVP Specification / Product Requirements Document (PRD)  
**نسخه:** 1.0  
**تاریخ:** ۱۴۰۴/۰۶/۱۷  
**وضعیت:** پیش‌نویس اولیه  
**مرجع:** [`Development_Roadmap_FA.md`](Development_Roadmap_FA.md) · [`AI_Content_Studio_Business_Plan_FA.md`](AI_Content_Studio_Business_Plan_FA.md)

---

## این سند چیست؟

| اصطلاح | معنی |
|--------|------|
| **MVP** | Minimum Viable Product — **حداقل محصول قابل استفاده** که یک مشکل واقعی را حل کند و ارزش پرداخت را ثابت کند |
| **MVP Spec / PRD** | سند مشخصات MVP — دقیقاً **چه چیزی** ساخته می‌شود، برای **چه کسی**، و **چه زمانی موفق** است |
| **Scope** | محدوده — چه چیزهایی **داخل** MVP هستند و چه چیزهایی **خارج** |

### تفاوت MVP Spec با Roadmap

| | **MVP Spec (این سند)** | **Development Roadmap** |
|---|------------------------|-------------------------|
| **نگاه** | محصول و کاربر | فنی و زمانی |
| **سؤال** | «چه چیزی بسازیم؟» | «به چه ترتیبی بسازیم؟» |
| **محتوا** | User Story، صفحات، معیار موفقیت | Sprint، Task، Stack |

## وضعیت پیاده‌سازی Backend در ۱۴۰۵/۰۶/۲۱

تا این نسخه، بخش‌های زیر از MVP در Backend پیاده‌سازی و تست شده‌اند:

- ثبت‌نام و ورود با ایمیل یا شماره موبایل؛ ثبت‌نام بدون ایمیل با حداقل یک شناسه
- Password Reset، Profile Update و تغییر رمز با الزام رمز فعلی
- Phone OTP با محدودیت تلاش، Provider قابل‌تعویض و فعال‌سازی یک‌باره Credit رایگان
- Product Asset deletion، Plan limit برای ویدئو و Regenerate
- Payment/Subscription، Checkout، Webhook امضاشده و Provider پیش‌فرض زرین‌پال

Frontend/PWA پایه شامل Landing، Auth، Dashboard، Product Library، Creative Builder و Generation History آماده است؛ اتصال کامل Progress/Result/Checkout، Provider واقعی AI، Notifications، Admin، Invoice و E2E هنوز تکمیل نشده‌اند.

---

# ۱. هدف MVP

## ۱.۱ جمله هدف (One-liner)

> **کاربر عکس محصولش را آپلود می‌کند، بدون نوشتن Prompt محتوای تبلیغاتی (عکس یا ویدئوی کوتاه) می‌سازد، Credit می‌پردازد و فایل را دانلود می‌کند.**

## ۱.۲ هدف تجاری

اثبات این فرضیه‌ها:

| # | فرضیه | چطور در MVP تست می‌شود |
|---|--------|-------------------------|
| 1 | کاربر بدون آموزش اولین خروجی را می‌سازد | Activation Rate، Time to First Value |
| 2 | خروجی از نظر کاربر قابل استفاده است | Feedback 👍/👎، مصاحبه با ۲۰ beta user |
| 3 | کاربر دوباره برمی‌گردد | Second Generation Rate در ۷ روز |
| 4 | کاربر حاضر است بپردازد | Free → Paid Conversion |
| 5 | هزینه AI کمتر از درآمد است | Gross Margin per Generation |

## ۱.۳ هدف فنی

- Pipeline کامل **Upload → Creative Engine → AI → Download** کار کند
- هزینه هر Generation از روز اول **Track** شود
- معماری **Provider Abstraction** برای تعویض AI بدون بازنویسی

## ۱.۴ مدت و منابع (تخمین)

| مورد | مقدار |
|------|--------|
| مدت توسعه | ۸–۱۲ هفته (بعد از Phase 0) |
| تیم حداقلی | ۱ Backend (Laravel) + ۱ Frontend (PWA) + ۱ Product/Founder |
| Beta Users | ۲۰ نفر از ICP قبل از Launch عمومی |

---

# ۲. مشتری هدف MVP (ICP)

برای MVP **یک** segment انتخاب می‌شود — نه همه:

> **فروشگاه‌های آنلاین پوشاک و عطر که:**
> - صفحه اینستاگرام فعال با حداقل ۲,۰۰۰ فالوور دارند
> - خودشان یا با عکاس محتوا تولید می‌کنند
> - حداقل هفته‌ای یک پست منتشر می‌کنند

### Persona اصلی: «سارا — صاحب بوتیک آنلاین»

| | |
|---|---|
| **سن** | ۲۸–۴۰ |
| **کار** | فروش لباس/عطر در اینستاگرام |
| **درد** | عکاسی و طراحی گران و زمان‌بر است |
| **مهارت فنی** | پایین — Prompt نمی‌نویسد |
| **دستگاه** | ۹۰٪ موبایل |
| **انتظار** | «عکس محصولم رو بدم، پست آماده بگیرم» |

---

# ۳. ارزش پیشنهادی MVP

## پیام اصلی

> **عکس محصولت رو بده؛ محتوای تبلیغاتی آماده اینستاگرام تحویل بگیر — بدون Prompt، بدون عکاس.**

## آنچه MVP تحویل می‌دهد

| ✅ دارد | ❌ ندارد (بعداً) |
|---------|------------------|
| آپلود و ذخیره محصول | Brand Kit |
| ساخت عکس تبلیغاتی | Campaign (چند Asset یکجا) |
| ساخت ویدئوی کوتاه با مدت انتخابی کاربر | Content Calendar |
| انتخاب Goal / Style / Format | Conversational Editing |
| حالت «خودت بهترینش رو بساز» | Team / Workspace |
| Credit + اشتراک + پرداخت | API عمومی |
| تاریخچه و دانلود | White Label |
| Watermark در Free | Auto Publish |

---

# ۴. User Journey اصلی (Happy Path)

```text
Landing Page
      ↓
Sign Up (ایمیل + رمز)
      ↓
Dashboard — «امروز چی می‌خوای بسازی؟»
      ↓
[📸 عکس محصول]  یا  [🎬 Reel]  یا  [📱 پست اینستاگرام]
      ↓
آپلود عکس محصول (یا انتخاب از کتابخانه)
      ↓
انتخاب هدف: فروش / معرفی / برندینگ / ...
      ↓
انتخاب سبک: Luxury / Minimal / Cinematic / ...
      ↓
انتخاب فرمت: Post / Story / Reel
      ↓
انتخاب مدت ویدئو از گزینه‌های پشتیبانی‌شده (فقط برای Video)
      ↓
[✨ خودت بهترینش رو بساز]  یا  [ساخت محتوا]
      ↓
پیش‌نمایش تنظیمات (اختیاری)
      ↓
[Generate] — Credit رزرو می‌شود
      ↓
«در حال ساخت... ████░░ ۸۰٪»
      ↓
«محتوایت آماده است 🎉»
      ↓
پیش‌نمایش + 👍/👎 + Download
      ↓
(Credit تمام شد؟) → Pricing → پرداخت → ادامه
```

**هدف UX:** کمترین کلیک تا اولین خروجی — **Time to First Value < ۵ دقیقه**

---

# ۵. صفحات و قابلیت‌های MVP

## ۵.۱ صفحات عمومی (بدون Login)

| صفحه | توضیح | اولویت |
|------|--------|--------|
| Landing | Hero، Before/After، CTA، Pricing خلاصه | P0 |
| Login | ایمیل + رمز | P0 |
| Register | نام، ایمیل، رمز، تأیید قوانین | P0 |
| Forgot Password | بازیابی رمز | P0 |
| Pricing | مقایسه پلن‌ها | P0 |
| Terms / Privacy | ToS، Content Policy، مالکیت خروجی | P0 |

## ۵.۲ صفحات احراز هویت‌شده

| صفحه | توضیح | اولویت |
|------|--------|--------|
| Dashboard | «امروز چی می‌خوای بسازی؟» + کارت‌های نوع محتوا | P0 |
| Upload Product | Drag & drop، دوربین موبایل | P0 |
| Product Library | Grid محصولات، جستجو، حذف | P0 |
| Creative Builder | Wizard: Product → Goal → Style → Format → Video Duration | P0 |
| Generation Progress | Progress bar، وضعیت async | P0 |
| Generation Result | Preview، Download، Regenerate، Feedback | P0 |
| History | لیست Generationها، فیلتر نوع/تاریخ | P0 |
| Credits & Billing | موجودی Credit، تاریخچه، خرید | P0 |
| Checkout | انتخاب پلن → درگاه → تأیید | P0 |
| Profile / Settings | نام، ایمیل، رمز | P1 |
| Notifications | «Generation آماده شد»، «Credit کم است» | P1 |

## ۵.۳ Admin (حداقلی)

| صفحه | توضیح | اولویت |
|------|--------|--------|
| Users List | جستجو، مشاهده | P1 |
| User Detail | Generations، Credits، Subscription | P1 |
| Refund Credit | دستی برگشت Credit | P1 |
| Generations Monitor | وضعیت صف، خطاها | P1 |

---

# ۶. Feature List — داخل Scope

## ۶.۱ Authentication & Users

| Feature | جزئیات | Acceptance Criteria |
|---------|--------|---------------------|
| Register | email یا phone + password؛ حداقل یکی از دو شناسه | کاربر جدید در < ۳۰ ثانیه ثبت‌نام کند |
| Login / Logout | email یا phone به‌عنوان identifier + Sanctum token | Login موفق → redirect به Dashboard |
| Password Reset | Email link | Reset در < ۵ دقیقه |
| Phone Verify (Free) | OTP از طریق Provider پیامک برای فعال‌سازی یک‌باره Credit رایگان | جلوگیری از abuse چند اکانت |

## ۶.۲ Products & Media

| Feature | جزئیات | Acceptance Criteria |
|---------|--------|---------------------|
| Upload Product Image | JPG/PNG/WebP، max 10MB | Thumbnail در < ۳ ثانیه |
| Product Library | CRUD، pagination | لیست ۵۰+ محصول بدون lag |
| Product Metadata | name (required), description (optional) | — |

## ۶.۳ Creative Builder

| Feature | جزئیات | Acceptance Criteria |
|---------|--------|---------------------|
| Goal Selection | ۶ گزینه از پیش‌تعریف | بدون input متنی آزاد |
| Style Selection | ۸+ سبک تصویری (کارت) | انتخاب با یک tap |
| Format Selection | Post, Story, Reel, TikTok | Aspect ratio درست |
| Environment | Studio, Nature, Luxury, ... | اختیاری در wizard |
| Video Options | Camera movement + مدت ویدئو | فقط وقتی format=video؛ مدت از گزینه‌های پشتیبانی‌شده انتخاب شود |
| Auto Best Mode | سیستم Goal+Product → settings | پیشنهاد قبل از Generate |
| Creative Brief | LLM → brief ساختاریافته | Logged برای debug |

**گزینه‌های Goal (MVP):**

```text
○ معرفی محصول
○ افزایش فروش
○ برندینگ
○ تخفیف / پیشنهاد ویژه
○ معرفی محصول جدید
○ جذب مخاطب
```

**گزینه‌های Style (MVP):**

```text
Luxury · Minimal · Cinematic · Natural · Colorful · Dark · Professional · Fashion
```

**گزینه‌های Format (MVP):**

```text
Instagram Post (1:1) · Instagram Story (9:16) · Instagram Reel (9:16) · TikTok (9:16)
```

## ۶.۴ Generation (Core)

| Feature | جزئیات | Acceptance Criteria |
|---------|--------|---------------------|
| Image Generation | Text/Image-to-Image | Success rate > 90% |
| Video Generation | Image-to-Video با مدت انتخابی کاربر | Success rate > 85%؛ Provider/Model باید مدت را پشتیبانی کند |
| Async Queue | queued → processing → done/failed | HTTP timeout نشود |
| Progress UI | Polling هر ۲–۳ ثانیه | کاربر وضعیت ببیند |
| Retry | تا ۲ بار با backoff | Failed → Credit refund |
| Regenerate | همان settings، خروجی جدید | Credit دوباره کسر |
| Download | PNG/JPG (image), MP4 (video) | Direct download |
| History | ۹۰ روز نگهداری | Filter by type/date |
| Feedback | 👍 / 👎 بعد از هر output | ذخیره در DB |
| Watermark | فقط Free plan | قابل مشاهده در preview |

## ۶.۵ AI Infrastructure (Backend — invisible to user)

| Feature | جزئیات |
|---------|--------|
| AI Gateway | Single entry برای همه AI calls |
| Model Router | Economic / Standard بر اساس plan |
| Provider Adapter | Google (Gemini, Imagen, Veo) + Fallback |
| Cost Tracking | provider, model, tokens, $ cost per job |
| Content Moderation | فیلتر Prompt قبل از Generate |
| Circuit Breaker | سقف هزینه روزانه AI |

## ۶.۶ Credits & Billing

| Feature | جزئیات | Acceptance Criteria |
|---------|--------|---------------------|
| Credit Balance | نمایش در header | Real-time بعد از generation |
| Credit Reservation | Reserve → Settle / Refund | Double-spend نشود |
| Credit Ledger | تمام تراکنش‌ها logged | Audit trail |
| Subscription Plans | Free, Starter, Creator | Config-based |
| Payment | Zarinpal یا IDPay | پرداخت موفق → Credit/plan فعال |
| Paywall | بدون Credit → block Generate | CTA به Pricing |
| Low Credit Alert | Email/In-app | زیر ۲۰٪ موجودی |

### پلن‌های MVP

| پلن | Credit ماهانه | Image | Video | Watermark | قیمت (تست) |
|-----|---------------|-------|----------|-----------|------------|
| **Free** | ۳۰ (~۳ Gen) | ✅ محدود | ❌ | ✅ | رایگان |
| **Starter** | ۲۰۰ | ✅ | ۲ عدد | ❌ | ~۴۹۹,۰۰۰ تومان |
| **Creator** | ۵۰۰ | ✅ | ۸ عدد | ❌ | ~۱,۲۹۹,۰۰۰ تومان |

### Credit Cost (MVP — قابل تنظیم)

| نوع | Credit |
|-----|--------|
| Standard Image | ۱۰ |
| Premium Image | ۲۵ |
| Video (Standard) | پویا؛ بر اساس مدت انتخابی و مدل |
| Video (Premium) | پویا؛ بر اساس مدت انتخابی و مدل |

> **توجه:** پیش از Generate، Credit موردنیاز بر اساس مدت انتخابی، کیفیت و Provider/Model محاسبه و به کاربر نمایش داده می‌شود. اعداد نهایی بعد از اندازه‌گیری Cost واقعی Provider تنظیم می‌شوند.

## ۶.۷ Notifications

| Event | Channel |
|-------|---------|
| Generation completed | In-app + Email |
| Generation failed | In-app + Email |
| Credit low | In-app |
| Payment success | Email |
| Welcome | Email |

## ۶.۸ Non-Functional (MVP)

| مورد | هدف |
|------|-----|
| RTL + فارسی | UI کامل RTL |
| Mobile-first | ۳۲۰px+ responsive |
| PWA | Add to Home Screen |
| API Response (p95) | < 500ms (غیر Generation) |
| Image Generation time | < 45 sec (median) |
| Video Generation time | < 3 min (median) |
| Uptime | 99% (staging: best effort) |

---

# ۷. Feature List — خارج از Scope (NOT in MVP)

این‌ها **عمداً** ساخته نمی‌شوند تا MVP زود Launch شود:

```text
❌ Brand Kit
❌ Campaign Generator (چند Asset)
❌ Content Calendar
❌ Conversational Editing («پس‌زمینه روشن‌تر کن»)
❌ Caption / Hashtag Generator
❌ Templates Library
❌ Team / Organization / Workspace
❌ Agency / Client management
❌ Approval Workflow
❌ API عمومی
❌ White Label
❌ Social Media Auto Publish
❌ Voice / Avatar / Music
❌ 3D Generation
❌ Full Video Editor
❌ Marketplace / Community
❌ Native Mobile App (iOS/Android)
❌ Multi-language (فقط فارسی در MVP)
❌ Jalali calendar / مناسبت‌های ایرانی (Phase 3)
```

---

# ۸. User Stories (خلاصه)

## Epic 1: Onboarding
- **US-1:** به عنوان کاربر جدید، می‌خواهم در کمتر از ۱ دقیقه ثبت‌نام کنم تا سریع شروع کنم.
- **US-2:** به عنوان کاربر Free، می‌خواهم با تأیید موبایل Credit رایگان بگیرم.

## Epic 2: Product
- **US-3:** به عنوان فروشنده، می‌خواهم عکس محصولم را از موبایل آپلود کنم.
- **US-4:** به عنوان کاربر، می‌خواهم محصولات قبلی‌ام را ببینم و دوباره استفاده کنم.

## Epic 3: Create Content
- **US-5:** به عنوان کاربر، می‌خواهم بدون نوشتن Prompt محتوا بسازم.
- **US-6:** به عنوان کاربر، می‌خواهم «خودت بهترینش رو بساز» بزنم و سیستم تصمیم بگیرد.
- **US-7:** به عنوان کاربر، می‌خواهم Progress ساخت را ببینم چون منتظر می‌مانم.
- **US-8:** به عنوان کاربر، می‌خواهم خروجی را دانلود و در اینستاگرام استفاده کنم.

## Epic 4: Pay
- **US-9:** به عنوان کاربر، وقتی Credit تمام شد می‌خواهم به راحتی پلن بخرم.
- **US-10:** به عنوان کاربر، می‌خواهم بدانم هر Generate چند Credit مصرف می‌کند.

## Epic 5: Trust & Quality
- **US-11:** به عنوان کاربر، می‌خواهم بعد از Generate نظر بدهم (👍/👎).
- **US-12:** به عنوان کاربر، اگر Generate شکست خورد Credit برگردد.

---

# ۹. API Endpoints (MVP)

## Auth
```http
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/logout
POST   /api/auth/forgot-password
POST   /api/auth/reset-password
POST   /api/auth/phone/send-code   # Request OTP
POST   /api/auth/verify-phone      # Free credit activation
```

## Products
```http
GET    /api/products
POST   /api/products
GET    /api/products/{id}
PUT    /api/products/{id}
DELETE /api/products/{id}
POST   /api/products/{id}/assets   # Upload image
DELETE /api/products/{id}/assets/{asset}
```

## Creative & Generations
```http
GET    /api/creative/options       # Goals, styles, formats
POST   /api/creative/preview       # Auto Best suggestion
POST   /api/generations            # Start generation
GET    /api/generations            # History list
GET    /api/generations/{id}       # Status + result
POST   /api/generations/{id}/retry
POST   /api/generations/{id}/regenerate
POST   /api/generations/{id}/feedback   # 👍/👎
GET    /api/generations/{id}/download
```

## Billing
```http
GET    /api/credits/balance
GET    /api/credits/transactions
GET    /api/plans
POST   /api/subscriptions/checkout
POST   /api/webhooks/payment       # Signed adapter webhook
GET    /api/payments/zarinpal/callback
```

## User
```http
GET    /api/user/profile
PUT    /api/user/profile
GET    /api/user/notifications
```

---

# ۱۰. Data Model (MVP)

```text
users
├── id, name, email, password, phone, phone_verified_at
├── credits_balance, plan_id
└── timestamps

products
├── id, user_id, name, description, status
└── timestamps

product_assets
├── id, product_id, media_asset_id, is_primary
└── timestamps

media_assets
├── id, user_id, disk, path, mime, size, width, height
└── timestamps

creative_projects
├── id, user_id, product_id
├── goal, style, format, environment, video_duration_seconds, video_settings (JSON)
└── timestamps

generations
├── id, user_id, creative_project_id
├── type (image|video), status (queued|processing|completed|failed)
├── provider, model, prompt_hash
├── credits_reserved, credits_charged
├── cost_usd, processing_time_ms
├── output_media_id, error_message
├── feedback (like|dislike|null)
└── timestamps

generation_jobs
├── id, generation_id, queue_job_id, attempt, status
└── timestamps

credit_transactions
├── id, user_id, type (purchase|reserve|charge|refund|bonus)
├── amount, balance_after, reference_type, reference_id
└── timestamps

subscriptions
├── id, user_id, plan_key, status, starts_at, ends_at
└── timestamps

payments
├── id, user_id, amount, gateway, gateway_ref, status
└── timestamps

usage_logs
├── id, generation_id, provider, model, input_tokens, output_tokens
├── cost_usd, metadata (JSON)
└── timestamps

notifications
├── id, user_id, type, data (JSON), read_at
└── timestamps
```

> **نکته:** `organizations` در schema لحاظ می‌شود (nullable `organization_id`) ولی UI Team در MVP فعال نیست.

---

# ۱۱. Wireframe — Dashboard (متنی)

```text
┌─────────────────────────────────────┐
│  Hale          🔔   💎 ۱۲۰ Credit   │
├─────────────────────────────────────┤
│                                     │
│   امروز چی می‌خوای بسازی؟           │
│                                     │
│  ┌─────────┐  ┌─────────┐          │
│  │ 📸      │  │ 📱      │          │
│  │ عکس     │  │ پست     │          │
│  │ محصول   │  │ اینستا  │          │
│  └─────────┘  └─────────┘          │
│  ┌─────────┐  ┌─────────┐          │
│  │ 🎬      │  │ 📲      │          │
│  │ Reel    │  │ استوری  │          │
│  └─────────┘  └─────────┘          │
│                                     │
│  ─── محصولات اخیر ───               │
│  [img] [img] [img]  → همه          │
│                                     │
│  ─── آخرین ساخت‌ها ───              │
│  ✅ عکس لوکس — ۲ ساعت پیش          │
│  ⏳ Reel — در حال ساخت...           │
│                                     │
├─────────────────────────────────────┤
│  🏠 خانه   📦 محصولات   📜 تاریخ  │
└─────────────────────────────────────┘
```

---

# ۱۲. Definition of Done — MVP Launch

MVP وقتی **Launch** می‌شود که **همه** موارد زیر ✅ باشند:

## محصول
- [ ] Happy Path کامل بدون باگ critical
- [ ] ۲۰ beta user تست کرده‌اند
- [ ] Time to First Value < ۵ دقیقه (median)
- [ ] Generation Success Rate > 90% (image)
- [ ] Feedback 👍/👎 فعال

## تجاری
- [ ] حداقل ۳ beta user «حاضرم بپردازم» گفته‌اند
- [ ] Pricing page live
- [ ] ToS + Content Policy منتشر شده
- [ ] درگاه پرداخت تست شده (real transaction)

## فنی
- [ ] Cost per generation قابل گزارش
- [ ] Admin panel برای refund Credit
- [ ] Backup DB روزانه
- [ ] Error tracking (Sentry) فعال
- [ ] CI: test + deploy staging

## Go / No-Go (بعد از ۳۰ روز beta)

| معیار | Go ✅ | No-Go ❌ |
|--------|-------|----------|
| Activation (اولین Gen) | > 60% signup | < 40% |
| Second Generation (۷ روز) | > 30% | < 15% |
| 👍 Rate | > 50% | < 30% |
| Free → Paid | > 5% | < 2% |
| Gross Margin | > 50% | < 30% |

---

# ۱۳. ریسک‌های MVP

| ریسک | Impact | Mitigation |
|------|--------|------------|
| AI Provider از ایران block | 🔴 Critical | Phase 0 POC + Fallback |
| کیفیت خروجی ضعیف | 🟠 High | Auto Best + Feedback loop |
| Video cost بالا | 🟠 High | محدود در Free/Starter |
| Free abuse | 🟡 Medium | Phone verify |
| Recurring payment | 🟡 Medium | تست زودهنگام Zarinpal |
| Scope creep | 🟡 Medium | این سند = مرجع — هر feature خارج لیست = No |

---

# ۱۴. North Star Metric (MVP)

قبل از Campaign Generator:

> **تعداد Generation موفق به‌ازای هر کاربر فعال در هفته**

| Metric | هدف MVP (۳۰ روز beta) |
|--------|------------------------|
| Signups | ۱۰۰+ |
| Activated (≥1 Gen) | ۶۰+ |
| Weekly Active Creators | ۳۰+ |
| Avg Gens / Active User / Week | ≥ 2 |
| Paid Subscribers | ۱۰+ |

---

# ۱۵. چک‌لیست Launch

```text
Pre-Launch
  □ Staging tested end-to-end
  □ 20 beta users onboarded
  □ Payment gateway live
  □ Legal pages published
  □ Monitoring & alerts configured
  □ Backup verified

Launch Day
  □ Deploy production
  □ Smoke test production
  □ Beta users notified
  □ Landing page CTA active

Post-Launch (Week 1)
  □ Daily KPI review
  □ Support channel ready (Telegram/WhatsApp)
  □ Bug triage daily
  □ Collect feedback from beta users
```

---

# ۱۶. جمع‌بندی

| سؤال | پاسخ |
|------|------|
| **MVP چیه؟** | آپلود محصول → ساخت عکس/Reel → Credit → Download |
| **برای کی؟** | فروشگاه آنلاین پوشاک/عطر با اینستاگرام فعال |
| **چقدر طول می‌کشه؟** | ۸–۱۲ هفته (بعد از Phase 0) |
| **چطور موفقه؟** | کاربر بدون Prompt خروجی بگیرد و بپردازد |
| **چی نمی‌سازیم؟** | Campaign، Brand Kit، Team، API — Phase 2+ |

---

*این سند با [`Development_Roadmap_FA.md`](Development_Roadmap_FA.md) هم‌راستاست. Roadmap = **چطور و کی** بسازیم؛ این سند = **چه چیزی** بسازیم.*

</div>
