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
- [x] تست‌های Backend و Providerها: آخرین وضعیت ثبت‌شده ۴۷ تست و ۱۵۸ assertion

## P0: تکمیل مسیر واقعی MVP

### AI و Generation واقعی

- [ ] اتصال حداقل یک Image Provider واقعی پشت `GenerationProvider`/`AiGateway`.
  - تنظیم secret فقط از env/config.
  - ثبت provider/model/cost/processing time واقعی.
  - تست sandbox و تست خطای timeout/429/5xx.
  - معیار پایان: یک تصویر واقعی از API تا Storage و Download end-to-end ثبت شود.

- [ ] اتصال Video/Image-to-Video Provider واقعی.
  - تضمین خروجی `video/mp4` واقعی، نه PNG fake.
  - بررسی durationهای ۵، ۸ و ۱۰ ثانیه و MIME/size metadata.
  - پشتیبانی polling یا webhook Provider.
  - معیار پایان: یک Reel واقعی در کمتر از SLA مستند تولید و دانلود شود.

- [ ] تکمیل `ModelRouter` برای quality، plan، cost و duration support.
  - fallback Provider و circuit breaker.
  - خطایابی قابل‌مشاهده بدون نشت جزئیات Provider به business logic.

- [ ] تکمیل Creative Brief/Prompt با LLM واقعی.
  - ثبت brief/prompt برای debug.
  - Auto Best بر اساس محصول/هدف، نه فقط default ثابت.
  - moderation قوی‌تر برای prompt و خروجی.

- [ ] اصلاح pipeline Provider.
  - status دقیق `queued → processing → completed/failed`.
  - webhook idempotency یا polling امن.
  - dead-letter handling و alert برای failure نهایی.
  - محدودیت retry طبق PRD و تست integration.

### Billing و Credit مالی

- [ ] جایگزینی Fake Gateway در محیط production با Verify واقعی زرین‌پال.
  - ثبت Merchant ID و callback عمومی HTTPS.
  - تست sandbox و یک transaction واقعی کنترل‌شده.
  - بررسی مبلغ ریالی/واحد پول، duplicate callback و code 101.

- [ ] تکمیل Subscription lifecycle.
  - انقضای خودکار `ends_at` و برگشت به `free`.
  - renewal ماهانه یا تصمیم صریح دربارهٔ عدم پشتیبانی renewal.
  - جلوگیری از فعال‌شدن plan منقضی.

- [ ] تکمیل محدودیت‌های Plan.
  - image limit و video limit ماهانه.
  - تفکیک Credit خریداری‌شده و Credit رایگان در صورت نیاز محصول.
  - enforce قبل از reserve و تست race/double-spend.

- [ ] حذف دوگانگی منبع Credit/Plan.
  - `credit_accounts.balance` و ledger منبع اصلی بمانند.
  - `users.credits_balance` یا حذف شود یا با migration/service به‌صورت رسمی sync شود.
  - profile/dashboard نباید مقدار stale از `users.credits_balance` نمایش دهد.

- [ ] تکمیل Invoice و Payment History.
  - مدل/endpoint تاریخچه پرداخت.
  - receipt قابل دانلود یا شناسه پیگیری.
  - نمایش وضعیت pending/paid/failed در UI.

- [ ] تکمیل Paywall واقعی.
  - خطای 402 به CTA `/pricing` وصل شود.
  - نمایش Credit estimate قبل از Generate.
  - Low-credit threshold و پیام قابل‌فهم.

### Frontend مسیر اصلی

- [ ] اتصال Progress/Result به Queue واقعی و تست مرورگر.
  - polling در tab فعال/غیرفعال.
  - loading/empty/error state.
  - retry failed و regenerate completed.
  - نمایش Credit مصرف‌شده و وضعیت دقیق.

- [ ] تکمیل Product Library.
  - نمایش thumbnail واقعی از Storage به‌جای placeholder.
  - edit، delete، search، pagination و re-upload.
  - confirmation و state خطا برای حذف.

- [ ] تکمیل Creative Builder.
  - Auto Best preview واقعی.
  - نمایش cost/credit قبل از Generate.
  - جلوگیری UI از ارسال duration برای image.
  - حفظ فرم در خطای validation/API.

- [ ] تکمیل Pricing/Checkout.
  - نمایش callback موفق/ناموفق زرین‌پال.
  - صفحه Payment Result و refresh موجودی.
  - جلوگیری از checkout برای کاربر unauthenticated با پیام مناسب.

- [ ] دسترس‌پذیری و responsive audit.
  - تست ۳۲۰px، tablet و desktop.
  - keyboard navigation، focus state، contrast و labels.
  - حذف placeholderهای صرفاً نمایشی از مسیرهای اصلی.

- [ ] PWA واقعی.
  - manifest، service worker، install prompt و offline fallback.
  - تصمیم دربارهٔ push notification.

## P1: قابلیت‌های لازم برای Launch

### Notifications

- [ ] ساخت Notification domain و جدول `notifications`.
- [ ] endpoint لیست/خواندن اعلان‌ها.
- [ ] اعلان Generation completed/failed.
- [ ] اعلان Credit کم، Payment موفق و Welcome.
- [ ] کانال In-app و Email؛ SMS فقط برای OTP باقی بماند.
- [ ] تست event، queue، unread/read و failure ارسال.

### Watermark و Media

- [ ] اعمال Watermark واقعی فقط برای Free در preview/output.
- [ ] عدم Watermark برای Starter/Creator با تست.
- [ ] metadata و MIME صحیح برای هر خروجی.
- [ ] retention ۹۰ روزه و cleanup فایل‌های Storage.
- [ ] job زمان‌بندی‌شده برای پاک‌سازی generation/media قدیمی.

### Admin و عملیات

- [ ] Admin domain و authorization متمرکز.
- [ ] Users list و User detail.
- [ ] Generation/queue monitor و مشاهده خطا.
- [ ] Refund Credit دستی با audit trail.
- [ ] Payment/Subscription support view.
- [ ] تست عدم دسترسی کاربر عادی به Admin.

### امنیت و پایداری

- [ ] rate limit per user/plan برای Auth، Generate، SMS و Payment.
- [ ] anti-fraud پایه: phone/IP/device limits و جلوگیری از چند bonus.
- [ ] request ID، structured logging و حذف secret از log.
- [ ] Sentry یا جایگزین error tracking.
- [ ] Horizon/worker production configuration و failed-job alert.
- [ ] backup روزانه MySQL و restore drill.
- [ ] security review برای upload، webhook، authorization و Storage paths.
- [ ] performance audit: p95 API کمتر از ۵۰۰ms و query/index review.

## P1: تست و انتشار

- [ ] E2E: Register/Login → Product Upload → Builder → Generate → Progress → Result → Download.
- [ ] E2E: Paywall → Pricing → Checkout → callback → Credit/Plan.
- [ ] E2E: Phone OTP → Free Credit فقط یک‌بار.
- [ ] browser test روی RTL و viewport ۳۲۰px.
- [ ] تست Provider واقعی در sandbox برای SMS.ir و زرین‌پال.
- [ ] CI شامل PHPUnit، `npm run build`، lint و migration test.
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
