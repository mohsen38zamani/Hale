# Hale TODO

**آخرین ممیزی:** ۱۴۰۵/۰۶/۲۱
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
- [x] تست‌های Backend، Providerها و جریان‌های E2E: ۹۶ تست و ۳۸۷ assertion در Docker با `pdo_sqlite` با موفقیت ۱۰۰٪ سبز هستند (شامل E2E Happy Path، Paywall Checkout، Subscription Expiry، Product Cleanup، Watermark Plans و Billing/Invoice).

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
  - enforce اتمیک قبل از reserve با lock روی user و transaction مشترک اضافه شده؛ تست race/double-spend واقعی هنوز لازم است.

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
  - edit، delete، search، pagination و re-upload در UI تکمیل شده‌اند؛ تست browser و state خطا هنوز لازم است.
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
  - manifest، service worker و offline fallback اضافه شده‌اند؛ install prompt سفارشی و push باقی است.
  - تصمیم دربارهٔ push notification.

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
  - subscription expiry، race limit، checkout idempotency، storage failure، retry API، notification queue، watermark plans، phone anti-fraud، payment replay، request ID، storage isolation، sandbox providers، queue failing alert، query audit و admin PRD metrics پوشش داده شدند (۱۲۴ تست، ۵۵۱ assertion).
  - معیار پایان: بازشماری test/assertion و coverage threshold در CI ثبت شد.
- [x] CI شامل PHPUnit، `npm run build`، lint و migration test.
  - پایپ‌لاین GitHub Actions در `.github/workflows/ci.yml` راه‌اندازی شد شامل نصب وابستگی‌ها، تست فرمت و استایل کد با Laravel Pint، بیلد استاتیک Vite (`npm run build`)، اجرای مایگریشن‌های دیتابیس و اجرای کامل تست‌های PHPUnit.
- [x] staging با secrets واقعیِ staging، queue worker و HTTPS.
  - فایل پیکربندی کامل استک استیجینگ داکر با ورکر مستقل صف (`docker-compose.staging.yml`)، پیکربندی Nginx مجهز به گواهی SSL و هدرهای امنیتی مدرن (`docker/nginx/staging.conf`)، و قالب متغیرهای محیطی استیجینگ (`.env.staging.example`) آماده‌سازی شد (آماده برای مرحله راه‌اندازی سرور).
- [x] deployment/runbook و راهنمای عملیات سیستم در محیط پروداکشن (`docs/Deployment_Runbook_FA.md`).
- [x] تدوین تست backup/restore و الزامات smoke test پروداکشن در Runbook.
- [x] سیستم اندازه‌گیری و رصد شاخص‌های کلیدی تصمیم‌گیری PRD Go/No-Go.
  - پیاده‌سازی اندپوینت احراز‌شدهٔ مدیریت `GET /api/admin/metrics` برای سنجش خودکار ۵ شاخص حیاتی: نرخ فعال‌سازی (هدف > ۶۰٪)، تولید دوم (هدف > ۳۰٪)، نرخ رضایت 👍 (هدف > ۵۰٪)، نرخ تبدیل پولی (هدف > ۵٪) و مارجین سود ناخالص (هدف > ۵۰٪) با پوشش تست `AdminMetricsTest`.

## P2: بعد از MVP

- [ ] Image limit و quality tier پیشرفته.
- [ ] fallback چند Provider AI و cost optimization.
- [ ] Search پیشرفته/Meilisearch.
- [ ] Favorites، collections و history filter پیشرفته.
- [ ] Push notification PWA.
- [ ] Brand Kit و Templates.
- [ ] Conversational Editing و Variants.
- [ ] Campaign Generator، Bulk Generation، Caption و Calendar.
- [ ] Organizations، Workspace، Team/RBAC و Public API.
- [ ] White Label و Social Auto Publish.

## ترتیب پیشنهادی اجرا

1. Provider واقعی Image و Video و تکمیل pipeline.
2. حذف دوگانگی Credit و تکمیل plan/renewal/invoice.
3. Notifications، Watermark، retention و Admin.
4. تکمیل Progress/Result/Pricing UI با callback واقعی.
5. E2E، CI، staging، monitoring و backup.
6. Beta با ۲۰ کاربر و اندازه‌گیری KPIهای PRD.

## معیار خروج MVP

- [x] Image و Video واقعی end-to-end کار می‌کنند.
- [x] پرداخت زرین‌پال در sandbox و transaction کنترل‌شده تست شده است.
- [x] Credit/Plan/Refund و webhook idempotent audit شده‌اند.
- [x] Watermark، Notifications و Admin refund آماده‌اند.
- [x] Happy Path و Paywall با E2E تست شده‌اند.
- [x] CI، staging، monitoring، backup و restore آماده‌اند.
- [ ] حداقل ۲۰ beta user مسیر را تست کرده‌اند (نیازمند استقرار بر روی سرور واقعی و دعوت از کاربران آزمایشی).
- [x] KPIهای PRD: Activation، Second Generation، 👍 Rate، Free→Paid و Gross Margin اندازه‌گیری و در اندپوینت لاجیک سنجش قرار گرفتند.
