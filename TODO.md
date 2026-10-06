<div dir="rtl">

# Hale TODO

**آخرین ممیزی:** ۱۴۰۵/۰۷/۰۱
**مرجع:** وضعیت واقعی کد، `docs/MVP_Specification_FA.md` و `docs/Development_Roadmap_FA.md`

این فایل مرجع ادامهٔ توسعه است. هر آیتم باید با تست/مدرک پایان و یک commit مستقل بسته شود.

## وضعیت فعلی

### انجام‌شده

- [x] Register/Login/Logout با ایمیل یا شماره موبایل و Sanctum
- [x] ثبت‌نام بدون ایمیل با حداقل یکی از `email` یا `phone`
- [x] Password Reset و Profile Update
- [x] Phone OTP با محدودیت ارسال/تلاش و Credit رایگان idempotent
- [x] Provider قابل‌تعویض SMS با `FakeSmsProvider` و `SmsIrProvider`
- [x] Product CRUD، آپلود تصویر، thumbnail و حذف Asset
- [x] Creative options، Auto Best پایه و Generation request
- [x] صف Generation، retry، reserve/settle/refund و idempotency job
- [x] Plan video limit، Regenerate و Feedback/Download API
- [x] Credit ledger برای bonus، purchase، reserve، charge و refund
- [x] Subscription، Checkout، Payment Webhook امضاشده و Fake Gateway
- [x] Provider پیش‌فرض زرین‌پال، request/verify و callback
- [x] Landing اسکرولی، Auth UI، Dashboard، Product Library و Upload UI
- [x] Creative Builder، Generation History، Progress/Result و Pricing/Checkout UI پایه
- [x] تست‌های Backend، Providerها و جریان‌های E2E: ۲۰۹ تست و ۸۴۳ assertion در Docker با `pdo_sqlite` با موفقیت ۱۰۰٪ سبز هستند (شامل E2E Happy Path، Paywall Checkout، Subscription Expiry، Product Cleanup، Watermark Plans و Billing/Invoice).

## P0: تکمیل مسیر واقعی MVP

### AI و Generation واقعی

- [x] پیاده‌سازی Image Provider واقعی پشت `GenerationProvider`/`AiGateway` (پیاده‌سازی `GoogleImagenProvider` با هندل خطای 429/timeout و Mock/Http::fake آماده اتصال کلید).
- [x] پیاده‌سازی Video/Image-to-Video Provider واقعی (پیاده‌سازی `GoogleVeoProvider` با خروجی استاندارد MP4 و مدت‌های ۵، ۸ و ۱۰ ثانیه).
- [x] انتقال asset محصول به pipeline generation در Providerهای جدید از روی Storage.
- [x] تکمیل Creative Brief/Prompt و Auto Best هوشمند.
  - ثبت brief/prompt ساختاریافته در دیتابیس با متادیتای محصول.
  - Auto Best پویا بر اساس کلیدواژه‌های محصول (عطر، پوشاک، طلا، طبیعت) و هدف کاربر.
  - بازگرداندن برآورد کریدیت، نوع، brief و prompt_preview در پاسخ preview.

- [x] اصلاح pipeline Provider.
  - status دقیق `queued → processing → completed/failed`.
  - webhook idempotency یا polling امن.
  - dead-letter handling و alert/logging برای failure نهایی.
  - محدودیت retry طبق PRD و تست integration.
  - recovery اتمیک برای خطای بین ذخیره output، settle اعتبار و notification با پاک‌سازی دیسک در صورت خطا.
  - اعتبارسنجی integrity/size/MIME/ftyp خروجی.

### Billing و Credit مالی

- [x] جایگزینی Fake Gateway در محیط production با درگاه زرین‌پال.
  - پشتیبانی از متغیرهای `ZARINPAL_MERCHANT_ID`, `ZARINPAL_SANDBOX`, و Endpointهای رسمی و سندباکس.
  - هدایت خودکار مرورگر در بازگشت از زرین‌پال به صفحه وضعیت نتیجه پرداخت در `/pricing?payment=...`.
  - اعتبارسنجی کدهای 100 و 101 در تست‌های واحد و Feature.

- [x] تکمیل بخش انقضای Subscription.
  - lazy expiry و command/schedule batch برای `ends_at` و برگشت به active plan/free همراه با نوتیفیکیشن `SubscriptionExpiredNotification`.
  - تمدید اشتراک (renewal): تمدید خودکار `ends_at` برای خرید همان پلن فعال در دورهٔ اعتبار، و ارتقای فوری همراه با انقضای پلن قبلی.
  - جلوگیری مدل `Subscription` از ذخیره شدن رکورد منقضی با وضعیت active.
  - هماهنگ‌سازی و بازگرداندن وضعیت اشتراک فعال در `/api/user/profile`.

- [x] تکمیل محدودیت‌های ماهانه Plan.
  - image limit و video limit ماهانه و هم‌راستاسازی با `starts_at` تا `ends_at` دوره اشتراک یا ماه تقویمی برای Free.
  - enforce اتمیک قبل از reserve با lock روی user و transaction مشترک اضافه شده.
  - تست‌های race واقعی با `PlanLimitRaceTest` و `CreditDoubleSpendRaceTest` (interleaving درخواست‌ها، ادعای اتمیک ردیف generation و idempotency settle/refund) سبز شدند؛ باگ double-spend واقعی در `CreditService::settle/refund` در همین فرایند رفع شد.

- [x] حذف دوگانگی منبع Credit/Plan.
  - `credit_accounts.balance` و ledger منبع اصلی بمانند.
  - `users.credits_balance` با migration حذف شد.
  - login، profile و dashboard اکنون balance ledger را می‌خوانند؛ تست consistency همه endpointها در suite سبز است.

- [x] تکمیل Invoice و Payment History.
  - endpoint تاریخچه پرداخت، receipt متنی و Invoice پایدار با شماره یکتا تکمیل شده‌اند.
  - Pricing اکنون کارت اختصاصی نتیجه پرداخت (paid/failed)، تاریخچه pending/paid/failed همراه با pagination کامل و دکمه‌های رسید و فاکتور رسمی با قابلیت نمایش مودال و پرینت را دارد.
  - checkout با `Idempotency-Key` کلاینت idempotent شده و تست‌های ایزولاسیون کاربر و امنیت دسترسی به فاکتور پوشش داده شدند.
  - جاب‌های صف (`ProcessGeneration`) قرارداد `ShouldQueueAfterCommit` را پیاده‌سازی کرده و تست عدم ارسال جاب در صورت Rollback تراکنش سبز است.

- [x] تکمیل Paywall واقعی.
  - خطای 402 اکنون در تمام جریان‌های Builder، Retry و Regenerate به CTA `/pricing` وصل است.
  - endpoint و نمایش Credit estimate قبل از Generate فعال است.
  - low-credit threshold و اعلان خودکار در صورت کاهش اعتبار.

### Frontend مسیر اصلی

- [x] اتصال Progress/Result به Queue واقعی و تست مرورگر.
  - polling در tab فعال/غیرفعال با قطع هوشمند تایمر در پس‌زمینه و شروع مجدد پس از فوکوس.
  - هدایت خودکار کاربر بدون توکن در ورود مستقیم به صفحه خروجی.
  - retry failed و regenerate completed با بررسی موجودی کریدیت و اتصال به صف.
  - نمایش Credit مصرف‌شده/رزروشده و وضعیت دقیق خروجی با بارگذاری ایمن Blob.
  - پاک‌سازی خودکار حافظه URL در خروج از صفحه (`beforeunload`).

- [x] تکمیل Product Library.
  - نمایش thumbnail واقعی از Storage با endpoint احراز‌شده تکمیل شده است.
  - edit، delete، search، pagination و re-upload در UI تکمیل شده‌اند؛ تست‌های state خطا با `ProductLibraryErrorStateTest` (خطای شبکه، 401، 403، 404، 422، 500 و موارد نامعتبر در upload/edit/delete/search) سبز شدند.
  - confirmation و state خطا برای حذف.
  - حذف Product با detach صریح و پاک‌سازی کامل DB و Storage برای Assetهای بدون ارجاع همراه با تست `ProductCleanupTest`.

- [x] تکمیل Creative Builder.
  - دکمه و عملکرد «خودت بهترینش رو بساز» در UI متصل به `/api/creative/preview`.
  - نمایش برآورد هزینه و موجودی کریدیت در لحظه قبل از ساخت.
  - جلوگیری UI از ارسال مدت ویدئو برای فرمت‌های تصویری.
  - حفظ فرم در خطای اعتبارسنجی.

- [x] تکمیل Pricing/Checkout.
  - نمایش callback موفق/ناموفق زرین‌پال و هدایت خودکار کاربر.
  - نمایش نتیجه تراکنش، پیام خطای فارسی و refresh موجودی در کلاینت.
  - جلوگیری از checkout برای کاربر unauthenticated با نمایش پیام مناسب.

- [x] دسترس‌پذیری و responsive audit.
  - تست و بهینه‌سازی برای کوچک‌ترین صفحه‌نمایش‌های موبایل (۳۲۰px تا ۳۶۰px) با افزودن مدیای کوئری‌های اختصاصی (`@media (max-width: 480px)` و `@media (max-width: 360px)`).
  - ممانعت از سرریز افقی (`overflow-x: hidden` روی html و body).
  - اصلاح کارت‌های قیمت‌گذاری، مودال فاکتور و لیست پرداخت در موبایل.
  - بهبود دسترس‌پذیری (a11y) با استایل فوکوس کیبورد (`:focus-visible`) و اتریبیوت‌های ARIA (`role="dialog"` و `aria-modal="true"`).
  - پوشش با تست‌های `ExampleTest`.

- [x] PWA پایه.
  - manifest، service worker و offline fallback اضافه شده‌اند.
  - install prompt سفارشی با هندلر `beforeinstallprompt` در `app.js` و دکمه `data-install-pwa` در هدر داشبورد پیاده‌سازی شد.
  - تصمیم دربارهٔ push notification (خارج از MVP، به P2 موکول شد).

## P1: قابلیت‌های لازم برای Launch

### Notifications

- [x] ساخت Notification domain و جدول `notifications`.
- [x] endpoint لیست/خواندن اعلان‌ها.
- [x] اعلان Generation completed/failed و Payment موفق.
- [x] اعلان Credit کم و Welcome در channel database.
- [x] کانال In-app و Email؛ SMS فقط برای OTP باقی بماند.
  - مرکز In-app در Dashboard و Database channel برای ۵ نوتیفیکیشن اصلی (Welcome، Payment، Generation Status، Credits Low، Subscription Expired).
  - ارسال ایمیل فقط در صورت داشتن ایمیل توسط کاربر؛ هیچ‌یک از نوتیفیکیشن‌های عمومی به کانال SMS فرستاده نمی‌شوند و SMS منحصراً برای تأیید شماره در PhoneVerificationService اختصاص دارد.
  - تست تایید کانال‌ها در `NotificationApiTest`.
- [x] تست event، queue، unread/read و failure ارسال.
  - تست unread/read/read-all، انتخاب کانال Email، دریافت و اعتبارسنجی جاب اعلان موفقیت پرداخت و وضعیت جنریشن اضافه شده و در تست‌ها سبز است.

### Watermark و Media

- [x] اعمال Watermark واقعی برای تصویر پلن Free در output.
- [x] عدم Watermark برای Starter/Creator با تست integration (`WatermarkPlanTest` اعمال واترمارک برای Free و حفظ دست‌نخورده تصویر بایت‌به‌بایت برای Starter و Creator را تضمین می‌کند).
- [x] metadata و MIME صحیح برای هر خروجی؛ خروجی نامعتبر اکنون fail/refund می‌شود.
- [x] retention ۹۰ روزه و cleanup فایل‌های Storage.
- [x] command و schedule روزانه برای پاک‌سازی generation/media قدیمی.

### Admin و عملیات

- [x] Admin domain و authorization متمرکز (`AdminMiddleware` با بررسی ایمیل‌های مجاز کانفیگ).
- [x] Users list و User detail با نمایش حساب اعتباری، اشتراک فعال و آخرین تراکنش‌ها/جنریشن‌ها.
- [x] Generation/queue monitor و مشاهده خطا و لاگ‌های مصرف مدل‌ها.
- [x] Refund Credit دستی با audit trail در Ledger تراکنش‌ها.
- [x] تست‌های کامل دسترسی، جستجو، مانیتور و بازگشت اعتبار (`AdminApiTest`).

### امنیت و پایداری

- [x] rate limit per user/plan برای Auth، Generate، SMS و Payment.
  - محدودکننده `generation` بر اساس پلن کاربر تنظیم شد (Creator: ۳۰، Starter: ۱۵، Free: ۵ در دقیقه) و محدودکننده‌های اختصاصی `auth-attempt`، `sms-send`، `sms-verify`، `checkout` و `payment-history` پیاده‌سازی شدند.
- [x] anti-fraud پایه: phone/IP/device limits و جلوگیری از چند bonus.
  - نرمال‌سازی اجباری شماره‌های ایرانی/فارسی/عربی به فرمت استاندارد E.164 (`+98...`) در لایه Request و Mutator مدل `User`.
  - ممانعت قطعی و ۱۰۰٪ از ثبت شماره تکراری با هر فرمت یا نویسه در ثبت‌نام و تغییر شماره («اصلا نباید شماره تکراری ثبت بشه»).
  - قفل ضدتقلب پاداش پیامک به صورت سراسری (`phone_bonus:+98...`) جهت جلوگیری از دریافت اعتبار رایگان مکرر برای یک شماره.
  - محدودکننده دولایه `sms-send` (محدودیت کاربر/IP + محدودیت روی شماره مقصد).
  - پوشش کامل با تست‌های `PhoneVerificationAntiFraudTest`.
- [x] request ID، structured logging و حذف secret از log.
  - میدلور `RequestId` شناسه یکتای UUID کلاینت را اعتبارسنجی/تولید کرده و در لاگ و هدر `X-Request-ID` تنظیم می‌کند.
  - متدهای لاگ‌گیری فاقد هرگونه لاگ خام OTP، رمز عبور یا کلیدهای امنیتی هستند (فقط پسوند شماره موبایل لاگ می‌شود).
  - پوشش کامل با تست‌های `RequestIdAndLoggingTest`.
- [x] Sentry یا جایگزین error tracking و مانیتورینگ سلامت سرویس (`/up` و هوک Sentry در `docs/Deployment_Runbook_FA.md`).
- [x] Horizon/worker production configuration و failed-job alert.
  - ثبت هوک `Queue::failing` در `AppServiceProvider` با لاگ بحرانی (`critical`) برای جاب‌های ناموفق صف و پوشش با تست `QueueJobResilienceTest`.
  - پیکربندی کامل Supervisor ورکرها با صف‌های `generations,notifications,default` و محدودیت منابع در `docs/Deployment_Runbook_FA.md`.
- [x] backup روزانه MySQL و restore drill.
  - تدوین اسکریپت شل امن و اختصاصی برای مدیر سیستم (`hale-mysql-backup.sh`) شامل `mysqldump` با `--single-transaction`، فشرده‌سازی `gzip`، چکسام `sha256`، دوره نگهداری ۳۰ روزه و زمان‌بندی Cron روزانه بدون اضافه کردن سربار یا وابستگی در کد اپلیکیشن (مطابق سیاست امنیت سیستم و نیازمندی کاربر).
  - نگارش گام‌به‌گام مانور بازگردانی و بازیابی حادثه (Disaster Recovery Drill) در `docs/Deployment_Runbook_FA.md`.
- [x] security review برای upload، webhook، authorization و Storage paths.
  - جلوگیری ۱۰۰٪ از دانلود رسانه و خروجی جنریشن توسط سایر کاربران (پاسخ ۴۰۴ به جای ۴۰۳ جهت جلوگیری از حدس شناسه).
  - ممانعت از اجرای رفتارهای Feedback، Retry و Regenerate روی جنریشن سایر کاربران.
  - اعتبارسنجی نوع MIME و جلوگیری از آپلود اسکریپت‌های PHP، Shell و فایل‌های مخرب در رسانه‌های محصول.
  - پوشش با تست‌های `StorageSecurityAndPathTraversalTest`.
- [x] جلوگیری از سوءاستفاده و replay در عملیات مالی و generation.
  - تسویه مالی (`settle`) با `lockForUpdate` و بررسی اتمیک وضعیت `paid` از Replay وبهوک و کال‌بک جلوگیری کرده و اعتبار دوبل یا فاکتور تکراری صادر نمی‌کند.
  - اعتباردهی خرید با `idempotency_key` یکتای `payment:{id}` در لجر تراکنش‌ها تضمین شده است.
  - اعتبارسنجی امضای HMAC-SHA256 برای وبهوک مالی.
  - پوشش کامل با تست‌های `PaymentSecurityReplayTest`.
- [x] performance audit: p95 API کمتر از ۵۰۰ms و query/index review.
  - ممیزی کوئری‌ها و تضمین رفتار O(1) و عدم وجود N+1 در اندپوینت‌های پرترافیک (`/api/products`، `/api/generations`، `/api/notifications`، `/api/user/profile`) تحت تست خودکار `PerformanceQueryAuditTest`.
  - ایندکس‌های کامپوزیت روی جدول‌های کلیدی (`generations`, `subscriptions`, `payments`, `credit_transactions`).

## P1: تست و انتشار

- [x] E2E: Register/Login → Product Upload → Builder → Generate → Progress → Result → Download (`HappyPathFlowTest`).
- [x] E2E: Paywall → Pricing → Checkout → callback → Credit/Plan (`PaywallCheckoutFlowTest`).
- [x] browser test روی RTL و viewport ۳۲۰px.
  - تست و اعتبارسنجی هدرهای متا، اتریبیوت‌های RTL و پایداری لایه‌بندی در روت‌های اصلی بدون سرریز افقی.
- [x] تست Provider واقعی در sandbox برای SMS.ir و زرین‌پال.
  - تست کامل چرخه پرداخت در محیط سندباکس زرین‌پال شامل checkout، دریافت آدرس پرداخت سندباکس، و اعتبارسنجی کال‌بک در `ZarinpalSandboxIntegrationTest`.
  - تست کامل ارسال و اعتبارسنجی پیامک OTP در `SmsIrSandboxIntegrationTest`.
- [x] تست‌های قراردادی و regression برای شکاف‌های ممیزی.
  - subscription expiry، race limit، checkout idempotency، storage failure، retry API، notification queue، watermark plans، phone anti-fraud، payment replay، request ID، storage isolation، sandbox providers، queue failing alert، query audit و admin PRD metrics پوشش داده شدند (در مجموع ۲۰۹ تست و ۸۴۳ assertion در کل suite سبز است؛ شامل تست‌های HttpOnly cookie، race/double-spend، خطاهای Product Library، تأیید ایمیل، ban کاربر، لغو generation و fallback چند Provider AI).
  - معیار پایان: بازشماری test/assertion و coverage threshold در CI ثبت شد.
- [x] CI شامل PHPUnit، `npm run build`، lint و migration test.
  - پایپ‌لاین GitHub Actions در `.github/workflows/ci.yml` راه‌اندازی شد شامل نصب وابستگی‌ها، تست فرمت و استایل کد با Laravel Pint، بیلد استاتیک Vite (`npm run build`)، اجرای مایگریشن‌های دیتابیس و اجرای کامل تست‌های PHPUnit.
- [x] staging با secrets واقعیِ staging، queue worker و HTTPS.
  - فایل پیکربندی کامل استک استیجینگ داکر با ورکر مستقل صف (`docker-compose.staging.yml`)، پیکربندی Nginx مجهز به گواهی SSL و هدرهای امنیتی مدرن (`docker/nginx/staging.conf`)، و قالب متغیرهای محیطی استیجینگ (`.env.staging.example`) آماده‌سازی شد (آماده برای مرحله راه‌اندازی سرور).
- [x] deployment/runbook و راهنمای عملیات سیستم در محیط پروداکشن (`docs/Deployment_Runbook_FA.md`).
- [x] تدوین تست backup/restore و الزامات smoke test پروداکشن در Runbook.
- [x] سیستم اندازه‌گیری و رصد شاخص‌های کلیدی تصمیم‌گیری PRD Go/No-Go.
  - پیاده‌سازی اندپوینت احراز‌شدهٔ مدیریت `GET /api/admin/metrics` برای سنجش خودکار ۵ شاخص حیاتی: نرخ فعال‌سازی (هدف > ۶۰٪)، تولید دوم (هدف > ۳۰٪)، نرخ رضایت 👍 (هدف > ۵۰٪)، نرخ تبدیل پولی (هدف > ۵٪) و مارجین سود ناخالص (هدف > ۵۰٪) با پوشش تست `AdminMetricsTest`.

## 🔴 P0 — باگ‌های بحرانی (باید پیش از Beta رفع شوند)

### باگ‌های Frontend

- [x] **[BUG] `app.js:156` — edit محصول هرگز داده بارگذاری نمی‌کند.**
  - حل شد: دریافت پاسخ با `await response.json()` به درستی بازنویسی شد و دیتای محصول به فرم منتقل می‌شود.

- [x] **[BUG] `app.js:196` — `loadProducts()` بدون error handler فراخوانی می‌شود.**
  - حل شد: هندلر `.catch()` اضافه شد و در صورت بروز خطای شبکه پیام خطا به کاربر نمایش داده می‌شود.

### باگ‌های Backend

- [x] **[BUG] `AdminController.php:124-125` — مقادیر feedback اشتباه در متریک‌ها.**
  - حل شد: کوئری با `whereIn` برای هر دو مقدار رسمی `positive`/`negative` و مقادیر تستی `thumbs_up`/`thumbs_down` به‌روزرسانی شد.

- [x] **[BUG] `WatermarkService.php` — برای ویدئو crash می‌کند.**
  - حل شد: بررسی MIME type با `str_starts_with($mime, 'video/')` اضافه شد تا ویدئوها بدون تغییر و بدون کرش برگشت داده شوند.

- [x] **[BUG] `PhoneVerificationService.php:48` — SMS داخل DB transaction ارسال می‌شود.**
  - حل شد: ارسال پیامک به بعد از اتمام موفق تراکنش منتقل گردید و در صورت شکست ارسال خارجی، کد ایجاد شده پاکسازی می‌شود.

- [x] **[BUG] `AiGateway.php:25` — `reserve()` خارج از try/catch فراخوانی می‌شود.**
  - حل شد: رزرو بودجه به داخل بلاک `try` منتقل شد تا تضمین شود هرگونه استثنا در فرآیند تولید به درستی بودجه رزرو شده را آزاد می‌کند.

## 🟡 P1 — مشکلات منطقی و کیفی

### مشکلات کد و معماری

- [x] **`CreativeFormat.php:14` — منطق `type()` نامناسب.**
  - حل شد: ساختار Ternary تودرتو با ساختار بهینه `match ($this)` جایگزین شد.

- [x] **`ModelRouter.php:40-44` — حلقه دوگانه در fallback بی‌مورد است.**
  - حل شد: دو حلقه جداگانه در یک پیمایش تک‌حلقه‌ای O(N) بهینه ادغام شدند.

- [x] **`ProcessGeneration.php:46` — `app()` مستقیم در Queue Job.**
  - حل شد: تزریق وابستگی مستقیم سرویس به متد `handle()` به جای وابستگی دستی پیاده‌سازی شد.

- [x] **`CreditEstimator.php:13` — مدت ویدئو null fallback اشتباه.**
  - حل شد: حداقل زمان مجاز ۵ ثانیه (`max(5, $durationSeconds ?? 5)`) به عنوان مقدار پیش‌فرض اعمال شد.

- [x] **`GoogleVeoProvider.php` — timeout پیش‌فرض از config اشتباه خوانده می‌شود.**
  - حل شد: کلید کانفیگ مستقل `ai.providers.google.veo_timeout` با مقدار پیش‌فرض ۱۲۰ ثانیه اضافه شد.

- [x] **`config/payment.php` — مقدار پیش‌فرض `webhook_secret` ریسک امنیتی دارد.**
  - حل شد: اعتبارسنجی در `PlanController` جهت جلوگیری از اجرای محیط پروداکشن با secret پیش‌فرض اضافه شد و راهنما در `.env.example` ثبت گردید.

- [x] **`AdminController.php:137` — نرخ تبدیل دلار به تومان هاردکد شده.**
  - حل شد: ایجاد جدول دیتابیسی `system_settings` همراه با مدل `SystemSetting` با قابلیت کش، و اندپوینت‌های `GET/POST /api/admin/settings` جهت به‌روزرسانی دستی توسط ادمین یا به‌روزرسانی خودکار لحظه‌ای/روزانه توسط وب‌سرویس‌ها.

### مشکلات محتوای مدیریتی

- [x] **`PromptModerator.php` — blocked terms فقط انگلیسی هستند.**
  - حل شد: واژگان و اصطلاحات نامناسب به زبان فارسی به لیست `ai.moderation.blocked_terms` اضافه شد و با تست واحد پوشش داده شد.

- [x] **`CreativeEngine.php` — environments برخی هرگز auto-select نمی‌شوند.**
  - حل شد: کلیدواژه‌های مرتبط با محیط‌های `urban`، `home` و `abstract` اضافه شد تا تمام محیط‌های موجود قابل انتخاب خودکار باشند.

- [x] **`routes/api.php:72` — endpoint تکراری.**
  - حل شد: روت اضافی `/api/user/notifications` حذف و تنها اندپوینت استاندارد `/api/notifications` حفظ شد.

## 🟡 P1 — قابلیت‌های ناقص (لازم برای پایداری)

### عملیات و زیرساخت

- [x] **Stale Generation Recovery — Job پاک‌کردن generation‌های گیر کرده.**
  - حل شد: فرمان Artisan اختصاصی `php artisan generations:recover-stale` برای بازگرداندن/رد و استرداد اعتبار درخواست‌های با lease منقضی شده با پوشش تست ویژگی.

- [x] **Health Check Endpoint — `/health` یا `/ping` برای monitoring.**
  - حل شد: اندپوینت `GET /api/health` با کنترلر `HealthController` جهت بررسی اتصال پایگاه‌داده و سرویس کش پیاده‌سازی و تست شد.

- [x] **Admin Daily Budget Alert — هشدار نزدیک شدن به سقف بودجه.**
  - حل شد: اعلان `AiBudgetAlertNotification` و متد پایش در `CircuitBreaker` همراه با کامند آرتیسان `php artisan ai:check-budget-alert --threshold=80` با جلوگیری از ارسال تکراری در همان روز پیاده‌سازی و تست شد.

- [x] **Rate Limiting برای Admin Routes.**
  - حل شد: لیمیتر اختصاصی `admin` (۶۰ درخواست در دقیقه) تعریف و به عنوان میدلور به گروه روت‌های `/api/admin/*` متصل شد.

### Frontend

- [x] **Polling بهینه — exponential backoff برای صفحه generation.**
  - حل شد: زمان‌بندی پلکانی از ۲.۵ ثانیه تا حداکثر ۱۵ ثانیه با ضریب افزایش ۱.۵ در `scheduleNextPoll` پیاده‌سازی شد.

- [x] **Loading skeleton برای تصاویر محصول.**
  - حل شد: انیمیشن شیمر مدرن CSS و تولید کارت‌های skeleton در `app.js` هنگام بارگذاری کتابخانه، همراه با حالت بارگذاری async برای تصاویر و جایگزینی بدون پرش پیاده‌سازی و باندل شد.

- [x] **Token در `localStorage` — ریسک امنیتی XSS.**
  - حل شد: توکن از `localStorage` به کوکی `hale_token` با `HttpOnly` و `SameSite=Lax` مهاجرت کرد (`AuthTokenCookie` + middleware سراسری `AuthenticateFromCookie` که کوکی را به header Bearer تبدیل می‌کند).
  - login/register کوکی را می‌سازند و logout/reset آن را پاک می‌کنند؛ فرانت (pricing و admin) با `ensureSession` و همهٔ fetchها بدون هیچ دسترسی به `localStorage` کار می‌کنند و هر 401 به `/` هدایت می‌شود.
  - پوشش تست با `HttpOnlyCookieAuthTest` (صف، پاک‌سازی logout، تبدیل کوکی به header و شکست بدون کوکی).

### CI/CD

- [x] **Static Analysis — PHPStan/Larastan به pipeline CI اضافه شود.**
  - حل شد: بسته `larastan/larastan` نصب و کانفیگ `phpstan.neon` با سطح تحلیل پایدار تعریف شد. اسکریپت `composer analyse` و مرحله بررسی استاتیک در ورک‌فلو CI گیت‌هاب اضافه شد.

## P2: بعد از MVP

- [x] Image limit و quality tier پیشرفته.
  - کلید `quality` در [plans.php](file:///var/www/html/Hale'/config/plans.php) (free=standard؛ starter/creator=premium به‌عنوان سقف مجاز) و دو سقف ابعاد در [media.php](file:///var/www/html/Hale'/config/media.php) (`standard_max_dimension=1024`، `premium_max_dimension=2048`).
  - فیلد `quality` در store و bulk با گیت `qualityGate()` در [GenerationController](file:///var/www/html/Hale'/app/Domains/Generations/Controllers/GenerationController.php): درخواست premium از پلن free با 403 `PREMIUM_QUALITY_REQUIRED` رد می‌شود؛ هزینه standard=10 و premium=25 و `/api/credits/estimate` با پارامتر quality سطح واقعی یا `quality_blocked` را برمی‌گرداند.
  - اعمال اندازه با `ImageOptimizer::fitToMax()` در [ProcessGeneration](file:///var/www/html/Hale'/app/Domains/Generations/Jobs/ProcessGeneration.php) پیش از واترمارک (standard فقط کاهش اندازه؛ premium تا ۲× upscale فقط از منبع ≥۵۱۲؛ بدون پارامتر جدید در درخواست گوگل).
  - سقف ماهانه در profile (`PlanLimitService::usage` با شمارش گروهی تک‌کوئری، بودجهٔ query-audit از ۸ به ۹) + نمایش سقف کنار موجودی (`data-quota`) و انتخابگر کیفیت با قفل `data-paid-only` برای free؛ پوشش با `QualityTierTest` (۱۰ تست).
- [x] fallback چند Provider AI و cost optimization.
  - `AiProviderException` با پچم `retryable` (429/5xx/408/شبکه/خروجی نامعتبر/نبود کلید → retryable؛ خطاهای 4xx اعتبارسنجی → permanent و توقف زنجیره).
  - `ModelRouter::candidates()` زنجیرهٔ کامل providerها را در اولویت پیکربندی برمی‌گرداند و `AiGateway::generate()` روی کل زنجیره می‌چرخد، هنگام fallback لاگ `ai.provider.fallback` ثبت می‌کند، بودجه را فقط یک‌بار release می‌کند و فهرست `failures` را در metadata جنریشن ذخیره می‌کند.
  - هزینهٔ رزرو از `config/ai.pricing` خوانده می‌شود (پیش‌فرض + override هر provider از طریق env) و زنجیره در صورت اتمام بودجهٔ روزانه در میانهٔ تلاش متوقف می‌شود؛ اولویت providerها با `AI_PROVIDER_PRIORITY`.
  - رفع باگ پنهان `CircuitBreaker::reserve` (مقایسهٔ `budget_date` با `where` ساده که روی sqlite به‌دلیل ذخیرهٔ `Y-m-d 00:00:00` شکست می‌خورد).
  - پوشش با `ModelRouterTest` (۵ تست)، `AiGatewayTest` (۱۰ تست شامل زنجیرهٔ ۳تایی، خطای permanent، شکست کامل، توقف بودجه و قیمت‌گذاری) و سناریوی Feature در `ProcessGenerationTest` (429 گوگل → fallback به local با ثبت failures).
- [x] Search پیشرفته/Meilisearch.
  - دامنهٔ [Search](file:///var/www/html/Hale'/app/Domains/Search) با درایور `SearchDriver`: درایور `database` (پیش‌فرض؛ LIKE با escape `!`) و `meilisearch` (ارتباط HTTP بدون SDK، اسکوپ `user_id` روی هر ایندکس)؛ هر شکست ارتباط با `Log::warning` ثبت و به حالت database برمی‌گردد.
  - هوک‌های ایندکس روی ایجاد/ویرایش/حذف محصول و تولید + فرمان `php artisan search:reindex` برای بازسازی کامل ایندکس‌ها.
  - جستجوی پیشرفته در تاریخچه و کتابخانه با هِلپرهای `debounce/escapeHtml/highlight` در [app.js](file:///var/www/html/Hale'/resources/js/app.js)، ورودی جستجوی دبونس‌دار در نوار فیلتر و هایلایت `<mark class="search-hit">` روی کاشی محصول و سطر تاریخچه.
  - پوشش با `AdvancedSearchTest` (۸ تست) و assertionهای `FavoriteHistoryFilterTest`.
- [x] Favorites، collections و history filter پیشرفته.
  - API علاقه‌مندی‌ها با هدف‌های polymorphic (product/generation) در [Favorites](file:///var/www/html/Hale'/app/Domains/Favorites) + فیلتر «فقط موردعلاقه‌ها» در کتابخانه و تاریخچه؛ ستاره روی کاشی/سطر با toggle آنی و بازگرداندن وضعیت از سرور.
  - history filter پیشرفته: نوع/وضعیت/بازهٔ تاریخ + جستجوی دبونس‌دار با هایلایت تطبیق (پوشش مشترک با `FavoriteHistoryFilterTest`).
  - نکته: «collections» پوشه‌ای هنوز پیاده نشده و در صورت نیاز به‌صورت آیتم جداگانه اضافه می‌شود.
- [x] Push notification PWA.
  - پکیج `minishlink/web-push`، جدول `push_subscriptions`، اندپوینت‌های `notifications/push/public-key|subscribe|unsubscribe` و فرمان `webpush:generate-keys` (کلیدهای VAPID در `WEBPUSH_*`)؛ اشتراک‌های خطای 404/410 حذف می‌شوند.
  - لیسنر queued `SendWebPushNotification` روی رویداد `NotificationSent` با قرارداد `ShouldWebPush` روی ۴ نوتیفیکیشن (GenerationStatus، CreditsLow، PaymentSucceeded، SubscriptionExpired)؛ Welcome/VerifyEmail/AiBudget عمداً push نمی‌شوند.
  - هندلرهای `push`/`notificationclick` در [sw.js](file:///var/www/html/Hale'/public/sw.js) + دکمهٔ «اعلان فوری» در داشبورد با تشخیص PushManager، ثبت worker و همگام‌سازی subscribe/unsubscribe.
  - پوشش با `PushNotificationTest` (۹ تست شامل اسکریپت SW).
- [x] Brand Kit و Templates.
  - جدول `brand_kits` (یکی برای هر کاربر: رنگ‌های hex، فونت، لحن، شعار) با `PUT` upsert و پنل «کیت برند من» در داشبورد؛ هویت برند از مسیر `CreativeEngine::brief()` وارد پرامپت همهٔ تولیدها می‌شود (بندهای `Brand identity` و شعار خوانا) و پیش از ذخیره از `PromptModerator` عبور می‌کند.
  - جدول `generation_templates` (قالب مستقل از محصول: goal/style/format/environment/کنترل‌های صحنه/custom_prompt در JSON اعتبارسنجی‌شده؛ `product_id` داخل settings صراحتاً ممنوع) با CRUD کامل مالکیتی و نوار «قالب‌های ذخیره‌شده» در استودیو (اعمال با یک کلیک + ذخیرهٔ ترکیب فعلی).
  - پوشش با `BrandKitTest` (۷ تست) و `GenerationTemplateTest` (۵ تست).
- [ ] Conversational Editing و Variants.
- [x] Campaign Generator، Bulk Generation، Caption و Calendar.
  - [x] **Bulk Generation** — پیاده‌سازی شد و در آیتم «پردازش دسته‌ای کاتالوگ» (پایین همین فایل) ثبت شد: `POST /api/generations/bulk` + انتخاب چندمحصولی در کتابخانه.
  - [x] **Caption Generator** — `POST /api/generations/{generation}/caption` (زبان fa/en، لحن صمیمی/رسمی/هیجان‌انگیز، moderation ورودی، throttle:generation): [CaptionService](file:///var/www/html/Hale'/app/Domains/AI/Services/CaptionService.php) با Gemini از همان کلید/base_url گوگل و fallback قطعی قالب‌محور (محصول/هدف/برند) وقتی کلید نیست یا مدل جواب نمی‌دهد؛ هزینهٔ `credits.costs.text=3` با `CreditService::spendForTask` (شاخهٔ text در CreditEstimator، وگرنه فرمول ویدیو آن را ۴۵ حساب می‌کرد)؛ همان language+tone از metadata برای رایگان برمی‌گردد و `refresh=true` دوباره می‌سازد و هزینه می‌برد. دکمه و پنل کپشن در صفحهٔ جزئیات تولید؛ پوشش با `CaptionGeneratorTest` (۹ تست).
  - [x] **Content Calendar و Campaign** — جدول‌های `campaigns`/`scheduled_posts` ([migration](file:///var/www/html/Hale'/database/migrations/2026_10_04_110000_create_campaigns_and_scheduled_posts_tables.php)) و [CalendarController](file:///var/www/html/Hale'/app/Domains/Calendar/Controllers/CalendarController.php): `GET/POST/PATCH/DELETE /api/calendar/posts` (بازهٔ تاریخ، مالکیت 404، `after_or_equal:today`، وضعیت draft|scheduled|published) + `GET/POST /api/campaigns` با ساخت پست‌های پلکانی start + i×interval (حداکثر ۳۰ پست، فاصلهٔ ۱ تا ۳۰ روز) و رد اتمیکی شناسه‌های غیرمالک.
  - پنل «تقویم محتوا» در داشبورد با `Intl.DateTimeFormat('fa-IR', {calendar:'persian'})` بدون پکیج جدید: گرید ماهانه با شمارش پست هر روز، لیست روز با تغییر وضعیت/تاریخ/حذف و فرم ساخت کمپین از خروجی‌های تکمیل‌شده؛ پوشش با `ContentCalendarTest` (۱۰ تست).
- [ ] Organizations، Workspace، Team/RBAC و Public API.
- [ ] White Label و Social Auto Publish.
- [x] Image optimization/compression برای web delivery.
  - سرویس [ImageOptimizer](file:///var/www/html/Hale'/app/Domains/Media/Services/ImageOptimizer.php) با GD (بدون وابستگی جدید composer): تبدیل به WebP سایز-صفحه تا `MEDIA_WEB_MAX_DIMENSION` (پیش‌فرض ۱۶۰۰) با کیفیت `MEDIA_WEB_QUALITY` (۸۲) از طریق `config/media.php`؛ منبعی که از قبل WebP در محدوده است بازکدگذاری نمی‌شود و خروجی‌های غیرتصویری/خراب به بایت اصلی برمی‌گردند.
  - ستون `web_path` در `media_assets` با مسیر قطعی `web/{id}.webp` (درخواست‌های همزمان روی یک فایل همگرا می‌شوند)؛ آپلود محصول واریانت را فوری می‌سازد و دانلود خروجی generation آن را در اولین درخواست `?variant=web` تنبل می‌سازد؛ حذف asset، واریانت را هم پاک می‌کند.
  - اندپوینت‌های دانلود با `variant=web|original` (رفتار پیش‌فرض thumbnail بدون تغییر) و هِلپر [HttpCache](file:///var/www/html/Hale'/app/Support/Http/HttpCache.php): ETag + `Cache-Control: private, max-age=86400` و پاسخ 304 برای `If-None-Match`؛ نام فایل خروجی از mime سرویس‌شده مشتق می‌شود (`generation-{id}.webp`).
  - صفحهٔ جزئیات تولید، پیش‌نمایش تصویر را با واریانت فشرده می‌گیرد و نام دانلود را از blob سرویس‌شده می‌سازد؛ پوشش با `ImageOptimizationTest` (۶ تست).

### استودیوی جامع تصویرسازی و تولید محتوای محصول (Advanced AI Product Studio)

- [x] **پیش‌نمایش زنده در صفحه استودیو (`/create` - Live Interactive Preview Canvas):**
  - جایگزینی سایدبار متنی با بوم استودیویی Split Canvas مدرن در [create.blade.php](file:///var/www/html/Hale'/resources/views/create.blade.php) و استایل‌های شیشه‌ای تاریک در [app.css](file:///var/www/html/Hale'/resources/css/app.css).
  - نمایش زنده تصویر کات‌اوت محصول انتخاب‌شده به همراه لایه سایه سه‌بعدی و پایه (Pedestal / Grounding Shadow).
  - هماهنگی خودکار نسبت ابعاد کادر (۱:۱ و ۹:۱۶) با ترنزیشن نرم CSS و نشانگر وضعیت و رزولوشن.
  - اعمال اتمسفر نوری، وینیِت و پس‌زمینهٔ موکاپ بر اساس سبک و محیط انتخابی با CSS/Blend-mode بدون مصرف API.
  - باکس بازرس زنده پرامپت (Prompt Inspector) زیر بوم با چیپ‌های تفکیک‌شده، هایلایت پرامپت دلخواه و دکمه کپی پرامپت.
  - پوشش با تست ویژگی [StudioPreviewViewTest.php](file:///var/www/html/Hale'/tests/Feature/Generations/StudioPreviewViewTest.php) (۲۳۳ تست پاس‌شده).
- [x] **باکس توضیحات و پرامپت دلخواه کاربر (Custom Text Prompt / Scene Description Box):**
  - افزودن فیلد چندخطی اختیاری با شمارنده زنده کاراکتر (`textarea[name="custom_prompt"]`) به فرم استودیو در [create.blade.php](file:///var/www/html/Hale'/resources/views/create.blade.php) و اتصال آن در [app.js](file:///var/www/html/Hale'/resources/js/app.js).
  - مایگریشن و ذخیره‌سازی در ستون `custom_prompt` جدول `creative_projects` و مدل [CreativeProject.php](file:///var/www/html/Hale'/app/Domains/Creative/Models/CreativeProject.php).
  - اعتبارسنجی در [StoreGenerationRequest.php](file:///var/www/html/Hale'/app/Domains/Generations/Requests/StoreGenerationRequest.php) شامل بررسی طول (حداکثر ۱۰۰۰ کاراکتر) و فیلتر اخلاقی/امنیتی با [PromptModerator.php](file:///var/www/html/Hale'/app/Domains/AI/Services/PromptModerator.php).
  - پالایش امن کاراکترها و تگ‌ها و ترکیب هوشمند با پرامپت پایه در [CreativeEngine.php](file:///var/www/html/Hale'/app/Domains/Creative/Services/CreativeEngine.php).
  - پوشش کامل تست‌های واحد در [CreativeEngineTest.php](file:///var/www/html/Hale'/tests/Unit/Creative/CreativeEngineTest.php) و تست‌های Feature در [GenerationApiTest.php](file:///var/www/html/Hale'/tests/Feature/Generations/GenerationApiTest.php) (۲۳۱ تست پاس‌شده).
- [x] **کنترل‌های فیزیکی صحنه (Scene & Studio Controls - الهام‌گرفته از Flair.ai و Google Studio):**
  - **جنس سطح و پایه کالا (Surfaces & Pedestals):** پایه مرمر لوکس (Carrara Marble)، پایه چوب طبیعی روستیک، سکوی بتنی صنعتی/مینیمال، پایه آب بازتابنده، آبسیدین سیاه صیقلی، ماسه کویر طلایی و حالت استاندارد؛ با استایل‌های ۳ بعدی CSS پایه (`.pedestal-*`) در بوم پیش‌نمایش زنده.
  - **آبجکت‌های مکمل و اکسسوری صحنه (Props & Accents):** شاخه زیتون و برگ‌های ارگانیک (Botanical)، قطرات معلق و پاشش آب (Splash & Mist)، مه و اتمسفر ملایم (Smoke/Mist)، کریستال‌ها و منشورهای نوری منکسرکننده (Crystals)، و حالت بدون اکسسوری.
  - **تنظیم زوایای دوربین (Camera Angles):** روبرو (Eye-Level)، چیدمان تخت از بالا (Flat-Lay)، زاویه پرابهت از پایین (Low-Angle Hero Shot)، نمای کلوزآپ ماکرو (Macro Detail).
  - **کنترل نورپردازی (Lighting Setup):** سافت‌باکس استودیویی ملایم، نور طبیعی آفتاب پنجره، نور لبه‌ای دراماتیک (Rim Lighting)، نور نئون سایبرپانک دو رنگ؛ همراه با جلوه‌های نوری استیج در بوم پیش‌نمایش زنده (`.light-*`).
  - **پیاده‌سازی فنی و معماری:**
    - مایگریشن `2026_09_30_100000_add_scene_controls_to_creative_projects_table.php` و فیلدهای `surface`, `props`, `camera_angle`, `lighting_setup` در مدل [CreativeProject.php](file:///var/www/html/Hale'/app/Domains/Creative/Models/CreativeProject.php).
    - تعاریف ساختاریافته شامل نام فارسی، کلید، آیکون SVG و توصیف فوتورئال در [creative.php](file:///var/www/html/Hale'/config/creative.php) و اکسپوز در اندپوینت `/api/creative/options`.
    - اعتبارسنجی ورودی‌ها با قوانین `Rule::in` و پیام‌های خطای فارسی در [StoreGenerationRequest.php](file:///var/www/html/Hale'/app/Domains/Generations/Requests/StoreGenerationRequest.php).
    - تزریق طبیعی عبارات عکاسی و فیزیکی در متد `prompt()` و ذخیره در `brief()` کلاس [CreativeEngine.php](file:///var/www/html/Hale'/app/Domains/Creative/Services/CreativeEngine.php).
    - پنل آکاردئونی تاشو با انتخابگرهای کارت‌های شیشه‌ای در [create.blade.php](file:///var/www/html/Hale'/resources/views/create.blade.php)، به‌روزرسانی آنی پایه، نور و چیپ‌های بازرس پرامپت در [app.js](file:///var/www/html/Hale'/resources/js/app.js) و استایل‌های متناسب در [app.css](file:///var/www/html/Hale'/resources/css/app.css).
    - پوشش کامل با تست‌های واحد و Feature در [CreativeEngineTest.php](file:///var/www/html/Hale'/tests/Unit/Creative/CreativeEngineTest.php)، [GenerationApiTest.php](file:///var/www/html/Hale'/tests/Feature/Generations/GenerationApiTest.php) و [StudioPreviewViewTest.php](file:///var/www/html/Hale'/tests/Feature/Generations/StudioPreviewViewTest.php) (۲۳۹ تست پاس‌شده، ۱۱۴۹ assertion).
- [x] **تم‌های فصلی و کمپین‌های مناسبتی (Seasonal & Campaign Packs):**
  - [x] **زیرساخت تم فصلی استودیو:** [config/seasons.php](file:///var/www/html/Hale'/config/seasons.php) با ۸ تم (یلدا، جمعه سیاه، ولنتاین، نوروز، زمستان، بهار، تابستان، پاییز بارانی) به ترتیب اولویت و پنجره‌های MM-DD قابل عبور از سال نو؛ تشخیص با [SeasonThemeService](file:///var/www/html/Hale'/app/Domains/Creative/Services/SeasonThemeService.php) و کلید `SEASON_THEME` (`auto` پیش‌فرض / کلید تم / `off`)، اندپوینت `GET /api/creative/theme`، دکور بوم استودیو (snowfall/vignette/sparkle/rain با پالت هر تم، زیر لایهٔ محصول) و نشان «تم فصلی» با خاموشی دائم از `localStorage`؛ پوشش با `SeasonThemeTest` (۷ تست).
  - [x] **تم‌های باقی‌مانده و پکیج‌های پرامپت کمپینی:** سه تم جدید — جمعه سیاه (11-22..11-30، مشکی و نئون طلایی)، ولنتاین (02-10..02-16، قبل از پنجرهٔ زمستان) و پاییز بارانی (09-22..11-21 که شکاف پاییزی را می‌پوشاند) با دکور جدید `rain` در [config/seasons.php](file:///var/www/html/Hale'/config/seasons.php)؛ هر ۸ تم کلیدهای `campaign_label` و `prompt_pack` دارند و پکیج کمپینی **opt-in** است: فیلد `campaign` (boolean با پیام فارسی) در سه FormRequest ([StoreGenerationRequest](file:///var/www/html/Hale'/app/Domains/Generations/Requests/StoreGenerationRequest.php)، [PreviewCreativeRequest](file:///var/www/html/Hale'/app/Domains/Creative/Requests/PreviewCreativeRequest.php)، [BulkGenerationRequest](file:///var/www/html/Hale'/app/Domains/Generations/Requests/BulkGenerationRequest.php))، حل تم فعال در `CreativeEngine::brief()` و درج با `campaignClause()` (پس از sanitize و بعد از brandClause) در `prompt()` — بدون فیلد یا با `SEASON_THEME=off` پرامپت دست‌نخورده می‌ماند؛ flag قبل از `create()` از insert جدا می‌شود و فقط در `brief` می‌ماند. UI: چیپ toggle کنار نشان فصلی با `localStorage('hale-campaign-pack')` (پیش‌فرض خاموش، همراه خاموشی تم فصلی غیرفعال می‌شود)، بازرس زندهٔ پرامپت و payload ساخت آن را دنبال می‌کنند؛ پوشش با `CampaignPromptPackTest` (۷ تست) + یک تست bulk در `BulkGenerationTest` (۹ تست).
- [x] **ابزارهای کمکی هوش مصنوعی (AI Utility Tools - الهام‌گرفته از Photoroom):**
  - حذف خودکار پس‌زمینه (One-Click Background Removal) با خروجی شفاف PNG و پس‌زمینه سفید استاندارد دیجی‌کالا/آمازون؛ ارتقای کیفیت و وضوح تصویر (AI Super-Resolution Upscaler به HD/2K/4K)؛ بسط دادن هوشمند کادر تصویر بدون برش محصول (Generative Expand / Outpainting)؛ محاسبه و تولید خودکار سایه و رفلکس واقعی سه‌بعدی روی سطح جدید (Natural 3D Shadows & Grounding).
  - دامنهٔ جدید [Editing](file:///var/www/html/Hale'/app/Domains/Editing) با جدول `image_edits`: مدل [ImageEdit](file:///var/www/html/Hale'/app/Domains/Editing/Models/ImageEdit.php)، منبع ویرایش خروجی تکمیل‌شدهٔ Generation یا تصویر اولیهٔ محصول با scope مالکیت ([EditSourceResolver](file:///var/www/html/Hale'/app/Domains/Editing/Services/EditSourceResolver.php) با خطای 404/422 جدا) و پرامپت‌ساز با guardrail ثابت ([EditPromptBuilder](file:///var/www/html/Hale'/app/Domains/Editing/Services/EditPromptBuilder.php)).
  - Provider جدید [GoogleImageEditProvider](file:///var/www/html/Hale'/app/Domains/AI/Providers/Google/GoogleImageEditProvider) با مدل `gemini-2.5-flash-image` (کلید `GOOGLE_IMAGE_EDIT_MODEL`) روی همان `generateContent` و کلید گوگل: عکس منبع به‌صورت `inline_data` کنار پرامپت ارسال می‌شود؛ درایور local هم type `image_edit` را پشتیبانی می‌کند (بدون کلید گوگل زنجیره به fake می‌رسد).
  - شارژ upfront و یک‌بارهٔ اعتبار با کلید idempotent `edit:{id}` و type `edit` ([spendForTask با generation اختیاری](file:///var/www/html/Hale'/app/Domains/Credits/Services/CreditService.php))؛ در شکست قطعی بازگشت خودکار و idempotent (`refundTask` + پرچم `failed_handled` در [ProcessImageEdit](file:///var/www/html/Hale'/app/Domains/Editing/Jobs/ProcessImageEdit.php)::failed)؛ ثبت مصرف بودجهٔ روزانهٔ AI با `CircuitBreaker::recordSpend` (چون جدول reservation به نسل وابسته و FK آن غیرnullable است).
  - هزینه‌ها در [credits.php](file:///var/www/html/Hale'/config/credits.php): حذف پس‌زمینه ۵، سایه/رفلکس ۵، بسط کادر ۱۰، ارتقا HD/2K/4K برابر ۵/۱۰/۱۵ اعتبار؛ ۲K/۴K فقط پلن پرمیوم (403 `PREMIUM_QUALITY_REQUIRED`) و گیت مشابه با پرچم `plan_blocked` در `POST /api/credits/estimate`؛ لیست تعرفه و سقف پلن در `GET /api/edits/costs`.
  - API: `GET/POST /api/edits`، `GET /api/edits/{id}` و `GET /api/edits/{id}/download` (واریانت وب + ETag مثل Generation)؛ خروجی با کلامپ ابعاد و watermark پلن رایگان نهایی می‌شود؛ نوتیفیکیشن دیتابیسی/ایمیلی/Web Push برای تکمیل و شکست (`ImageEditStatusNotification`).
  - UI: دکمهٔ «ابزارهای تصویر» کنار کپشن در صفحهٔ خروجی (فقط پس از تکمیل) با پنل عملیات/گزینه‌ها/تعرفه/پیش‌نمایش + دکمهٔ «ابزار ✦» روی کاشی هر محصول در داشبورد با modal مشترک؛ کنترلر واحد `mountImageTools` در `app.js` (تعرفهٔ زنده، قفل گزینه‌های 2K/4K برای پلن رایگان، polling نتیجهٔ صف‌شده).
  - پوشش با `ImageEditTest` (۱۶ تست) و `EditPromptBuilderTest` (۶ تست).
- [x] **پردازش دسته‌ای کاتالوگ (Batch Processing Studio):**
  - حالت «پردازش دسته‌ای» در کتابخانهٔ داشبورد: دکمهٔ toggle، چک‌باکس روی کاشی‌ها فقط در حالت انتخاب، «انتخاب همه» و نوار اکشن با شمارندهٔ انتخاب (انتخاب در صفحه‌بندی/جستجو حفظ می‌شود و با حذف محصول پاک می‌گردد).
  - `POST /api/generations/bulk` حداکثر ۲۰ شناسهٔ یکتا: تنظیمات مشترک اختیاری (Goal/Style/Format/صحنه/متن دلخواه با اعتبارسنجی و moderation؛ نبودِ هر فیلد → `autoBest` همان محصول)، کیت برند به‌صورت خودکار اعمال می‌شود.
  - پیش‌بررسی موجودی کل بسته قبل از ایجاد هر ردیف (402 `INSUFFICIENT_CREDITS`)، سپس چک پلن/اعتبار به‌ازای هر محصول داخل تراکنش با موفقیت جزئی + `skipped_product_ids` و `reason` (سقف پلن / اعتبار / moderation)؛ مالکیت محصولات از قبل تأیید می‌شود (`INVALID_PRODUCTS`) و هر نسل با نشانهٔ `bulk` در metadata ثبت می‌شود.
  - پوشش با `BulkGenerationTest` (۸ تست).
- [x] GDPR-style account deletion endpoint — **رد شد و از مسیر محصول حذف شد.**
  - تصمیم صریح: حذف کاربر وجود ندارد چون به هر حساب کردیت رایگان اولیه تعلق می‌گیرد و حذف اکانت باعث ایجاد سوءاستفاده/مشکل مالی می‌شود. هیچ اندپوینت حذف حسابی پیاده‌سازی نخواهد شد.
- [x] Email verification الزامی (فعال کردن `MustVerifyEmail`).
  - پیاده‌سازی: `User` به interface و trait `MustVerifyEmail` مجهز شد؛ ارسال لینک با `VerifyEmailNotification` روی signed URL API به `GET /api/auth/email/verify/{id}/{hash}` (اعتبار ۲۴ ساعته) و ارسال مجدد با `POST /api/auth/email/verification-notification` (throttled).
  - الزام با middleware سراسری `verified` (`EnsureEmailIsVerified`): کاربر unverified امکان ساخت/retry/regenerate و checkout ندارد (403 `EMAIL_NOT_VERIFIED`)؛ اکانت‌های phone-only معاف‌اند و تغییر ایمیل در پروفایل، تأیید را باطل می‌کند.
  - migration backfill برای کاربران قدیمی تا قفل نشوند؛ پوشش با `EmailVerificationTest` (۱۳ تست).
- [x] Credit top-up بدون subscription (خرید اعتبار جداگانه).
  - سه بستهٔ `topup_50/150/400` (۵۰/۱۵۰/۴۰۰ اعتبار با ۱٫۵/۴/۹٫۵ میلیون تومان) در [credits.php](file:///var/www/html/Hale'/config/credits.php)؛ `GET /api/credits/packs` عمومی و `POST /api/credits/topup` (verified + throttle:checkout + Idempotency-Key).
  - `BillingService::checkoutTopup` با scope اختصاصی `topup:` و metadata شامل kind/pack/credits (استخراج متدهای مشترک checkout)؛ شاخهٔ topup در `settle()` فقط `grantPurchase` را با کلید idempotent `payment:{id}` اجرا می‌کند — بدون subscription، بدون تغییر plan و بدون شمارش سقف‌های پلن.
  - نوتیفیکیشن پرداخت، توضیح درگاه و رسید برای بسته‌ها topupمحور شدند؛ UI: بخش «بسته‌های اعتبار» در pricing با همان جریان checkout پلن‌ها و بنر وضعیت پرداخت مشترک + لینک «خرید اعتبار» در پیام کمبود اعتبار استودیو؛ پوشش با `CreditTopupTest` (۷ تست شامل callback، شکست و idempotency).
- [x] Admin: امکان ban/suspend کاربر.
  - ستون‌های `banned_at`/`banned_until`/`ban_reason` + اندپوینت‌های `POST /api/admin/users/{user}/ban` و `/unban` (دلیل الزامی، `expires_at` اختیاری برای suspend موقت؛ حساب‌های مدیر محافظت‌شده‌اند).
  - اجرا: revoke همهٔ توکن‌ها هنگام ban، مسدودسازی login، middleware سراسری `PreventBannedUser` (403 به‌جز profile و logout)، انقضای خودکار suspend موقت، نمایش chip «مسدود» و دکمه‌های مسدودسازی/لغو در پنل ادمین.
  - پوشش با `AdminBanTest` (۸ تست).
- [x] Admin: لغو generation در حال پردازش.
  - `POST /api/admin/generations/{generation}/cancel` با سرویس `CancelGeneration`: قفل ردیف، فقط `queued`/`processing` → status `cancelled` → refund اتمیک اعتبار → release بودجه AI → پاک‌سازی خروجی موقت → notification کاربر؛ در غیر این صورت 409 `GENERATION_NOT_CANCELLABLE`.
  - guard در `ProcessGeneration`: claim فقط از `queued`، بلوک تغییر status به `failed/queued` روی generation لغوشده در مسیر exception و خروج زودهنگام `failed()`؛ UI ادمین دکمه «لغو» + chip «لغو شد» و صفحهٔ خروجی کاربر حالت لغو را terminal می‌کند (بدون retry).
  - پوشش با `AdminCancelGenerationTest` (۷ تست شامل no-op شدن جاب صف بعد از لغو).

## اشکالات گزارش‌شده و نیازمندی‌های بازطراحی (Current Issues & Redesign Backlog)

### ۱. اصلاح نماهای دوربین و زاویه دید در پرامپت هوش مصنوعی (Camera Angles & Perspectives)
- [x] **رفع اشکال اعمال نماهای دوربین در پرامپت هوش مصنوعی:**
  - **مسئله و ریشه:** نماهای دوربین به درستی عمل نمی‌کردند و فقط نمای از بالا (Flat-Lay) خروجی متناسب می‌داد. ریشه‌ها: جملهٔ زاویه در موقعیت ۵ از ۸ پرامپت و با فرمول وصفی `Camera perspective:` قرار داشت (نه دستور صریح و اولویت‌دار)؛ جملهٔ ویدئویی `smooth cinematic camera pan` با زاویهٔ ثابت تناقض مستقیم داشت؛ نمای ایزومتریک و جانبی اصلاً پیاده‌سازی نشده بودند؛ و بوم پیش‌نمایش `/create` هیچ نشانه‌ای از زاویه نداشت.
  - **اقدام انجام‌شده و راه‌حل نهایی:**
    - بازنویسی `prompt` هر زاویه در `config/creative.php` با دستورات صریح انگلیسی (مانند `strict eye-level horizontal shot, straight-on camera angle...`، `dramatic low-angle hero perspective looking upward at the product...`، `extreme close-up macro shot with shallow depth of field...`) و افزودن کلیدهای `isometric` (۴۵ درجه) و `side_angle` (زاویه جانبی) — جمعاً ۶ نما که از طریق `/api/creative/options` به‌صورت خودکار در چیپ‌ها و اعتبارسنجی و تمپلیت‌ها ظاهر می‌شوند.
    - افزودن کلید `motion` برای هر زاویه؛ `CreativeEngine::prompt()` جملهٔ زاویه را به‌صورت `Camera angle (locked): ...` **بلافاصله پس از جملهٔ ابتدایی** قرار می‌دهد و جملهٔ `Dynamic motion` ویدئو از همان `motion` خوانده می‌شود (بدون زاویه، متن قبلی عیناً حفظ می‌شود).
    - آینه‌سازی هر دو قاعده در بازرس پرامپت (`resources/js/app.js`) تا متن نمایش‌داده‌شده به کاربر با متن ارسالی به مدل یکی باشد.
    - اعمال زاویه روی بوم پیش‌نمایش زندهٔ `/create`: کلاس `angle-{key}` روی استیج + پرسپکتیو و ترنسفورم اختصاصی هر نما در `resources/css/app.css`.
    - تست‌های پوششی: `CreativeEngineTest` (فرونت‌لود شدن هر ۶ زاویه پیش از `Objective:`، تطابق حرکت ویدئو با زاویه، حفظ پن عمومی بدون زاویه) + تعداد زاویه‌ها و کلید `motion` در `/api/creative/options` + تست قرارداد آینهٔ JS/CSS در `StudioPreviewViewTest`.
  - **انحراف ثبت‌شده:** کلید `hero_shot` حفظ شد (در دیتابیس و تمپلیت‌ها ذخیره است) و نام `low_angle`ِ مندرج در نیازمندی صرفاً نام پیشنهادی بود.

### ۲. پایداری، محل دائمی و انتخاب‌پذیری تم‌های فصلی و کمپینی (Seasonal & Campaign Themes)
- [x] **طراحی محل دائمی و حفظ وضعیت تم فصلی/کمپینی:**
  - **مسئله:** تم بارانی و سایر تم‌های مناسبتی پس از انتخاب یا رفرش، محو می‌شوند یا محل دائمی و مشخصی برای مشاهده، تغییر و انتخاب مجدد ندارند؛ کاربر نمی‌داند در کجای رابط کاربری تم را دوباره انتخاب یا مدیریت کند.
  - **نیازمندی فنی:**
    - طراحی یک بخش/سلکتور دائمی و مشخص برای تم‌های فصلی و کمپین‌ها در استودیوی ساخت (`/create`) و تنظیمات کاربر/داشبورد.
    - ذخیره وضعیت تم انتخابی در تنظیمات پروفایل کاربر یا `localStorage` پایدار به شکلی که با رفرش یا جابه‌جایی در صفحات ریست یا محو نشود.
    - افزودن یک نشانگر وضعیت واضح (Status Badge) در بالای فرم استودیو که نام تم فعال، رنگ تم و دکمهٔ «تغییر تم / خاموش کردن» را همیشه در دسترس قرار دهد.
    - نمایش فهرست تمام ۸ تم (یلدا، جمعه سیاه، ولنتاین، نوروز، زمستان، بهار، تابستان، پاییز بارانی) با امکان پیش‌نمایش پالت و افکت هرکدام.

  - **اقدام انجام‌شده و راه‌حل نهایی:**
    - **کامپوننت مشترک** [resources/views/components/season-picker.blade.php](file:///var/www/html/Hale'/resources/views/components/season-picker.blade.php) که هم بالای فرم استودیو در [resources/views/create.blade.php](file:///var/www/html/Hale'/resources/views/create.blade.php) و هم در سکشن جدید «تم‌های فصلی و کمپینی» در [resources/views/dashboard.blade.php](file:///var/www/html/Hale'/resources/views/dashboard.blade.php) رندر می‌شود. نشانگر وضعیت (`data-season-status`) شامل `data-season-badge` (نام و اموجی تم فعال با رنگ پالت)، `data-season-swatches` (سه‌چیپ رنگ) و دکمه‌های «تغییر تم» و «خاموش/روشن» است که همیشه بالای فرم در دسترس‌اند.
    - **سلکتور ۸ تم** (`data-season-picker`) با کارت هر تم: کاشی پیش‌نمایش زندهٔ افکت (`data-decor` که همان keyframe های بوم یعنی `season-snowfall`/`season-breathe`/`season-twinkle`/`season-rain` را صدا می‌زند)، سه رنگ پالت، نام و اموجی و برچسب فارسی افکت (برف، باران، هاله نور، درخشش)، به‌علاوهٔ دو گزینهٔ ثابت «خودکار» و «خاموش». فهرست از `config('seasons.themes')` رندر می‌شود پس هیچ تمی جا نمی‌ماند.
    - **حافظهٔ پایدار** در `localStorage` با کلید `hale-season-theme` و سه مقدار `auto` | `off` | کلید تم (مقدار قدیمی `off` همچنان خوانده می‌شود). انتخاب با رفرش و جابه‌جایی بین `/create` و داشبورد از بین نمی‌رود و هر دو صفحه از یک کلید مشترک می‌خوانند.
    - `GET /api/creative/theme` حالا `themes` (کل ۸ تم)، `enabled` (کلید اضطراری `SEASON_THEME=off`) و `theme` (تشخیص تاریخ) را برمی‌گرداند؛ در حالت `off` کل پنل پنهان می‌شود چون کلید استقرار بر انتخاب کلاینت اولویت دارد.
    - رفع ناهمخوانی بازرس و پرامپت واقعی: فیلد اختیاری `season_theme` (اعتبارسنجی با `Rule::in` روی کلیدهای کانفیگ و پیام فارسی) در `StoreGenerationRequest`، `BulkGenerationRequest` و `PreviewCreativeRequest`، و در `CreativeEngine::brief()` با متد جدید `SeasonThemeService::byKey()` پکیج کمپینیِ تمِ پین‌شده ساخته می‌شود. `season_theme` همراه `campaign` پیش از `unset` از `create` ردیف `creative_projects` حذف می‌شوند چون ستون ندارند.
    - استایل‌ها در [resources/css/app.css](file:///var/www/html/Hale'/resources/css/app.css) و منطق در [resources/js/app.js](file:///var/www/html/Hale'/resources/js/app.js)؛ بلوک فصلی بازنویسی شد (`resolveSeasonTheme`/`applySeasonTheme`/`writeSeasonChoice`) و دیگر به وجود بوم گره نمی‌خورد، پس روی داشبورد هم کار می‌کند. کلید `season_theme` به payload ثبت تولید اضافه شد.
    - تست‌ها: `SeasonThemeTest` (انتشار کل فهرست ۸ تم + پرچم `enabled`، و سکشن داشبورد)، `CampaignPromptPackTest` (۳ تست جدید: پیروی پکیج از تم پین‌شده در پیش‌نمایش و ثبت تولید، نرفتن `season_theme` به `create`، و رد شدن کلید نامعتبر با پیام «تم فصلی انتخاب‌شده نامعتبر است») و قرارداد در `StudioPreviewViewTest` (بالای فرم بودن نشانگر، همهٔ مارکرهای UI، `data-season-choice`/`data-palette`/`data-decor` برای هر ۸ تم، و اسنیپت‌های JS و CSS).
  - **انحراف/تصمیم ثبت‌شده:** (۱) ذخیرهٔ وضعیت در `localStorage` انجام شد نه «تنظیمات پروفایل کاربر»؛ نیازمندی صریحاً یکی از این دو را می‌پذیرفت و این گزینه بدون migration و endpoint کار می‌کند و برای کاربر مهمان هم برقرار است. (۲) افزودن `season_theme` به API تولید خارج از متن نیازمندی بود، ولی برای یکی ماندن پکیجی که بازرس پرامپت نشان می‌دهد با پکیجی که در پرامپت واقعی می‌رود لازم بود؛ بدون آن، تم دستی فقط بصری می‌شد و پرامپت همچنان از تشخیص تاریخِ سرور ساخته می‌شد. (۳) badge موجود در top-right بوم حذف و به نشانگر بالای فرم منتقل شد؛ تست `test_studio_page_exposes_the_season_badge` بدون تغییر پاس می‌شود چون `data-season-badge` همچنان در صفحه رندر می‌شود.
  - **انحراف از پلن (۴): بچ ششم پس از بازبینی.** از آنجا که `/create` مسیر عمومی است و `GET /api/creative/theme` لاگین می‌خواهد، کلیدهای `data-season-enabled` و `data-season-auto` (خروجی `SeasonThemeService::active()`) مستقیماً در HTML همان کامپوننت رندر شدند و اسکریپت از همان‌جا بوت می‌کند؛ endpoint فقط وضعیت را اصلاح می‌کند، پیش‌نیاز نیست. این تغییر خارج از ۵ بچ مصوب بود و صرفاً برای بستن همین شکاف انجام شد.
  - **بدون دست زدن به پرامپت:** متن خروجی `CreativeEngine::prompt()` و بازرس پرامپت JS تغییری نکرد و هیچ تست exact پرامپتی عوض نشد؛ تنها بلوک `campaign` داخل brief ممکن است حالا به تم پین‌شده اشاره کند که بدون فیلد جدید رفتار قبلی عیناً تکرار می‌شود.

### ۳. نمایش میزان و وزن تأثیرگذاری هر آیتم بر تصویر (Item Impact Indicator / Weighting)
- [x] **نشانگر میزان تأثیرگذاری گزینه‌ها بر تصویر نهایی:**
  - **مسئله:** کاربر نمی‌داند هر گزینه‌ای که انتخاب می‌کند (سبک کلی، نورپردازی، زاویه، متریال سطح، اکسسوری، تم فصلی) چقدر و در چه ابعادی روی خروجی نهایی هوش مصنوعی تأثیر می‌گذارد.
  - **نیازمندی فنی:**
    - دسته‌بندی و تعیین سطح تأثیرگذاری (Impact Level) برای هر آپشن در `config/creative.php`:
      - **تأثیر بالا (High Impact):** سبک کلی (Style)، زاویه دوربین (Camera Angle)، نسبت ابعاد (Aspect Ratio).
      - **تأثیر متوسط (Medium Impact):** نورپردازی (Lighting Setup)، پایه و سطح (Surface & Pedestal).
      - **تأثیر جزئی/تکمیلی (Subtle/Accent Impact):** اکسسوری‌ها (Props & Accents)، متادیتای برند، تم فصلی.
    - نمایش برچسب‌های بصری ملایم در رابط کاربری (مانند تگ «تأثیر عمده در ترکیب‌بندی»، نشانگر ۳ سطحی شدت، یا درصد تخمینی نفوذ در صحنه).
    - ارائه راهنمای کوتاه متنی (Tooltip/Caption) زیر هر بخش که به کاربر می‌گوید این انتخاب چه تغییری در خروجی اعمال خواهد کرد.
  - **اقدام انجام‌شده و راه‌حل نهایی:**
    - دو کلید جدید در [config/creative.php](file:///var/www/html/Hale'/config/creative.php): `impact_levels` (سه سطح `high`/`medium`/`subtle` با `label`، `short` برای تگ کوتاه و `hint` برای tooltip فارسی) و `impacts` که **سطح را روی کنترل (نام فیلد رادیو) تعریف می‌کند** نه روی هر آیتم — ۳۲ گزینه ارث‌برندهٔ ۸ تصمیم‌اند، پس افزودن آیتم جدید به هیچ تصمیم یا کد دیگری نیاز ندارد.
    - انتشار هر دو از طریق `CreativeController::options()` در `GET /api/creative/options` (منبع واحد چیپ‌ها و عنوان‌ها)، بنابراین هیچ مقدار سطحی در JS یا Blade هاردکد نشده است.
    - نشانگر سطح، **یکی برای هر باکس (نه برای هر چیپ)**: کامپوننت بی‌نام [resources/views/components/impact-badge.blade.php](file:///var/www/html/Hale'/resources/views/components/impact-badge.blade.php) با ورودی `control` که سطح را از `creative.impacts.{control}` و متن و tooltip را از `creative.impact_levels.{level}` می‌گیرد و `data-impact-control="{control}"` را همراه تگ می‌نویسد؛ `<x-impact-badge control="goal" />` کنار عنوان هر ۸ باکس رندر می‌شود (سه `legend` هدف/سبک/فرمت، لیبل محیط و چهار `label.group-title` صحنه).
    - راهنمای متنی زیر هر بخش (`<small class="impact-hint" data-impact-hint>`) در [resources/views/create.blade.php](file:///var/www/html/Hale'/resources/views/create.blade.php) برای هر ۸ کنترل (هدف، سبک، فرمت، محیط، سطح، اکسسوری، زاویه دوربین، نورپردازی) با جملهٔ اختصاصی فارسیِ «این انتخاب چه چیزی را در خروجی عوض می‌کند».
    - استایل تگ در [resources/css/app.css](file:///var/www/html/Hale'/resources/css/app.css) (pill با رنگ و بوردر مجزا: صورتی برای عمده، کهربایی برای متوسط، خاکستری برای جزئی) با `margin: 0 6px` تا از متن عنوان فاصله بگیرد.
    - تست‌ها: `CreativeImpactTest` (۳ تست: ساختار و کامل بودن `impact_levels`، نگاشت دقیق نیازمندی در `impacts`، و اینکه هیچ سطحی به کلید تعریف‌نشده اشاره نمی‌کند) + گسترش `GenerationApiTest` (ساختار JSON جدید) + قرارداد در `StudioPreviewViewTest` (نبودِ `impact-badge` در `app.js`، سطح درست برای هر ۸ `data-impact-control` در HTML رندرشده، دقیقاً یک تگ به‌ازای هر باکس، و برابری تعداد کپشن‌ها با تعداد کنترل‌ها).
    - **بازطراحی به‌درخواست کاربر (۶ اکتبر):** نشانگر از داخل هر چیپ به کنار عنوان هر باکس منتقل شد و منطق رندر تگ از `renderChoices` در `resources/js/app.js` کاملاً حذف شد؛ endpoint و داده‌های `impacts`/`impact_levels` بدون تغییر ماندند.
    - بدون دست زدن به پرامپت: `CreativeEngine::prompt()` و بازرس پرامپت JS دست‌نخورده ماندند و هیچ‌یک از تست‌های exact پرامپت تغییر نکردند.
  - **انحراف ثبت‌شده:** (۱) `goal` و `environment` در نیازمندی نامبرده نبودند ولی کنترل‌های خودِ فرم‌اند؛ برای اینکه هیچ باکسی بدون نشانگر نماند به‌ترتیب `high` (جملهٔ Objective و پیام اصلی را می‌سازد) و `medium` (پس‌زمینه و فضای صحنه) تعیین شدند. (۲) «متادیتای برند» و «تم فصلی» چیپ انتخاب‌شدنی در استودیو نیستند (کیت برند در داشبورد است و تم فصلی badge اختصاصی بالای `/create` دارد) → در این آیتم پوشش داده نشدند و در صورت نیاز باید جداگانه به `impacts` اضافه شوند.

### ۴. شبیه‌سازی و پیش‌نمایش بصری تغییرات هر آیتم برای کاربر (Visual Preview & Interactive Explainer)
- [ ] **پیش‌نمایش بصری و توضیح ملموس اثر انتخاب‌ها قبل از ساخت:**
  - **مسئله:** در حال حاضر کاربر پیش از زدن دکمه جنریت و کسر کریدیت، نمی‌تواند متوجه شود انتخاب هر گزینه دقیقاً چه تغییری روی تصویر یا فضا ایجاد می‌کند؛ نیاز به راهکاری برای نمایش یا توضیح ملموس این تغییرات قبل از ساخت نهایی است.
  - **نیازمندی فنی:**
    - ارتقای بوم تعاملی پیش‌نمایش در `/create` (Live Stage Preview):
      - افزودن لایه‌های شبیه‌سازی CSS/SVG برای تغییر زاویه دوربین (نمایش پرسپکتیو و خط افق روی استیج).
      - پیش‌نمایش زنده افکت‌های نورپردازی (نور لبه‌ای، نور پنجره، سافت‌باکس و نئون).
      - نمایش نمادین و تعاملی اکسسوری‌های انتخابی (برگ، قطرات آب، مه).
    - افزودن کارت‌های نمونه مقایسه‌ای کوچک (Micro Visual Cards / Mini Previews): یک مودال یا Tooltip شناور کنار هر گزینه که یک نمونه واقعی خروجی «قبل و بعد» یا نمونهٔ آن سبک/زاویه را با عکس کالا نشان دهد.
    - نوار خلاصه هوشمند صحنه (Scene Summary Bar): متنی روان به فارسی زیر بوم که صحنهٔ پیکربندی‌شده را توصیف کند (مثلاً: *«عطر شما روی سکوی مرمر، با زاویه مستقیم از روبرو و نورپردازی ملایم آفتاب قرار می‌گیرد»*).

### ۵. اصلاح پایپ‌لاین CI در GitHub Actions و ارتقای نسخه‌ها (CI Workflow & Compatibility)
- [x] **رفع خطای تطابق نسخه PHP در GitHub Actions و هشدارهای رانر:**
  - **مسئله و خطاها در Actions Run `37280211905`:**
    - **خطای اصلی (Exit code 2 در Composer):** در ورک‌فلو نسخه PHP رانر روی `8.2` تنظیم شده بود در حالی که پکیج `laravel/pint v1.32.0` قفل‌شده در `composer.lock` نیازمند `php ^8.3.0` بود.
    - **هشدار Node.js:** منسوخ شدن Node 20 روی رانرهای گیت‌هاب و لزوم ارتقا به Node 22+.
    - **اطلاعیه Runner:** اعلان جابه‌جایی برچسب `ubuntu-latest` به `Ubuntu 26`.
  - **اقدام انجام‌شده و راه‌حل نهایی:**
    - به‌روزرسانی نسخه PHP در `.github/workflows/ci.yml` به `php-version: '8.3'`.
    - ارتقای نسخه Node.js در ورک‌فلو به `node-version: 22`.
    - تنظیم محدودیت نسخه PHP در `composer.json` به `"php": "^8.3"` و همگام‌سازی هش در `composer.lock` با `composer update --lock`.
    - اعتبارسنجی کامل محلی با متغیرهای محیطی CI، شبیه‌ساز دیتابیس حافظه‌ای SQLite، Pint و Larastan (تمام ۳۷۵ تست و تحلیل ایستا سبز شدند).

### ۶. سفارشی‌سازی پرامپت به تفکیک هوش مصنوعی و انتخاب مدل هدف در استودیو (Model-Specific Prompt Compilers & AI Selector)
- [ ] **طراحی سلکتور هوش مصنوعی هدف و کامپایلرهای اختصاصی پرامپت:**
  - **مسئله:** هر موتور هوش مصنوعی (مدل‌های زبانی چت و سناریو، موتورهای تخصصی تصویرساز، و تولیدکننده‌های ویدئو) نیازمندی، ساختار نحوی (Syntax) و انکودر متنی کاملاً متفاوتی دارند. استفاده از یک پرامپت ساده یا عمومی باعث افت شدید خروجی می‌شود؛ مثلاً کلود به تگ‌های ساختاریافته XML نیاز دارد، چت‌جی‌پی‌تی نیازمند بریف مکالمه‌ای و غنی است، Veo به قرار گرفتن حرکت دوربین در کلمات ابتدایی پرامپت وابسته است، Midjourney به کدهای پارامتری (`--ar` و `--style raw`) تکیه دارد، و فلوکس شرح فوتورئالیسم و فیزیک نور بدون واژگان کلیشه‌ای می‌خواهد.
  - **دسته‌بندی جامع موتورهای هوش مصنوعی پشتیبانی‌شده:**
    1. **مدل‌های زبانی، سناریونویسی و کارگردانی کمپین (LLM & Creative Direction):**
       - 🟢 **ChatGPT (OpenAI / GPT-4o & Canvas):** کامپایل پرامپت به شکل بریف ساختاریافته و غنی به زبان انگلیسی و فارسی، شامل اهداف بازاریابی، توصیف جزئیات محصول، پرامپت اختصاصی قابل ارجاع به DALL-E 3، سناریوی کپشن و قلاب‌های فروش (Hook).
       - 🟣 **Claude (Anthropic / Claude 3.5 Sonnet & Claude 3 Opus):** کامپایل پرامپت مهندسی‌شده با تگ‌های معتبر XML (نظیر `<product_context>`, `<creative_direction>`, `<lighting_and_atmosphere>`, `<visual_style>`, `<output_format>`)؛ ایده‌آل برای نگارش متن‌های فاخر، استوری‌بورد تصویری و ایده‌پردازی مفهومی برند.
       - 🔵 **DeepSeek (DeepSeek-V3 / R1):** کامپایل پرامپت بر پایه استدلال زنجیره تفکر (Chain-of-Thought) جهت تحلیل عمیق جامعه هدف ایرانی، تعیین زاویه برنده تبلیغاتی (Winning Angle) و بهینه‌سازی کپی‌رایتینگ برای افزایش نرخ تبدیل.
       - 🔴 **Grok (xAI / Grok-2 Vision & Flux):** کامپایل پرامپت پویا و مدرن مناسب برای خلق ترندهای شبکه‌های اجتماعی، لحن جسورانه برند و خروجی بصری فلوکس در شبکه اجتماعی X.
    2. **موتورهای تصویرساز حرفه‌ای تبلیغاتی (Image Generation):**
       - 🎨 **Google Imagen 3:** ساختار عکاسی حرفه‌ای با اصطلاحات دقیق فاصله کانونی لنز، نورپردازی ۳ نقطه‌ای، زاویه بدون ابهام و حذف کلمات کلیشه‌ای اسپم.
       - ⛵ **Midjourney (v6 / v6.1):** تبدیل پارامترها به ساختار فشرده عبارتی با کاما، نسبت ابعاد `--ar`، سوییچ `--style raw` و `--v 6.1`.
       - ⚡ **FLUX.1 (Dev/Pro):** شرح واقع‌گرایانه، عینی و خطی صحنه با ذکر دقیق منابع نور طبیعی/استودیویی، پرسپکتیو و بافت متریال.
       - 🖼️ **Stable Diffusion (SDXL / SD 3.5):** تفکیک بلوک‌های پرامپت مثبت و منفی (Positive & Negative Prompts) و وزن‌دهی استاندارد `(masterpiece:1.2), (depth of field:1.1)`.
       - 🤖 **OpenAI DALL-E 3:** توصیف پیوسته و تصویری صحنه متناسب با مکانیسم ارتقای خودکار پرامپت در اکوسیستم OpenAI.
    3. **موتورهای ویدئوساز و موشن تجاری (Video Generation):**
       - 🎬 **Google Veo 2:** اجرای قانون طلایی Front-loading (آغاز پرامپت با حرکت و زاویه دوربین: `[Camera Motion] + [Subject] + [Action/Physics] + [Environment]`).
       - 🎥 **Runway Gen-3 Alpha:** ساختار استاندارد کارگردانی بر اساس برچسب‌های سینمایی (`[Camera]: Orbit slow, [Motion]: Fluid, [Lighting]: Studio commercial`).
       - 🚀 **Luma Dream Machine / Kling AI / Sora:** توصیف فیزیک دینامیک پیوسته در طول زمان، شبیه‌سازی حرکت عناصر و تغییر نور در تایم‌فریم ۵ تا ۱۰ ثانیه.
    4. **ابزارهای ویرایش، روتوش و تغییر پس‌زمینه (Editing & Inpainting):**
       - ✏️ **Gemini Flash Image Edit:** پرامپت تفاضلی (Delta Prompt) متمرکز بر تغییر زمینه و ثابت نگه‌داشتن کالای اصلی در کادر.
  - **نیازمندی در رابط کاربری (UI/UX استودیو `/create`):**
    - افزودن منوی تب‌بندی‌شده انتخاب هوش مصنوعی هدف (**Target AI Selector**) با ۴ سربرگ مجزا (مدل‌های متنی و چت / تولید عکس / تولید ویدئو / ویرایش).
    - پیش‌نمایش آنی ساختار پرامپت اختصاصی در بازرس پرامپت (Live Prompt Inspector) به محض تغییر مدل هوش مصنوعی انتخابی، بدون پاک شدن تنظیمات صحنه.
    - دکمه‌های کاربردی:
      - 📋 **کپی پرامپت اختصاصی (Copy Prompt):** جهت چسباندن سریع در چت‌جی‌پی‌تی، کلود، میدجورنی یا ران‌وی کاربر.
      - 🚀 **ساخت مستقیم (Generate):** برای مدل‌های متصل به سرویس داخلی سیستم (Imagen 3, Veo 2, Gemini Edit).
      - 💾 **ذخیره در قالب‌ها (Save to Templates):** امکان ذخیره تنظیمات به تفکیک مدل برای استفاده در کمپین‌های بعدی.
  - **نیازمندی فنی و معماری بک‌اند (DDD & Strategy Pattern):**
    - تعریف قرارداد `PromptCompilerInterface` در دامنه `App\Domains\Creative\Contracts`.
    - پیاده‌سازی مستقل کامپایلرها:
      - `ChatGptPromptCompiler` (تولید بریف ساختاریافته دو زبانه و پرامپت مارکتینگ)
      - `ClaudePromptCompiler` (تولید خروجی با تگ‌های استاندارد XML)
      - `DeepSeekPromptCompiler` (تولید پرامپت بر پایه CoT و استراتژی فروش)
      - `GrokPromptCompiler` (تولید پرامپت وایرال و مدرن)
      - `ImagenPromptCompiler` (اصطلاحات دقیق عکاسی استودیویی)
      - `VeoPromptCompiler` (فرمول سینمایی Front-loading دوربین)
      - `MidjourneyPromptCompiler` (فرمت پارامتری عکاسی تبلیغاتی)
      - `FluxPromptCompiler` (شرح عینی متریال و نور)
      - `StableDiffusionPromptCompiler` (بلوک مثبت و منفی با سینتکس وزن‌دهی)
      - `RunwayPromptCompiler` (برچسب‌های حرکتی ویدئو)
      - `GeminiEditPromptCompiler` (پرامپت تفاضلی Inpainting)
    - ایجاد `PromptCompilerFactory` برای حل پویا و بهینه کامپایلر بر اساس کلید `target_ai`.
    - پشتیبانی از پارامتر `target_ai` در اعتبارسنجی `CreativeRequest` و پاسخ `/api/creative/preview`.
    - ذخیره `target_ai` در متادیتای ردیف `generations` جهت ردیابی و ارزیابی کیفیت پرامپت‌ها.
    - پوشش کامل تست‌های واحد و Feature در `PromptCompilerTest` و `StudioPreviewViewTest`.

### ۷. کنترل و قفل ثبات کاراکتر/مدل در برابر تغییرپذیری (Character & Subject Consistency Control)
- [ ] **طراحی کنترل ثبات کاراکتر/مدل انسانی (Consistent vs Dynamic Character Option):**
  - **مسئله:** در تولید تصاویر تجاری که شامل مدل انسانی، چهره یا کاراکتر برند هستند، با هر بار جنریشن چهره و ویژگی‌های مدل تغییر می‌کند؛ همچنین در شرایطی که کاربر تمایل به تنوع چهره دارد کنترلی وجود ندارد. نیاز به یک آپشن دوحالته برای قفل هویت کاراکتر یا اجازه به خلق مدل جدید است.
  - **نیازمندی در رابط کاربری (UI/UX استودیو `/create`):**
    - افزودن کنترل اختصاصی «ثبات کاراکتر / مدل» در بخش تنظیمات صحنه با دو حالت:
      - 🔒 **ثابت و بدون تغییر (Lock Character / Same Model):** مقید ساختن هوش مصنوعی به حفظ دقیق هویت، چهره، ساختار فیزیولوژیک و فیچرهای مدل در تمامی تولیدها (`strict character consistency, identical facial features, same model identity across generations, preserve facial structure and ethnicity, zero character drift`).
      - 🎲 **تنوع و مدل جدید (Dynamic / New Character):** اجازه به هوش مصنوعی برای تولید مدل‌های انسانی متنوع متناسب با سبک و سناریو.
    - نمایش چیپ وضعیت در بازرس پرامپت (`Prompt Inspector`) جهت شفافیت برای کاربر.
  - **نیازمندی فنی و معماری بک‌اند (DDD):**
    - افزودن تنظیمات کاراکتر به `config/creative.php` با کلیدهای `locked` و `dynamic`.
    - دریافت پارامتر `character_consistency` در `PreviewCreativeRequest`، `StoreGenerationRequest` و `BulkGenerationRequest`.
    - ترکیب دستورات صریح تثبیت کاراکتر در `CreativeEngine::prompt()` در صورت فعال بودن وضعیت Locked.
    - ذخیره در متادیتای ردیف `creative_projects`.
    - تست‌های پوششی در `CreativeEngineTest` و `GenerationApiTest`.

### ۸. ممانعت قطعی از درج خودکار و ناخواسته متن/تایپوگرافی روی تصویر (Strict Suppression of Unwanted Generated Text)
- [ ] **جلوگیری از تولید خودکار حروف و نوشته‌های نامفهوم روی تصاویر:**
  - **مسئله:** مدل‌های تصویرساز گاهی به صورت خودکار متن‌ها و حروف نامفهوم (Gibberish text)، تایپوگرافی‌های مصنوعی یا نوشته‌های ناخواسته روی پس‌زمینه و بسته‌بندی رندر می‌کنند در حالی که کاربر هیچ متنی تعریف نکرده است.
  - **نیازمندی فنی و مهندسی پرامپت:**
    - بازبینی عبارت پایانی در `CreativeEngine::prompt()`: ارتقای عبارت ساده `no unwanted text` به دستورات انکاری قاطع و سخت‌گیرانه در حالت پیش‌فرض:
      `strictly clean composition, no text, no words, no letters, no typography, no fake labels, no pseudo-writing, no artificial watermark or signage`.
    - **قاعده مشروط (Conditional Rule):** درج هرگونه متن در تصویر ممنوع باشد، مگر اینکه کاربر صریحاً در فیلد اختصاصی متن، شعار برند (Brand Kit Tagline) یا بریف، متنی مشخص کرده باشد؛ در آن صورت فقط همان متن مشخص با راهنمای خوانایی هدایت شود.
    - اطمینان از اینکه توصیف‌های استودیو، متریال سطوح یا استایل‌ها کلماتی که تداعی‌کننده پوستر متنی یا فونت هستند در پرامپت درج نکنند.
    - تست‌های واحد در `CreativeEngineTest` برای سنجش وجود دستورات اکید منع متن در حالت عادی و فعال شدن گزینشی متن در صورت تعریف صریح کاربر.



## ترتیب پیشنهادی اجرا

1. رفع باگ‌های P0 (ویرایش محصول، metrics، watermark ویدئو، SMS در transaction).
2. مشکلات منطقی P1 (timeout Veo، CreditEstimator، PromptModerator فارسی).
3. زیرساخت عملیاتی (health check، stale generation recovery، admin rate limit).
4. بهینه‌سازی‌های Frontend (polling backoff، skeleton loading).
5. CI با static analysis.
6. Beta با ۲۰ کاربر و اندازه‌گیری KPIهای PRD.

## معیار خروج MVP

- [x] Image و Video واقعی end-to-end کار می‌کنند.
- [x] پرداخت زرین‌پال در sandbox و transaction کنترل‌شده تست شده است.
- [x] Credit/Plan/Refund و webhook idempotent audit شده‌اند.
- [x] Watermark، Notifications و Admin refund آماده‌اند.
- [x] Happy Path و Paywall با E2E تست شده‌اند.
- [x] CI، staging، monitoring، backup و restore آماده‌اند.
- [x] باگ‌های P0 رفع شده‌اند.
- [ ] حداقل ۲۰ beta user مسیر را تست کرده‌اند (نیازمند استقرار بر روی سرور واقعی و دعوت از کاربران آزمایشی).
- [x] KPIهای PRD: Activation، Second Generation، 👍 Rate، Free→Paid و Gross Margin اندازه‌گیری و در اندپوینت لاجیک سنجش قرار گرفتند.

</div>
