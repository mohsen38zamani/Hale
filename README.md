<div dir="rtl">

# Hale — استودیوی هوشمند تولید محتوای تبلیغاتی

Hale یک پلتفرم SaaS فارسی و Mobile-first برای ساخت محتوای تبلیغاتی با هوش مصنوعی است. کاربر عکس محصول را آپلود می‌کند، هدف و سبک را به‌صورت بصری انتخاب می‌کند و بدون نیاز به Prompt Engineering، تصویر تبلیغاتی یا ویدئوی کوتاه آماده انتشار دریافت می‌کند.

> **ارزش پیشنهادی:** عکس محصولت را بده؛ محتوای تبلیغاتی آماده اینستاگرام تحویل بگیر — بدون Prompt، بدون عکاس.

## وضعیت پروژه

پروژه در حال عبور از **Phase 0 به Phase 1** است. اسکلت Laravel، APIهای احراز هویت، محصولات و رسانه، Creative، Generation صف‌محور، AI Gateway، اعتبار و Billing پایه در مخزن پیاده‌سازی شده‌اند. احراز هویت با ایمیل یا موبایل، بازیابی رمز، OTP و Credit رایگان، Provider قابل‌تعویض SMS، محدودیت پلن، Regenerate و پرداخت با Provider پیش‌فرض زرین‌پال آماده است. frontend/PWA، اتصال Provider واقعی AI، اعلان‌ها، پنل مدیریت و بعضی قابلیت‌های عملیاتی هنوز باقی مانده‌اند.


```text
آپلود عکس محصول
      ↓
انتخاب هدف، سبک، فرمت و مدت ویدئو
تولید Async تصویر یا ویدئو با مدت انتخابی کاربر
      ↓
تسویه Credit، پیش‌نمایش و دانلود
```

### مشتری هدف MVP


### قابلیت‌های اصلی

- ثبت‌نام، ورود، بازیابی رمز و تأیید شماره موبایل برای Credit رایگان
- ورود با ایمیل یا شماره موبایل؛ ثبت‌نام با حداقل یکی از این دو شناسه
- آپلود JPG، PNG و WebP تا سقف ۱۰ مگابایت
- ثبت بازخورد 👍/👎 و Watermark برای پلن رایگان
- سیستم Credit با چرخه Reserve → Settle/Refund
- پلن اشتراکی، پرداخت داخلی و تاریخچه تراکنش‌ها
- Checkout و Callback زرین‌پال پشت abstraction قابل‌تعویض درگاه پرداخت
- پنل مدیریت حداقلی برای کاربران، Generationها و بازگشت Credit
- ثبت هزینه Provider و مدل برای هر Generation
- اعلان تکمیل یا شکست Generation و کاهش موجودی Credit

### خارج از محدوده MVP

Brand Kit، Campaign Generator، Content Calendar، ویرایش مکالمه‌ای، تیم و Workspace، API عمومی، White Label، انتشار خودکار شبکه‌های اجتماعی و اپلیکیشن Native به فازهای بعد منتقل شده‌اند.

## گزینه‌های تولید محتوا

| بخش | گزینه‌های اولیه |
|---|---|
| هدف | معرفی محصول، افزایش فروش، برندینگ، تخفیف، محصول جدید، جذب مخاطب |
| سبک | Luxury، Minimal، Cinematic، Natural، Colorful، Dark، Professional، Fashion |
| فرمت | Instagram Post (1:1)، Story (9:16)، Reel (9:16)، TikTok (9:16) |
| مدت ویدئو | انتخاب کاربر از مدت‌های پشتیبانی‌شده توسط Provider/Model |
| خروجی | تصویر استاندارد/پریمیوم، ویدئوی استاندارد/پریمیوم با مدت انتخابی |

## معماری پیشنهادی

Hale در MVP به‌صورت **Modular Monolith** توسعه می‌یابد. مرزهای Domain داخل یک برنامه Laravel حفظ می‌شوند و تمام Generationها به‌صورت Async اجرا خواهند شد.

```text
PWA (RTL / Mobile-first)
          │
          ▼
      Laravel API
          │
   ┌──────┼─────────┐
   ▼      ▼         ▼
 MySQL  Redis   S3 / MinIO
          │
          ▼
   Queue + Horizon
          │
          ▼
 Creative Engine
          │
          ▼
 Cost Estimator → Model Router → AI Gateway
                                  │
                         Provider Adapters
                                  │
                         Image / Video AI
```

### اصول فنی

1. **Provider Abstraction:** منطق کسب‌وکار به Provider مشخص وابسته نیست.
2. **Async-first:** درخواست HTTP منتظر پایان تولید تصویر یا ویدئو نمی‌ماند.
3. **Cost-aware:** هزینه واقعی، Retry، مدل و Provider هر عملیات ثبت می‌شود.
4. **Credit Safety:** اعتبار پیش از اجرا رزرو و پس از موفقیت تسویه یا در شکست بازگردانده می‌شود.
5. **Idempotency & Security:** Webhookهای AI و پرداخت باید idempotent و دارای تأیید امضا باشند.
6. **Guardrails:** Moderation، Rate Limit، Anti-fraud و Circuit Breaker هزینه در هسته سیستم قرار دارند.
7. **Mobile-first RTL:** رابط فارسی از عرض ۳۲۰ پیکسل و در قالب PWA طراحی می‌شود.

## پشته فناوری

| لایه | فناوری پیشنهادی |
|---|---|
| Backend | Laravel 11+، PHP 8.3+، REST API، Sanctum |
| Frontend | PWA با Inertia + Vue 3 یا Livewire 3 |
| Database | MySQL 8 / MariaDB 10.6+ |
| Cache / Queue | Redis 7+، Laravel Queue، Horizon |
| Object Storage | S3-compatible؛ MinIO در محیط محلی |
| Web Server | Nginx + PHP-FPM |
| Payment | Zarinpal یا IDPay از طریق Adapter |
| Monitoring | Sentry، Horizon و Telescope در توسعه |
| Testing / CI | PHPUnit و GitHub Actions |
| Local Environment | Docker Compose |

تصویر PHP موجود بر پایه `php:8.3-fpm-bookworm` است و افزونه‌های `pdo_mysql`، `mbstring`، `pcntl`، `bcmath`، `gd`، `zip`، `intl`، `opcache` و Redis را نصب می‌کند.

## ساختار پیشنهادی Domainها

```text
app/Domains/
├── Auth/
├── Users/
├── Products/
├── Media/
├── Creative/
├── Generations/
├── AI/
│   ├── Gateway/
│   ├── Router/
│   └── Providers/
├── Credits/
├── Billing/
├── Notifications/
└── Admin/
```

## راه‌اندازی محیط توسعه

### پیش‌نیازها

- Docker Engine و Docker Compose v2
- Git
- دسترسی معتبر به Providerهای AI انتخاب‌شده

### وضعیت فعلی راه‌اندازی

فایل‌های زیر اکنون آماده‌اند:

- `docker/php/Dockerfile`: محیط PHP 8.3 و Composer
- `docker/php/php.ini`: محدودیت حافظه ۵۱۲ مگابایت، آپلود ۲۰ مگابایت و Session مبتنی بر Redis
- `docker/nginx/default.conf`: سرو Laravel از مسیر `public/`
- `docker/mysql/init/01-init.sql`: ایجاد دیتابیس‌های `hale` و `hale_testing`
- `docker/scripts/minio-init.sh`: ایجاد Bucket در MinIO

تصویر PHP بر پایه PHP 8.3 است و افزونه‌های `pdo_mysql`، `pdo_sqlite`، `mbstring`، `pcntl`، `bcmath`، `gd`، `zip`، `intl`، `opcache` و Redis را نصب می‌کند. imageهای PHP و Composer از mirror عمومی ECR دریافت می‌شوند.

برای اجرای محیط توسعهٔ فعلی:

```bash
cp .env.example .env
docker compose build app
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
```

اجرای `docker compose up -d`، MinIO و سرویس یک‌بارمصرف `minio-init` را نیز اجرا می‌کند و bucket `hale` را به‌صورت خودکار می‌سازد. `minio-init` پس از ساخت bucket با کد خروج `0` متوقف‌شدن طبیعی دارد.

### آدرس سرویس‌ها

| سرویس | آدرس |
|---|---|
| Laravel / Nginx | http://localhost:8080 |
| Mailpit | http://localhost:8025 |
| MinIO API | http://localhost:9000 |
| MinIO Console | http://localhost:9001 |
| MySQL | `localhost:33060` |
| Redis | `localhost:63790` |

اجرای تست‌ها در image پروژه که `pdo_sqlite` دارد:

```bash
docker compose run --rm app php artisan test
```

## API برنامه‌ریزی‌شده MVP

```http
# Auth
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/logout
POST   /api/auth/forgot-password
POST   /api/auth/reset-password
POST   /api/auth/phone/send-code
POST   /api/auth/verify-phone

# Products
GET    /api/products
POST   /api/products
GET    /api/products/{id}
PUT    /api/products/{id}
DELETE /api/products/{id}
POST   /api/products/{id}/assets
DELETE /api/products/{id}/assets/{asset}

# Creative & Generations
GET    /api/creative/options
POST   /api/creative/preview
POST   /api/generations
GET    /api/generations
GET    /api/generations/{id}
POST   /api/generations/{id}/retry
POST   /api/generations/{id}/regenerate
POST   /api/generations/{id}/feedback
GET    /api/generations/{id}/download

# Credits & Billing
GET    /api/credits/balance
GET    /api/credits/transactions
GET    /api/plans
POST   /api/subscriptions/checkout
POST   /api/webhooks/payment       # Fake/adapter webhook با signature
GET    /api/payments/zarinpal/callback
```

این فهرست قرارداد هدف MVP است؛ بخشی از endpointها در حال حاضر پیاده‌سازی شده‌اند و وضعیت واقعی را باید از `routes/api.php` بررسی کرد.

### وضعیت فعلی Backend

| حوزه | وضعیت فعلی |
|---|---|
| Auth | Register/Login/Logout با ایمیل یا موبایل، Password Reset و Profile Update پیاده شده |
| Phone Verification | OTP با محدودیت تلاش، اتصال Provider پیامک و فعال‌سازی idempotent Credit رایگان |
| SMS Provider | `FakeSmsProvider` برای تست و `SmsIrProvider` برای `sms.ir` |
| Generation | Queue، Credit reserve/settle/refund، Plan limit، Retry و Regenerate |
| Billing | Payment/Subscription، Checkout، Webhook امضاشده و Callback زرین‌پال |
| باقی‌مانده | Frontend/PWA، Provider واقعی AI، Invoice، Notifications، Admin و E2E |

## مدل درآمد و Credit

مدل درآمدی پروژه **Subscription + Credits** است. اعداد زیر برای تست MVP قابل تنظیم‌اند:

| پلن | Credit ماهانه | تصویر | ویدئو | Watermark |
|---|---:|---|---|---|
| Free | ۳۰ | محدود | ندارد | دارد |
| Starter | ۲۰۰ | دارد | ۲ عدد | ندارد |
| Creator | ۵۰۰ | دارد | ۸ عدد | ندارد |

| عملیات | Credit پیشنهادی |
|---|---:|
| تصویر استاندارد | ۱۰ |
| تصویر پریمیوم | ۲۵ |
| ویدئوی استاندارد | پویا؛ بر اساس مدت انتخابی و مدل |
| ویدئوی پریمیوم | پویا؛ بر اساس مدت انتخابی و مدل |

پیش از Generate، مدت‌های قابل انتخاب متناسب با Provider/Model نمایش داده می‌شوند و Cost Estimator هزینه و Credit را بر اساس مدت انتخابی، کیفیت و مدل محاسبه می‌کند. قیمت پلن‌ها و Creditها باید پس از اندازه‌گیری هزینه واقعی Provider، نرخ Retry، Storage و Gross Margin بازتنظیم شوند. ویدئو در MVP نامحدود نخواهد بود.

## نقشه راه

| فاز | هدف | بازه تخمینی |
|---|---|---:|
| Phase 0 | اعتبارسنجی Provider، زیرساخت محلی، ERD و مصاحبه مشتری | ۲–۳ هفته |
| Phase 1 | MVP: محصول، Generation، Credit، Billing و Launch آزمایشی | ۸–۱۲ هفته |
| Phase 2 | Retention: Brand Kit، Template و AI Editing | ۶–۸ هفته |
| Phase 3 | Campaign، Content Calendar و تولید گروهی | ۸–۱۰ هفته |
| Phase 4 | تیم، Workspace، Agency، API و White Label | ۱۰–۱۲ هفته |
| Phase 5 | اتوماسیون بازاریابی و انتشار | تعیین‌نشده |

## معیارهای موفقیت MVP

- زمان رسیدن به اولین ارزش کمتر از ۵ دقیقه
- نرخ موفقیت تولید تصویر بیشتر از ۹۰٪
- نرخ موفقیت ویدئو بیشتر از ۸۵٪
- زمان پاسخ APIهای عادی در p95 کمتر از ۵۰۰ میلی‌ثانیه
- Activation بیشتر از ۶۰٪ ثبت‌نام‌ها
- Second Generation در هفت روز بیشتر از ۳۰٪
- نرخ بازخورد مثبت بیشتر از ۵۰٪
- تبدیل Free به Paid بیشتر از ۵٪
- Gross Margin بیشتر از ۵۰٪

North Star Metric در MVP: **تعداد Generation موفق به‌ازای هر کاربر فعال در هفته**.

## ریسک‌های کلیدی

- **محدودیت منطقه‌ای Providerهای AI:** اعتبارسنجی در Phase 0 و Fallback با Provider جایگزین یا مدل Open-weight.
- **هزینه بالای Video:** سهمیه پلن، Credit بالا، Cost Tracking و عدم ارائه Unlimited.
- **سوءاستفاده از پلن رایگان:** تأیید موبایل، Rate Limit و محدودیت IP/Device.
- **کیفیت نامناسب خروجی:** Auto Best، Feedback Loop و Model Router.
- **هزینه کنترل‌نشده:** بودجه روزانه و Circuit Breaker برای صف Generation.
- **محتوای نامناسب یا نقض حقوق:** Content Policy و Moderation پیش و پس از تولید.

## مستندات

| سند | توضیح |
|---|---|
| [`docs/MVP_Specification_FA.md`](docs/MVP_Specification_FA.md) | PRD، محدوده MVP، User Journey، API و معیار پذیرش |
| [`docs/Development_Roadmap_FA.md`](docs/Development_Roadmap_FA.md) | برنامه فنی، Sprintها، معماری و فناوری‌ها |
| [`docs/AI_Content_Studio_Business_Plan_FA.md`](docs/AI_Content_Studio_Business_Plan_FA.md) | بیزینس‌پلن، بازار، مدل درآمد و اقتصاد AI |
| [`docs/AI_Content_Studio_Review_PM_CTO_Sales_FA.md`](docs/AI_Content_Studio_Review_PM_CTO_Sales_FA.md) | بازبینی محصول، فنی، فروش و ریسک‌ها |

## مشارکت در توسعه

تا پیش از تثبیت اسکلت پروژه، هر تغییر باید با محدوده MVP و اولویت‌های Roadmap هم‌راستا باشد. برای تغییرات محصولی ابتدا PRD و برای تصمیم‌های فنی ابتدا Development Roadmap را بررسی کنید. اطلاعات حساس، کلید API و فایل `.env` نباید Commit شوند.

## مجوز

هنوز فایل مجوز برای پروژه تعریف نشده است. تا زمان افزودن `LICENSE`، تمام حقوق پروژه محفوظ است و استفاده، انتشار یا بازتوزیع آن نیازمند اجازه مالک مخزن است.

</div>
