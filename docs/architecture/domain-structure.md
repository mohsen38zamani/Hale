# معماری Domain در Hale

Hale یک Modular Monolith است. هر Domain منطق و Request و Service مرتبط خود را در `app/Domains` نگه می‌دارد و concerns مشترک در `app/Support` قرار می‌گیرند.

## مرزهای Phase 1
`Auth`, `Users`, `Organizations`, `Products`, `Media`, `Creative`, `Generations`, `AI`, `Credits`, `Billing`, `Notifications`, `Admin`.

AI Providerها نباید به Business Logic نشت کنند، Generation باید async باشد و تغییر Credit فقط از ledger انجام شود.

## Providerهای خارجی پیاده‌سازی‌شده

- `Auth\Contracts\SmsProvider`: قرارداد ارسال OTP؛ `SmsIrProvider` برای `sms.ir` و `FakeSmsProvider` برای تست و توسعه.
- `Billing\Contracts\PaymentGateway`: قرارداد ساخت و Verify پرداخت؛ `ZarinpalPaymentGateway` به‌عنوان driver پیش‌فرض با پشتیبانی sandbox و `FakePaymentGateway` برای تست.
- `AI\Contracts\GenerationProvider`: قرارداد تولید محتوا؛ `GoogleImagenProvider` (تصویر) و `GoogleVeoProvider` (ویدئو) به همراه `FakeGenerationProvider`؛ پشتیبانی از Fallback در `ModelRouter` و کنترل سقف بودجه روزانه با `CircuitBreaker`.
- کلیدها و شناسه‌های Provider فقط از environment/config خوانده می‌شوند و در کد یا commit ذخیره نمی‌شوند.
- Webhook پرداخت Fake با HMAC بررسی می‌شود و callback زرین‌پال با Verify رسمی Authority و مبلغ اعتبارسنجی می‌گردد؛ ثبت Credit و Subscription داخل transaction و با idempotency انجام می‌شود.

## ERD اولیه Foundation
```text
users 1 ─── * personal_access_tokens
users 1 ─── * sessions
users 1 ─── * organizations (owner_id)
```
