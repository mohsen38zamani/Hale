# معماری Domain در Hale

Hale یک Modular Monolith است. هر Domain منطق و Request و Service مرتبط خود را در `app/Domains` نگه می‌دارد و concerns مشترک در `app/Support` قرار می‌گیرند.

## مرزهای فعلی Domainها
`Auth`, `Products`, `Media`, `Creative`, `Generations`, `AI`, `Credits`, `Billing`, `Notifications`, `Search`, `Favorites`, `Brand`, `Templates`, `Calendar`, `Admin`.

AI Providerها نباید به Business Logic نشت کنند، Generation باید async باشد و تغییر Credit فقط از ledger انجام شود.

## Providerهای خارجی پیاده‌سازی‌شده

- `Auth\Contracts\SmsProvider`: قرارداد ارسال OTP؛ `SmsIrProvider` برای `sms.ir` و `FakeSmsProvider` برای تست و توسعه.
- `Billing\Contracts\PaymentGateway`: قرارداد ساخت و Verify پرداخت؛ `ZarinpalPaymentGateway` به‌عنوان driver پیش‌فرض با پشتیبانی sandbox و `FakePaymentGateway` برای تست.
- `AI\Contracts\GenerationProvider`: قرارداد تولید محتوا؛ `GoogleImagenProvider` (تصویر) و `GoogleVeoProvider` (ویدئو) به همراه `FakeGenerationProvider`؛ پشتیبانی از زنجیرهٔ Fallback در `ModelRouter`/`AiGateway` (با `AI_PROVIDER_PRIORITY`) و کنترل سقف بودجه روزانه با `CircuitBreaker`.
- `CaptionService` (شاخهٔ متنی AI): تولید کپشن با Gemini از همان کلید گوگل (`GOOGLE_TEXT_MODEL`) و fallback قطعی قالب‌محور وقتی کلید یا سرویس در دسترس نیست.
- `Search\Services\SearchService` با قرارداد درایور: `database` (پیش‌فرض، LIKE امن) و `meilisearch` (بدون SDK، با fallback خودکار به database و هوک ایندکس روی CRUD).
- Web Push: قرارداد `ShouldWebPush` روی نوتیفیکیشن‌های کلیدی + ارسال با `minishlink/web-push` و کلیدهای VAPID در `WEBPUSH_*`؛ اشتراک‌های خطای 404/410 حذف می‌شوند.
- `Media\Services\ImageOptimizer`: ساخت واریانت WebP سایز-صفحه (`MEDIA_WEB_*`) و اعمال سقف ابعاد کیفیت استاندارد/پریمیوم (`MEDIA_STANDARD/PREMIUM_MAX_DIMENSION`) با GD.
- کلیدها و شناسه‌های Provider فقط از environment/config خوانده می‌شوند و در کد یا commit ذخیره نمی‌شوند.
- Webhook پرداخت Fake با HMAC بررسی می‌شود و callback زرین‌پال با Verify رسمی Authority و مبلغ اعتبارسنجی می‌گردد؛ ثبت Credit و Subscription داخل transaction و با idempotency انجام می‌شود؛ خرید بسته‌های top-up فقط `grantPurchase` را با کلید `payment:{id}` اجرا می‌کند.

## ERD اولیه Foundation
```text
users 1 ─── * personal_access_tokens
users 1 ─── * sessions
users 1 ─── * organizations (owner_id)
```

جدول‌های افزوده‌شده در طول توسعه: `push_subscriptions`، `brand_kits`، `generation_templates`، `favorites`، `campaigns` و `scheduled_posts` به همراه migrationهای کیفیت، واریانت وب و مسدودسازی کاربر.
