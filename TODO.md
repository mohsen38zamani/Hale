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
- [x] تست‌های Backend، Providerها و جریان‌های E2E: ۸۷ تست و ۳۴۹ assertion در Docker با `pdo_sqlite` با موفقیت ۱۰۰٪ سبز هستند (شامل E2E Happy Path، Paywall Checkout، Subscription Expiry و Product Cleanup).

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

- [ ] تکمیل Invoice و Payment History.
  - endpoint تاریخچه پرداخت، receipt متنی و Invoice پایدار با شماره یکتا تکمیل شده‌اند.
  - Pricing اکنون نتیجه paid/failed، تاریخچه pending/paid/failed و receipt احراز‌شده را نمایش می‌دهد؛ pagination history و صفحه نتیجه کامل هنوز لازم است.
  - checkout با `Idempotency-Key` کلاینت idempotent شده است؛ Invoice و UI وضعیت پرداخت هنوز لازم است.
  - fake checkout محلی برای success/failure اضافه شده؛ production gateway و payment result UI هنوز باقی است.
  - queueهای اصلی اکنون `after_commit` دارند؛ تست rollback/queue هنوز لازم است.

- [x] تکمیل Paywall واقعی.
  - خطای 402 اکنون در تمام جریان‌های Builder، Retry و Regenerate به CTA `/pricing` وصل است.
  - endpoint و نمایش Credit estimate قبل از Generate فعال است.
  - low-credit threshold و اعلان خودکار در صورت کاهش اعتبار.

### Frontend مسیر اصلی

- [ ] اتصال Progress/Result به Queue واقعی و تست مرورگر.
  - polling در tab فعال/غیرفعال.
  - loading/empty/error state.
  - retry failed و regenerate completed.
  - نمایش Credit مصرف‌شده/رزروشده و وضعیت دقیق تکمیل شده؛ browser test و polling lifecycle هنوز لازم است.
  - دانلود و preview اکنون با Bearer و Blob کار می‌کنند؛ polling در tab مخفی/visible و browser test هنوز لازم است.
  - retry failed به endpoint متصل شده؛ تست UI و handling خطا هنوز لازم است.

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

- [ ] دسترس‌پذیری و responsive audit.
  - تست ۳۲۰px، tablet و desktop.
  - keyboard navigation، focus state، contrast و labels.
  - حذف placeholderهای صرفاً نمایشی از مسیرهای اصلی.

- [x] PWA پایه.
  - manifest، service worker و offline fallback اضافه شده‌اند؛ install prompt سفارشی و push باقی است.
  - تصمیم دربارهٔ push notification.

## P1: قابلیت‌های لازم برای Launch

### Notifications

- [x] ساخت Notification domain و جدول `notifications`.
- [x] endpoint لیست/خواندن اعلان‌ها.
- [x] اعلان Generation completed/failed و Payment موفق.
- [x] اعلان Credit کم و Welcome در channel database.
- [ ] کانال In-app و Email؛ SMS فقط برای OTP باقی بماند.
  - مرکز In-app در Dashboard و Email برای اعلان‌های اصلی آماده است؛ push هنوز لازم است.
- [ ] تست event، queue، unread/read و failure ارسال.
  - تست unread/read/read-all و انتخاب کانال Email اضافه شده؛ event failure و Email queue integration هنوز لازم است.

### Watermark و Media

- [x] اعمال Watermark واقعی برای تصویر پلن Free در output.
- [ ] عدم Watermark برای Starter/Creator با تست integration.
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

- [ ] rate limit per user/plan برای Auth، Generate، SMS و Payment.
  - limiterهای Generate، Checkout و Payment history اضافه شده‌اند؛ plan-aware و endpointهای Auth/SMS هنوز باقی است.
- [ ] anti-fraud پایه: phone/IP/device limits و جلوگیری از چند bonus.
- [ ] request ID، structured logging و حذف secret از log.
  - `X-Request-ID` و context لاگ اضافه شده؛ structured logging و audit کامل هنوز باقی است.
- [ ] Sentry یا جایگزین error tracking.
- [ ] Horizon/worker production configuration و failed-job alert.
- [ ] backup روزانه MySQL و restore drill.
- [ ] security review برای upload، webhook، authorization و Storage paths.
  - OTP خام از log حذف شده و تغییر phone در failure Provider rollback می‌شود؛ تست امنیتی آن لازم است.
  - نتیجه Storage بررسی می‌شود؛ integrity و failure integration test هنوز لازم است.
- [ ] جلوگیری از سوءاستفاده و replay در عملیات مالی و generation.
  - retry/regenerate و callback/webhook محدودیت عملیاتی و idempotency کلاینتی کامل ندارند.
  - معیار پایان: تست هم‌زمانی، double-click، replay callback و rate limit per-user/IP سبز باشد.
- [ ] performance audit: p95 API کمتر از ۵۰۰ms و query/index review.

## P1: تست و انتشار

- [x] E2E: Register/Login → Product Upload → Builder → Generate → Progress → Result → Download (`HappyPathFlowTest`).
- [x] E2E: Paywall → Pricing → Checkout → callback → Credit/Plan (`PaywallCheckoutFlowTest`).
- [x] E2E: Phone OTP → Free Credit فقط یک‌بار (`AuthApiTest` و `HappyPathFlowTest`).
- [ ] browser test روی RTL و viewport ۳۲۰px.
- [ ] تست Provider واقعی در sandbox برای SMS.ir و زرین‌پال.
- [x] تست‌های قراردادی و regression برای شکاف‌های ممیزی.
  - subscription expiry، race limit، checkout idempotency، storage failure، retry API، notification queue و download احراز‌شده پوشش داده شدند (۸۷ تست، ۳۴۹ assertion).
  - معیار پایان: بازشماری test/assertion و coverage threshold در CI ثبت شود.
- [ ] CI شامل PHPUnit، `npm run build`، lint و migration test.
  - `npm ci`، `npm run build` و `migrate:fresh` به workflow اضافه شده‌اند؛ E2E/integration و lint JavaScript هنوز باقی است.
  - browser/E2E، lint JavaScript و integration با MySQL/Redis/S3 هنوز در CI نیست.
- [ ] staging با secrets واقعیِ staging، queue worker و HTTPS.
- [ ] deployment/runbook و API documentation نهایی.
- [ ] تست backup/restore و smoke test production.

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

- [ ] Image و Video واقعی end-to-end کار می‌کنند.
- [ ] پرداخت زرین‌پال در sandbox و transaction کنترل‌شده تست شده است.
- [ ] Credit/Plan/Refund و webhook idempotent audit شده‌اند.
- [ ] Watermark، Notifications و Admin refund آماده‌اند.
- [ ] Happy Path و Paywall با E2E تست شده‌اند.
- [ ] CI، staging، monitoring، backup و restore آماده‌اند.
- [ ] حداقل ۲۰ beta user مسیر را تست کرده‌اند.
- [ ] KPIهای PRD: Activation، Second Generation، 👍 Rate، Free→Paid و Gross Margin اندازه‌گیری شده‌اند.
