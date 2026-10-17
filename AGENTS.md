# AGENTS.md — راهنمای همکاری با پروژهٔ حله (Hale)

> این فایل مرجع «حالت همکاری» است: قواعد کاری، وضعیت فعلی، نکات فنی چسبناک و تسک‌های باز.
> قبل از هر کاری این فایل را بخوان؛ بعد `git log --oneline -10` و `git status` را چک کن.

## ۱. هویت پروژه

- مسیر: `/var/www/html/Hale'` (نام دایرکتوری آپاستروف دارد؛ در دستورها حتماً quote بزنید)
- محصول: **حله** — پلتفرم SaaS ایرانی تولید محتوای تبلیغاتی محصول با هوش مصنوعی (عکس/ویدئو/ویرایش/کپشن/تقویم محتوا)
- ستک: Laravel 12 + PHP 8.3 (Docker، سرویس `app`)، معماری **DDD** در `app/Domains/*`
  (Auth, Products, Generations, Creative, Editing, Media, Billing, Calendar, Notifications, Search, Security, Templates, Brand)
- فرانت: **Blade + `resources/js/app.js` + `resources/css/app.css` با Vite — بدون SPA و بدون فریم‌ورک JS**
- سرویس‌ها: MySQL، Redis، MinIO (دیسک `s3`)، Meilisearch (اختیاری)
- سایت dev: `http://localhost:8080` — ریموت: `github.com/mohsen38zamani/Hale`

## ۲. دستورها (همه داخل داکر)

```bash
docker compose run --rm app php artisan test                      # suite کامل
docker compose run --rm app php artisan test --filter=NameTest    # تست تکی
docker compose run --rm app ./vendor/bin/pint --test              # استایل
docker compose run --rm app ./vendor/bin/phpstan analyse --no-progress   # استاتیک
npm run build                                                     # بیلد فرانت (روی هاست، نه داکر)
docker compose run --rm app php artisan migrate --force           # مایگریشن
```

## ۳. قواعد همکاری (اینها را نشکن)

1. **اول پلن می‌نویسی ← بعد منتظر «تأیید صریح» کاربر می‌مانی ← بعد اجرا می‌کنی.** بدون تأیید، حتی یک خط تغییر نده.
2. کار را به **بچ‌های کوچک با کامیت مستقل** تقسیم کن؛ هر بچ فقط با سبز شدن **هر ۴ چک**
   (test + pint + phpstan + build) و کامیت، بسته می‌شود؛ بعد گزارش کن.
3. **حذف کاربر (GDPR) به‌طور کامل رد شده — هرگز پیشنهادش نده.**
4. انحراف از پلن را هم به کاربر گزارش کن و هم داخل `TODO.md` (زیر همان آیتم) ثبت کن.
5. **یک سیشن موازی دیگر روی همین ریپو کار می‌کند** → قبل از `git add` حتماً `git status`
   و `git log --oneline -5` را چک کن؛ هرگز hash کامیت قبلی را hardcode نکن.
6. **push کردن فقط با درخواست صریح کاربر.**
7. سبز بودنِ محلی هیچ‌وقت جای سبز بودن CI گیت‌هاب را نمی‌گیرد (بخش ۷ را بخوان).

## ۴. وضعیت فعلی (آخرین به‌روزرسانی: ۲۰۲۶-۱۰-۱۷)

- HEAD: `3ef7d6a` — درخت **تمیز**
- **۳ کامیت push‌نشده:** `3ef7d6a`، `9416238`، `a74349b` (تا وقتی کاربر نگوید push نکن)
- suite: **۵۱۴ تست / ۴۹۸۸ assertion** سبز، Pint سبز، PHPStan بدون خطا، build سبز
- آخرین اجرای CI گیت‌هاب (run #14 روی `0656656`) **شکست خورده** — بخش ۷

### چه چیزهایی تا الان تحویل شده (خلاصه)
- هستهٔ MVP: auth (ایمیل/موبایل + OTP)، محصولات و آپلود، نسل تصویر/ویدئو با صف و retry،
  کیف پول اعتباری، اشتراک و پرداخت زرین‌پال (webhook امضاشده و idempotent)، نوتیفیکیشن،
  admin و بازپرداخت، watermark، جستجو، تقویم محتوا و کمپین
- **AI Utility Tools** (حذف پس‌زمینه، سایه، بزرگ‌نمایی، Expand) با دامنهٔ `Editing`
- **ویرایش مکالمه‌ای** (`EditThread`) با زنجیرهٔ edit، takeهای متعدد (Variants) و سقف `AI_MAX_EDIT_CHAIN=8`
- **۱۲+ کامپایلر پرامپت** در `app/Domains/Creative/Prompts/*` + `PromptCompilerFactory`
  + سلکتور «هوش مصنوعی هدف» (`target_ai`) در استودیو
- پشتیبانی **عکس محصول به‌عنوان reference** برای مدل‌های بینایی، حالت ویدئوی مرجع‌دار،
  ترجمهٔ فارسیِ استودیو به انگلیسی برای مدل‌ها، راستی‌آزمایی واقعی providerها
- نماهای دوربین بازنویسی‌شده (فرونت‌لود `Camera: ...`) + پیش‌نمایش زندهٔ زاویه در `/create`

## ۵. نکات فنی چسبناک (قبل از دست‌زدن بخوان)

- **`config/creative.php` منبع واحد گزینه‌های استودیو است:** `/api/creative/options`، چیپ‌های UI،
  `Rule::in` در validation و تمپلیت‌ها همه از همان config تغذیه می‌شوند → افزودن/تغییر آیتم
  در config خودکار در همه‌جا اعمال می‌شود.
- **ساخت پرامپت فقط سمت سرور است** (`CreativeEngine` + کامپایلرها). بازرس پرامپت در استودیو
  متن واقعی را از `POST /api/creative/preview` (`prompt_preview`) می‌گیرد و بین پاسخ‌ها
  یک آینهٔ فوری در `resources/js/app.js` دارد؛ اگر پرامپت را عوض کردی، **باید آینهٔ JS را هم
  چک کنی** (تست قرارداد در `StudioPreviewViewTest` وجود دارد).
- تست‌های `CreativeEngineTest`، `GenerationApiTest` و کامپایلرها روی **متن پرامپت** assert دارند؛
  تغییر متن پرامپت = باید همان تست‌ها هم‌زمان اصلاح شوند (وگرنه suite قرمز می‌شود).
- ذخیره‌سازی: `MEDIA_DISK=s3` (endpoint مینیو در داکر)؛ هر تستی که فایل می‌نویسد باید
  `Storage::fake('s3')` بزند؛ **تست‌ها نسبت به شبکه غیر-isolatedند** (بخش ۷).
- `config/ai.php`: درایور پیش‌فرض `AI_DRIVER=local` (تست‌ها fake)، سقف بودجهٔ روزانه،
  قیمت‌گذاری رزرو، لیست کلمات ممنوعهٔ `moderation`، `max_edit_chain=8`.
- صف: در تست `QUEUE_CONNECTION=sync`؛ `throttle:generation` پنج درخواست در دقیقه؛
  هزینه‌ها: تصویر standard=10 / premium=25، متن=۳، ابزارهای ویرایش ۵/۵/۱۰/۵-۱۰-۱۵.
- ثبت‌نام با `INITIAL_CREDIT_BALANCE=30`؛ بازگشت اعتبار با کلید idempotent `refund` در متادیتا.
- CSS پروژه `[hidden] { display: none !important }` دارد (پنهان‌سازی با `hidden` قابل اتکاست).

## ۶. مستندات مرجع

- `TODO.md` — **فقط منبع حقیقت تسک‌ها**؛ هر آیتم با تست و کامیت مستقل بسته می‌شود
- `docs/Product_Creative_Studio_Specification_FA.md` — مشخصات استودیوی تخصصی اصناف (آیتم ۱۲)
- `docs/AI_Content_Studio_Business_Plan_FA.md` + `docs/AI_Content_Studio_Review_PM_CTO_Sales_FA.md`
  — مدل درآمدی/اعتباری دو مسیره (آیتم ۱۳)
- `docs/MVP_Specification_FA.md`، `docs/Development_Roadmap_FA.md`، `docs/Deployment_Runbook_FA.md`
- `docs/architecture/` — ساختار دامنه‌ها

## ۷. مشکل شناخته‌شدهٔ CI (کاربر فعلاً گفت بیخیالش شو)

- `.github/workflows/ci.yml` نسخه‌ها را به PHP 8.3 / Node 22 برده و Composer/Pint/PHPStan/build
  در CI سبز است، ولی **مرحلهٔ test در گیت‌هاب می‌شکند** (آخرین بار run #14، exit code 2).
- ریشه‌یابی انجام‌شده: با env دقیق CI و `AWS_ENDPOINT` غیرقابل‌دسترس، **۱۰ تست می‌شکنند**
  (EmailVerification، BrandKit، CampaignPromptPack، GenerationApi) چون خروجی generation
  روی دیسک واقعی `s3` می‌نویسد؛ یعنی تست‌ها نسبت به دیسک isolation ندارند.
- خروجی واقعی CI با API در دسترس نبود (توکن داخل `~/.git-credentials` معتبر نیست).
- **رفتار مورد انتظار:** اگر کاربر دوباره CI را خواست، اول تست‌ها را hermetic کن
  (fake کردن سراسری دیسک‌ها در `tests/TestCase`)، نه تغییر env در ورک‌فلو.

## ۸. تسک‌های باز (شماره = خط در `TODO.md`)

| خط | آیتم | وزن |
|----|------|-----|
| ۳۴۷ / ۶۵۵ | **استودیوی تخصصی تولید محتوای محصول** (۱۰ صنف) با معماری دوگانهٔ «کپی پرامپت خودکفا + ساخت مستقیم داخلی» — طبق `docs/Product_Creative_Studio_Specification_FA.md` | خیلی سنگین، چندبچ |
| ۶۸۳ | **زیرساخت مالی و مدل درآمدی دو مسیرهٔ اعتباری** — طبق بیزینس پلن و PRD | سنگین |
| ۳۴۸ | Organizations، Workspace، Team/RBAC و Public API | سنگین |
| ۳۴۹ | White Label و Social Auto Publish | سنگین |
| ۷۳۰–۷۳۲ | معیارهای خروج: پیاده‌سازی بندهای ۱۲ و ۱۳ + **حداقل ۲۰ کاربر beta** (این یکی **blocked** است: نیازمند استقرار روی سرور واقعی) | عملیاتی |

بخش‌های بازتر P0/P1 و آیتم‌های ۱ تا ۱۱ باک‌لاگ بازطراحی **همه بسته شده‌اند** — دوباره بازشان نکن.

## ۹. شروع کار در سیشن جدید

1. `git log --oneline -10` و `git status` را چک کن و وضعیت (به‌ویژه کامیت‌های push‌نشده) را به کاربر گزارش کن.
2. جدول بخش ۸ را به کاربر بده و بخواه یکی را انتخاب کند.
3. بعد از انتخاب، **پلن بنویس** (بچ‌ها، فایل‌ها، تست‌ها، ریسک‌ها) و منتظر تأیید صریح بمان.
