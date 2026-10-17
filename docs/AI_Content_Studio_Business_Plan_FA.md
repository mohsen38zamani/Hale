<div dir="rtl">

# بیزینس پلن پلتفرم هوشمند تولید محتوای تبلیغاتی با هوش مصنوعی

**AI Content Studio / AI Marketing Content Platform**

> نسخه پیشنهادی از ایده اولیه تا MVP، درآمدزایی و مقیاس‌پذیری

---

## فهرست مطالب

1. خلاصه اجرایی
2. مسئله بازار
3. راه‌حل پیشنهادی
4. ارزش پیشنهادی
5. مشتری هدف
6. تجربه کاربری
7. قابلیت‌های اصلی محصول
8. MVP
9. نقشه راه محصول
10. مدل درآمدی
11. اقتصاد واحد محصول
12. معماری فنی
13. معماری AI Provider و Model Router
14. مدل داده اولیه
15. مزیت رقابتی
16. استراتژی فروش
17. جذب اولین مشتریان
18. قیف فروش
19. ریسک‌های اصلی
20. KPIها
21. معیار Go / No-Go
22. چشم‌انداز نهایی
23. جمع‌بندی مدیریتی
24. برنامه اجرایی پیشنهادی

---

# 1. خلاصه اجرایی

ایده اصلی، ساخت یک **پلتفرم SaaS برای تولید محتوای تبلیغاتی با هوش مصنوعی** است که پیچیدگی ابزارهای مختلف AI و Prompt Engineering را از کاربر پنهان کند.

کاربر به جای اینکه:

- در سایت‌های مختلف جست‌وجو کند؛
- مدل‌های مختلف AI را بشناسد؛
- Prompt بنویسد؛
- تنظیمات فنی انجام دهد؛
- بین ابزارهای تصویر و ویدئو جابه‌جا شود؛
- خروجی‌ها را با ابزار دیگری ویرایش کند؛

فقط ویژگی‌های موردنظر خود را به صورت بصری انتخاب می‌کند.

مثلاً:

```text
محصول: عطر
هدف: افزایش فروش
سبک: لوکس
فضا: استودیو تاریک
فرمت: Instagram Reel
حرکت دوربین: Cinematic
```

سپس سیستم بر اساس نیاز کاربر، یکی از دو مسیر اصلی را در اختیار او قرار می‌دهد:

```text
                    Product
                       │
              ┌────────┴────────┐
              │                 │
       AI ما را استفاده کن    فقط Prompt بگیر
              │                 │
           Credits           Credits
              │                 │
       Image / Video       Professional Prompt
```

یعنی محصول در فاز فعلی دو انتخاب اصلی و رسمی دارد:
> 🎨 **تولید محتوا با AI ما** (تأمین هوش مصنوعی توسط پلتفرم و تحویل فایل آماده عکس/ویدیو)
> یا
> ✍️ **فقط پرامپت حرفه‌ای می‌خواهم** (تأمین هوش مصنوعی توسط کاربر و فروش دانش مهندسی پرامپت و سناریو توسط پلتفرم)

هر دو مسیر کاملاً درآمدی هستند و هر دو از زیرساخت مشترک **Credit** استفاده می‌کنند.

## نکته استراتژیک اصلی

محصول صرفاً یک:
> «سایت ساخت عکس با AI» (AI Image Generator)
نیست. ارزش بنیادین محصول عبارت است از:
> **Creative Intelligence + Product Understanding + Prompt Engineering + Marketing Workflow**

به همین دلیل حتی کاربری که نمی‌خواهد از AI متصل به پلتفرم استفاده کند و اشتراک شخصی Midjourney، ChatGPT یا Runway دارد، همچنان مشتری دست‌به‌نقد و مخاطب هدف پلتفرم است. هدف پلتفرم ارائه دستیار بازاریابی و استودیوی تولید محتوای تبلیغاتی برای کسب‌وکارهاست.

---

# 2. مسئله بازار

امروز ابزارهای تولید تصویر و ویدئو با هوش مصنوعی بسیار زیاد شده‌اند، اما زیاد بودن ابزارها الزاماً تجربه کاربری را بهتر نکرده است.

کاربر معمولی ممکن است با مفاهیمی مثل:

- Prompt
- Negative Prompt
- Seed
- Image-to-Image
- Text-to-Video
- Image-to-Video
- Model
- Style
- Reference Image
- Aspect Ratio
- Camera Movement

مواجه شود.

این پیچیدگی برای یک فروشنده، صاحب فروشگاه یا صاحب کسب‌وکار ارزش خاصی ندارد.

او نمی‌خواهد متخصص AI شود.

او می‌خواهد:

> **برای محصولم محتوای خوب و قابل استفاده بساز.**

بنابراین مشکل واقعی فقط Prompt Engineering نیست.

## مشکل واقعی

مشتری معمولاً نمی‌داند:

1. چه نوع محتوایی برای محصولش بسازد؛
2. چه سبک تصویری مناسب محصول است؛
3. چه سناریویی برای ویدئو مناسب است؛
4. چه فرمتی برای Instagram بهتر است؛
5. چگونه چند محتوای متفاوت اما هماهنگ تولید کند؛
6. چگونه هویت بصری برند خود را حفظ کند.

بنابراین محصول باید **تصمیم‌گیری خلاقانه + عملیات فنی AI** را ساده کند.

---

# 3. راه‌حل پیشنهادی

راه‌حل، یک تجربه یکپارچه برای تولید محتوا است.

## ورودی

کاربر:

- محصول را آپلود می‌کند؛
- هدف را انتخاب می‌کند؛
- سبک را انتخاب می‌کند؛
- فضای تصویر را انتخاب می‌کند؛
- فرمت را انتخاب می‌کند؛
- در صورت نیاز جزئیات ویدئو را مشخص می‌کند.

## پردازش و خروجی (دو مسیر مستقل)

سیستم پس از دریافت ورودی، پردازش اولیه را انجام می‌دهد:
1. تصویر محصول را تحلیل و مشخصات فیزیکی آن را استخراج می‌کند؛
2. صنف کالا و ویژگی‌های محافظت‌شده را تعیین می‌کند؛
3. هدف بازاریابی و لحن برند را تحلیل می‌کند؛
4. Creative Brief ساختاریافته تولید می‌کند؛
5. پرامپت مهندسی‌شده و کامپایل‌شده می‌سازد؛

و سپس کاربر بر اساس نیاز خود یکی از دو مسیر خروجی را انتخاب می‌کند:

### مسیر اول: 🎨 تولید محتوا با AI ما
- مدل مناسب توسط Model Router انتخاب و درخواست به سرورهای AI ارسال می‌شود؛
- تصویر یا ویدیو تولید، بهینه‌سازی و ذخیره می‌گردد؛
- امکان اصلاح، ادیت محاوره‌ای و تولید واریانت فراهم می‌شود؛
- **خروجی:** فایل نهایی باکیفیت و آماده انتشار در ازای کسر Credit متناسب با نوع مدیا (تصویر/ویدیو).

### مسیر دوم: ✍️ فقط پرامپت حرفه‌ای می‌خواهم
- پرامپت تولیدی بر اساس دستورالعمل و سینتکس مدل انتخابی کاربر (Midjourney v6.1, FLUX.1, ChatGPT, Claude, Stable Diffusion, Runway) کامپایل و بهینه‌سازی می‌شود؛
- مقیاس فیزیکی، لنز، نورپردازی و پارامترهای منفی اختصاصی اعمال می‌شوند؛
- **خروجی:** پرامپت خودکفا و تست‌شده آماده کپی و استفاده در ابزار شخصی کاربر در ازای کسر Credit سبک.

---

# 4. ارزش پیشنهادی

ارزش اصلی محصول صرفاً این نیست که یک «AI Image Generator» باشد؛ بلکه ارزش بنیادین آن در لایهٔ:
> **Creative Intelligence + Product Understanding + Prompt Engineering + Marketing Workflow**

خلق می‌شود.

## 4.1 سادگی و عدم نیاز به تخصص
کاربر چه بخواهد تصویر آماده تحویل بگیرد و چه بخواهد برای میدجورنی خودش پرامپت بسازد، نیازی به یادگیری مهندسی پرامپت پیچیده ندارد.

## 4.2 درک عمیق ماهیت کالا (Product Understanding)
سیستم تفاوت یک ساعت مچی (نیاز به خوانایی صفحه و عقربه‌ها)، یک انگشتر (نیاز به ماکرو و درخشش سنگ) و یک کفش (نیاز به پرسپکتیو پویا) را می‌داند و پرامپت و صحنه را متناسب با صنف تنظیم می‌کند.

## 4.3 یکپارچگی ابزارها
کاربر نیازی به باز کردن چند سایت و ابزار ندارد؛ یک استودیوی متمرکز برای ۱۲ مدل هوش مصنوعی در اختیار دارد.

## 4.4 ثبات برند (Brand Kit)
Brand Kit باعث می‌شود تمام خروجی‌ها (تصویر یا پرامپت) با لحن، پالت رنگ و شعار کسب‌وکار هماهنگ باشند.

## 4.5 بهره‌وری اقتصادی دوگانه
کاربری که هزینه بالای اکانت‌های هوش مصنوعی را نمی‌پردازد، با پرداخت ریالی در مسیر اول محتوا می‌گیرد؛ کاربری هم که قبلاً اکانت خارجی خریده، با هزینه بسیار اندک بهترین استفاده را از اکانت خود می‌برد.

---

# 5. مشتری هدف

مشتریان فاز فعلی محصول به دو دسته اصلی تقسیم می‌شوند:

## دسته اول: مشتریان خروجی آماده (Full-Service AI)
- فروشگاه‌های آنلاین کوچک و متوسط (اینستاگرام، باسلام، دیجی‌کالا)
- مزون‌ها و فروشندگان پوشاک، عطر، ساعت، طلا و لوازم آرایشی
- کسب‌وکارهای محلی که عکاس و طراح ندارند و می‌خواهند بدون دانش فنی، تصویر و ویدیوی آماده دریافت کنند.

## دسته دوم: مشتریان پرامپت حرفه‌ای (Prompt-Only / BYO-Account)
- آژانس‌های تبلیغاتی و دیجیتال مارکتینگ
- طراحان گرافیک و Social Media Managerها
- تولیدکنندگان محتوا و کاربرانی که اشتراک شخصی Midjourney، ChatGPT Plus، Claude Pro یا Runway دارند و به دنبال سناریو، بریف بازاریابی و پرامپت‌های بی‌نقص تخصصی صنف کالا هستند.

## بازار اولیه پیشنهادی

کسب‌وکارهای کوچک و متوسطی که از Instagram یا فروشگاه آنلاین برای بازاریابی استفاده می‌کنند.

### دسته‌های مناسب

- فروشگاه‌های پوشاک
- مزون‌ها
- فروشندگان عطر
- فروشندگان لوازم آرایشی
- فروشگاه‌های اکسسوری
- جواهرات
- فروشگاه‌های آنلاین
- کافه‌ها
- رستوران‌ها
- فروشندگان محصولات مصرفی
- فروشندگان خودرو
- مشاوران املاک

## بازار دوم

- ادمین‌های Instagram
- Social Media Managers
- فریلنسرهای تولید محتوا
- آژانس‌های تبلیغاتی

## بازار سوم

شرکت‌های متوسط و بزرگ که نیاز به:

- Workspace
- Team
- Brand Governance
- حجم بالای تولید
- API
- White Label

دارند.

---

# 6. تجربه کاربری پیشنهادی

صفحه اول نباید یک Prompt Box بزرگ باشد.

بد:

```text
Describe what you want...

[ Generate ]
```

زیرا کاربر را دوباره وارد دنیای پیچیده Prompt می‌کند.

## صفحه اصلی

پیشنهاد:

> **امروز چی می‌خوای بسازی؟**

کارت‌ها:

```text
📸 عکس محصول

📱 پست اینستاگرام

🎬 تیزر تبلیغاتی

📲 استوری

🎥 Reel

🛍️ عکس فروشگاهی

🎁 کمپین تخفیف
```

---

## مرحله 1 — انتخاب محصول

```text
محصولت را انتخاب کن

[ + آپلود عکس محصول ]

یا

[ انتخاب از محصولات قبلی ]
```

---

## مرحله 2 — هدف

```text
این محتوا برای چیه؟

○ معرفی محصول
○ افزایش فروش
○ برندینگ
○ تخفیف
○ معرفی محصول جدید
○ جذب مخاطب
```

---

## مرحله 3 — سبک

به جای Prompt، انتخاب تصویری:

```text
Luxury
Minimal
Cinematic
Natural
Colorful
Dark
Professional
Fashion
```

---

## مرحله 4 — فضا

```text
Studio
Nature
Street
Luxury
Cafe
Office
Fantasy
3D
```

---

## مرحله 5 — ویدئو

```text
مدت ویدئو (از گزینه‌های پشتیبانی‌شده مدل)
Slow Zoom
Camera Orbit
Close-up
Product Reveal
Tracking Shot
Handheld
Cinematic
```

---

## مرحله 6 — فرمت

```text
Instagram Post
Instagram Story
Instagram Reel
TikTok
Website
```

---

# 7. قابلیت «خودت بهترینش را بساز»

یکی از قابلیت‌های مهم باید این باشد:

> **✨ خودت بهترینش رو بساز**

کاربر فقط می‌گوید:

```text
محصول: عطر
هدف: فروش
پلتفرم: Instagram
```

سیستم خودش انتخاب می‌کند:

```text
Style → Luxury Cinematic
Lighting → Dramatic
Environment → Premium Studio
Camera → Slow Push-in
Composition → Product Hero
Mood → Premium / Masculine
```

و قبل از Generate پیشنهاد خود را نشان می‌دهد.

این قابلیت می‌تواند یکی از مهم‌ترین بخش‌های تجربه کاربری باشد.

---

# 8. Creative Engine

هسته اصلی کسب‌وکار فقط UI نیست.

همچنین صرفاً APIهای AI نیست.

یکی از مهم‌ترین دارایی‌های محصول باید:

> **Creative Engine**

باشد.

مثلاً ورودی:

```json
{
  "product": "perfume",
  "goal": "sales",
  "audience": "men 25-40",
  "platform": "instagram",
  "style": "luxury",
  "mood": "dark",
  "format": "reels"
}
```

به Creative Brief تبدیل می‌شود:

```text
Product:
Luxury masculine perfume

Goal:
Sales

Environment:
Dark premium studio

Lighting:
Dramatic rim lighting

Camera:
Slow cinematic push-in

Composition:
Product centered

Mood:
Premium and masculine
```

سپس Prompt نهایی تولید می‌شود.

---

# 9. AI Model Router

یکی از مهم‌ترین تصمیم‌های فنی:

نباید Business Logic مستقیماً به یک AI Provider وابسته شود.

معماری پیشنهادی:

```text
User Request
      ↓
Creative Engine
      ↓
AI Model Router
      ↓
┌──────────┬──────────┬──────────┐
↓          ↓          ↓
Image      Video      Edit
Model      Model      Model
```

Router می‌تواند بر اساس موارد زیر تصمیم بگیرد:

- نوع خروجی
- کیفیت
- سرعت
- هزینه
- Availability
- قابلیت‌های مدل
- اندازه تصویر
- مدت ویدئو
- نیاز به Reference Image

این معماری باعث می‌شود اگر یک مدل بهتر یا ارزان‌تر وارد بازار شد، بتوان آن را اضافه کرد بدون اینکه کل سیستم بازنویسی شود.

---

# 10. Brand Kit

یکی از قابلیت‌های کلیدی برای Retention.

کاربر یک بار اطلاعات برند را وارد می‌کند:

```text
Brand Name
Logo
Primary Color
Secondary Color
Font
Brand Tone
Target Audience
Visual Style
Slogan
```

بعد هر Generation از Brand Kit استفاده می‌کند.

مثلاً:

```text
Create Instagram Post
       ↓
Brand Kit
       ↓
Creative Engine
       ↓
AI
       ↓
Brand-consistent Result
```

این قابلیت باعث می‌شود محصول از یک ابزار ساده AI به یک **ابزار کاری دائمی** تبدیل شود.

---

# 11. Product Library

کاربر باید بتواند محصولات خود را ذخیره کند.

مثلاً:

```text
My Products

🍾 Perfume A
🍾 Perfume B
👟 Sneaker A
👕 T-Shirt A
⌚ Watch A
```

سپس:

```text
Perfume A
   ↓
Create Reel
```

یا:

```text
Perfume A
   ↓
Create Campaign
```

این ویژگی باعث افزایش استفاده مجدد از محصول می‌شود.

---

# 12. Campaign Generator

این بخش می‌تواند یکی از مهم‌ترین قابلیت‌های تجاری محصول باشد.

به جای:

> Generate Image

کاربر:

> **Create Campaign**

را انتخاب می‌کند.

مثلاً:

```text
Product:
Luxury Perfume

Goal:
Sales

Campaign:
Summer Sale
```

سیستم:

```text
Campaign

✓ Hero Image
✓ Instagram Post
✓ Story
✓ Reel
✓ Product Close-up
✓ Promotional Banner
✓ Caption
```

تولید می‌کند.

---

# 13. Content Calendar

مرحله بعدی:

> «برای این ماه برای من محتوا بساز.»

سیستم:

```text
Day 1 → Product Hero
Day 2 → Educational
Day 3 → Product Detail
Day 4 → Customer Problem
Day 5 → Offer
Day 6 → Lifestyle
...
```

و سپس:

> Generate All

مثلاً:

```text
12 Images
8 Stories
4 Reels
6 Promotional Posts
```

این ویژگی ارزش اشتراک را بسیار افزایش می‌دهد.

---

# 14. AI Marketing Assistant

در نسخه‌های بعدی کاربر می‌تواند بگوید:

> این محصول جدید منه.

سیستم پیشنهاد بدهد:

```text
5 Creative Ideas

1. Luxury Hero Shot
2. Problem / Solution
3. Lifestyle
4. Before / After
5. Promotional Reel
```

کاربر فقط انتخاب می‌کند.

این تجربه بسیار ساده‌تر از Prompt Engineering است.

---

# 15. Conversational Editing

بعد از تولید خروجی، کاربر نباید مجبور باشد دوباره از اول بسازد.

مثلاً:

```text
لباس مدل را مشکی کن.

پس‌زمینه را روشن‌تر کن.

محصول را بزرگ‌تر کن.

نور را بیشتر کن.

متن را حذف کن.

دوربین را نزدیک‌تر کن.
```

سیستم درخواست را به Editing Operation تبدیل می‌کند.

این ویژگی می‌تواند تجربه محصول را بسیار جذاب‌تر کند.

---

# 16. MVP

نسخه اول باید کوچک باشد.

## قابلیت‌های MVP

### ضروری

- ثبت‌نام و ورود
- آپلود محصول
- Product Library
- انتخاب Goal
- انتخاب Style
- انتخاب Format
- Creative Engine
- Image Generation
- Short Video Generation
- Generation History
- Credit System
- Subscription
- Payment
- Download

### فعلاً نساز

- Voice Generation
- Avatar
- Music Generation
- 3D
- Full Video Editor
- Marketplace
- Community
- Prompt Marketplace
- Social Network
- قابلیت‌های پیچیده تیمی

---

# 17. مسیر اصلی MVP

```text
Landing Page
     ↓
Sign Up
     ↓
Upload Product
     ↓
Choose Goal & Style & Category
     ↓
انتخاب مسیر تحویل و مدل مصرف Credit
   ↙                                 ↘
[🎨 تولید محتوا با AI ما]            [✍️ فقط پرامپت حرفه‌ای می‌خواهم]
Processing in Queue                  Prompt Compiler & Optimization
     ↓                                    ↓
Preview & Download Content           Copy Self-Contained Prompt for Target AI
```

هدف:
> **رسیدن به بیشترین ارزش با کمترین کلیک و پوشش هر دو گروه مخاطب (متقاضی محتوای آماده و کاربر دارای اکانت هوش مصنوعی)**

---

# 18. نقشه راه محصول (Product Roadmap)

## Phase 1 — هسته اصلی محصول (Core Product & Dual Monetization)

```text
Authentication & Profiles
Product Upload & Library
Category Intelligence (Jewelry, Watches, Shoes, etc.)
Creative Engine & Multi-Model Compilers
Path 1: In-App Generation (Image X Credits, Video Y Credits)
Path 2: Professional Prompt Only (Simple 1, Image 2, Video 5 Credits)
Universal Credit Ledger (Top-up & Subscription)
Payment Gateway & Invoice
History & Before/After Review
Download & Copy Prompt
```

---

## Phase 2 — Bring Your Own Key (BYOK)

```text
اتصال API Key شخصی کاربر (Google, OpenAI, Anthropic, Replicate)
پرداخت مستقیم هزینه هوش مصنوعی از حساب کاربر
درآمدزایی پلتفرم بابت Creative Intelligence، Workflow و Brand Kit
```

---

## Phase 3 — Template / Prompt Marketplace

```text
فروش Prompt، Template و Creative Recipe توسط Creatorها
مارکت‌پلیس قالب‌های تخصصی اصناف
دریافت کمیسیون پلتفرم از هر تراکنش
```

---

## Phase 4 — Agency / Team Plans

```text
Organizations & Workspaces
تیم‌ها و سطوح دسترسی (RBAC)
مدیریت چند Client و چند Brand Kit
پلن‌های حجمی شرکتی
```

---

## Phase 5 — White Label

```text
استقرار پلتفرم با برند، لوگو و دامنه اختصاصی آژانس‌ها
سفارشی‌سازی کامل پنل و درگاه پرداخت
```

---

## Phase 6 — API / Enterprise

```text
ارائه Creative Engine و Category Compilers به صورت Public API
اتصال به فروشگاه‌سازها (دیجی‌کالا، ووکامرس، شاپایفای، باسلام)
قراردادهای اختصاصی سازمانی با SLA تضمین‌شده
```

---

# 19. مدل درآمدی فعلی محصول (Current Revenue Model)

مدل درآمدی فاز فعلی محصول به‌صورت **اجباری، قطعی و منحصراً بر پایه دو مسیر اصلی** زیر قرار دارد و هر دو مسیر کاملاً پولی و متصل به سیستم **Credit** هستند:

```text
                    Product
                       │
              ┌────────┴────────┐
              │                 │
       AI ما را استفاده کن    فقط Prompt بگیر
              │                 │
           Credits           Credits
              │                 │
       Image / Video       Professional Prompt
```

### مسیر اول: 🎨 تولید محتوا با AI ما
کاربر از مدل‌ها و زیرساخت هوش مصنوعی متصل به پلتفرم استفاده می‌کند.
- **تولید تصویر:** کسر X واحد Credit (مقدار پیش‌فرض ۱۰ تا ۲۵ اعتبار متناسب با کیفیت استاندارد/پریمیوم).
- **تولید ویدئو:** کسر Y واحد Credit (پویا و بر اساس مدت ثانیه و مدل انتخابی).
- **قابلیت‌های تکمیلی و ابزارها:** کسر Credit متناسب (مانند واریانت‌ها، حذف پس‌زمینه، آپ‌اسکیل).
- کاربر با خرید بسته‌های اعتباری (Top-Up) یا اشتراک، حساب خود را شارژ می‌کند و با تولید محتوا در پلتفرم، اعتبار او تسویه می‌شود.

### مسیر دوم: ✍️ فقط پرامپت حرفه‌ای می‌خواهم
کاربر نمی‌خواهد از هوش مصنوعی پلتفرم استفاده کند و خودش اکانت میدجورنی، ChatGPT Plus، Claude Pro یا Runway دارد.
در این حالت سیستم:
1. عکس و متادیتای محصول را دریافت می‌کند؛
2. محصول را تحلیل و قوانین فیزیکی صنف (مقیاس، نگین، صفحه ساعت و...) را استخراج می‌کند؛
3. هدف بازاریابی، اتمسفر و لحن برند را در نظر می‌گیرد؛
4. Creative Brief ساختاریافته تولید می‌کند؛
5. پرامپت را بر اساس دستورالعمل و سینتکس اختصاصی مدل انتخابی کاربر (Midjourney v6.1, FLUX.1, ChatGPT, Claude, SD) کامپایل و بهینه‌سازی می‌کند؛
6. پرامپت خودکفا و تست‌شده را جهت کپی و استفاده در اکانت شخصی کاربر تحویل می‌دهد.

این مسیر نیز **کاملاً پولی و مبتنی بر Credit** است، اما Credit مصرفی آن با مسیر «تولید محتوا با AI ما» متفاوت و بسیار ارزان‌تر محاسبه می‌شود.

---

## 19.1 مدل‌های درآمدی مراحل بعدی (Future Revenue Models)
سایر مدل‌های درآمدی در فاز فعلی فعال نیستند و با اولویت‌بندی به فازهای بعدی نقشه راه منتقل شده‌اند:
1. **Phase 2 — BYOK (Bring Your Own Key):** اتصال کلید API کاربر و دریافت هزینه اشتراک پلتفرم بابت هوش صحنه‌پردازی.
2. **Phase 3 — Template / Prompt Marketplace:** کمیسیون از خرید و فروش قالب‌ها و پرامپت‌های کاربران.
3. **Phase 4 — Agency / Team Plans:** بسته‌های اشتراک تیمی برای مدیریت چندین مشتری و برند.
4. **Phase 5 — White Label:** ارائه نسخه اختصاصی با برند و لوگوی مشتری سازمانی.
5. **Phase 6 — API / Enterprise:** فروش دسترسی API به توسعه‌دهندگان و فروشگاه‌های بزرگ.

---

# 20. Credit System — واحد مشترک ارزش پلتفرم

در پلتفرم حله، اعتبار صرفاً به معنای شمارشگر فایل نیست:

```text
Credit ≠ فقط واحد تولید تصویر/ویدئو

Credit = واحد ارزش قابل مصرف در پلتفرم
```

هر خدمت بر اساس ارزش ارائه‌شده و هزینه زیرساختی، میزان مشخصی Credit مصرف می‌کند:

| نوع خدمت / قابلیت | مصرف Credit | ماهیت و توضیحات |
| :--- | :---: | :--- |
| **Simple Prompt (پرامپت ساده)** | ۱ Credit | پرامپت متنی پایه و ایده‌پردازی اولیه |
| **Image Prompt (پرامپت تخصصی عکس)** | ۲ Credits | پرامپت کامپایل‌شده برای Midjourney / Flux / Imagen با حفظ مشخصات صنف |
| **Video Prompt (پرامپت تخصصی ویدیو)** | ۵ Credits | برگه کارگردانی، زاویه دوربین و حرکت فیزیکی برای Veo / Runway |
| **Image Generation (تولید تصویر با AI ما)** | X Credits (پیش‌فرض ۱۰ تا ۲۵) | رندر تصویر کامل در پلتفرم (مقدار X در پنل ادمین قابل تنظیم است) |
| **Video Generation (تولید ویدیو با AI ما)** | Y Credits (پویا بر اساس ثانیه) | رندر ویدیوی کامل در پلتفرم (مقدار Y در پنل ادمین قابل تنظیم است) |
| **Image Utility Tools (ابزارهای ویرایش)** | ۵ تا ۱۵ Credits | حذف پس‌زمینه، آپ‌اسکیل، واریانت‌ها، سایه و بسط کادر |

> **نکته مدیریتی:** مقادیر دقیق X و Y در جدول بالا توسط مدیر سیستم در کانفیگ و تنظیمات سیستم (`config/credits.php` و اندپوینت ادمین) قابل تغییر است و نباید به عنوان عدد صلب و غیرقابل انعطاف در نظر گرفته شود.

---

# 21. اقتصاد واحد محصول (Unit Economics تفکیک‌شده)

برای سنجش سلامت مالی، اقتصاد واحد برای هر دو مسیر به صورت کاملاً مجزا پایش می‌شود:

## 21.1 مسیر اول: تولید محتوا با AI ما (AI Generation)
- **هزینه متغیر هر درخواست:**
  `Cost = هزینه تحلیل LLM + هزینه پرامپت + هزینه سرور تصویر/ویدیو (گوگل) + هزینه ذخیره‌سازی و ترافیک + ذخیره شکست (Retry Reserve)`
- **دامنه هزینه داخلی:**
  - تصویر: بین ۰.۰۳ تا ۰.۱۰ دلار
  - ویدیو: وابسته به مدت زمان و ثانیه (۰.۰۵ تا ۰.۴۰ دلار به ازای هر ثانیه)
- **حاشیه سود ناخالص هدف (Target Gross Margin):** ۵۰٪ تا ۷۰٪

## 21.2 مسیر دوم: فقط پرامپت حرفه‌ای (Prompt-Only)
- **هزینه متغیر هر درخواست:**
  `Cost = هزینه توکن‌های ورودی/خروجی مدل زبانی سبک (Gemini Flash / GPT-Mini) ≈ $0.0003 تا $0.0006`
  در این مسیر **هیچ هزینه دلاری سنگینی بابت رندر تصویر یا ویدیو به پلتفرم تحمیل نمی‌شود.**
- **حاشیه سود ناخالص هدف (Target Gross Margin):** **بیش از ۸۵٪ تا ۹۵٪**
- **اهمیت تجاری:** این مسیر برای پلتفرم یک جریان درآمدی با حاشیه سود بسیار بالا، بدون ریسک هزینه‌های بالای GPU و با جذب مخاطبان حرفه‌ای است.

---

# 22. معماری فنی

برای MVP، پیشنهاد نمی‌شود از ابتدا Microservice بسازیم.

با توجه به ماهیت محصول، معماری اولیه:

```text
                PWA
                 │
                 ↓
            Laravel API
                 │
       ┌─────────┼─────────┐
       ↓         ↓         ↓
     MySQL     Redis     Storage
                 │
                 ↓
               Queue
                 │
                 ↓
        Generation Jobs
                 │
                 ↓
             AI Gateway
                 │
        ┌────────┼────────┐
        ↓        ↓        ↓
      Image    Video     Edit
```

---

# 23. چرا Generation باید Asynchronous باشد؟

ساخت تصویر ممکن است زمان ببرد.

ساخت ویدئو ممکن است بسیار بیشتر طول بکشد.

بنابراین نباید درخواست HTTP را تا پایان Generation باز نگه داشت.

معماری:

```text
POST /generations
        ↓
Generation Created
        ↓
status = queued
        ↓
Queue
        ↓
AI Provider
        ↓
Webhook
        ↓
status = completed
```

Frontend:

```text
Generating...

████████░░ 80%
```

و بعد:

```text
Your content is ready 🎉
```

---

# 24. AI Gateway

پیشنهاد می‌شود تمام ارتباطات AI از یک لایه داخلی عبور کند.

```text
Laravel
   ↓
AI Gateway
   ↓
Model Router
   ↓
Provider Adapter
   ↓
AI Provider
```

مزایا:

- کنترل هزینه
- Logging
- Retry
- Timeout
- Provider Failover
- Model Selection
- Usage Tracking
- Security

---

# 25. Provider Abstraction

پیشنهاد معماری:

```php
interface ImageGenerator
{
    public function generate(
        GenerationRequest $request
    ): GenerationResult;
}
```

Providerهای مختلف:

```text
ImageGenerator
 ├── ProviderA
 ├── ProviderB
 ├── ProviderC
 └── ProviderD
```

برای ویدئو نیز:

```text
VideoGenerator
```

و برای ویرایش:

```text
ImageEditor
```

بهتر است Business Logic به هیچ Provider خاصی وابسته نباشد.

---

# 26. تکنولوژی پیشنهادی

## Frontend

```text
PWA
Mobile-first
Responsive UI
```

## Backend

```text
Laravel
PHP
REST API
```

## Database

```text
MySQL / MariaDB
```

## Queue / Cache

```text
Redis
Laravel Queue
Horizon
```

## Storage

Object Storage برای:

- Original Images
- Generated Images
- Videos
- Thumbnails
- Temporary Assets

## Monitoring

حداقل:

```text
Application Logs
Queue Monitoring
AI Provider Logs
Generation Metrics
Error Tracking
Cost Tracking
```

---

# 27. مدل داده اولیه

```text
users

organizations

organization_members

brands

products

product_assets

templates

creative_projects

generations

generation_variants

ai_providers

ai_models

generation_jobs

credits

credit_transactions

subscriptions

payments

media_assets

usage_logs
```

از ابتدا بهتر است مفهوم:

```text
Organization
Workspace
Brand
```

در معماری لحاظ شود؛ حتی اگر قابلیت Team در MVP فعال نباشد.

---

# 28. مزیت رقابتی

AI به تنهایی مزیت رقابتی پایدار نیست.

رقبا می‌توانند مدل‌های جدیدتر و ارزان‌تر اضافه کنند.

مزیت باید در این موارد باشد:

## Workflow

فرآیند کامل و ساده.

## Templates

سناریوهای آماده برای کسب‌وکارها.

## Brand Knowledge

شناخت برند مشتری.

## Product Knowledge

شناخت محصولات مشتری.

## Creative Intelligence

پیشنهاد ایده و سناریو.

## Localization

تمرکز روی بازار و زبان خاص.

## Automation

تولید مستمر محتوا.

---

# 29. فرصت بازار فارسی

اگر بازار اولیه ایران باشد، امکانات زیر می‌تواند مزیت مهمی باشد:

- RTL
- فونت فارسی
- کپشن فارسی
- هشتگ فارسی
- تقویم شمسی
- مناسبت‌های ایرانی
- کمپین نوروز
- شب یلدا
- روز مادر
- روز پدر
- مناسبت‌های فروش
- لحن‌های مختلف فارسی

مثلاً:

> برای شب یلدا برای فروشگاه من کمپین بساز.

سیستم:

```text
Campaign Strategy
       +
Images
       +
Stories
       +
Reel
       +
Persian Captions
       +
CTA
```

تولید می‌کند.

---

# 30. استراتژی Positioning

Positioning ضعیف:

> تولید عکس با هوش مصنوعی

Positioning بهتر:

> تولید محتوای Instagram با هوش مصنوعی

Positioning پیشنهادی:

> **دستیار بازاریابی هوش مصنوعی برای کسب‌وکارها**

و در سطح Campaign:

> **از یک عکس محصول، یک کمپین تبلیغاتی بساز.**

---

# 31. استراتژی فروش

نباید فروش را با توضیح تکنولوژی شروع کرد.

مشتری اهمیت نمی‌دهد از چه مدل AI استفاده می‌شود.

او نتیجه می‌خواهد.

## پیام‌های فروش متناسب با دو مسیر فعلی

### ۱. برای مسیر «🎨 تولید محتوا با AI ما»
> **عکس محصولت رو بده؛ محتوای تبلیغاتی آماده اینستاگرام تحویل بگیر.**  
> **بدون بلد بودن Prompt یا کار با ابزارهای پیچیده، برای محصولت عکس و ویدیوی تبلیغاتی تجاری بساز.**

### ۲. برای مسیر «✍️ فقط پرامپت حرفه‌ای می‌خواهم»
> **اکانت Midjourney یا ChatGPT داری اما پرامپت‌هایت عکس‌های مصنوعی، بی‌ربط و خراب می‌سازد؟**  
> **عکس و مشخصات کالا را بده؛ پرامپت مهندسی‌شده، دقیق و کالیبره‌شده برای مدل خودت تحویل بگیر با حفظ ۱۰۰٪ جزئیات صنف، نگین، متریال و مقیاس واقعی کالا.**

و پیام مشترک کمپین:

> **از یک عکس محصول، یک خروجی تجاری یا یک دستورکار کارگردانی هوش مصنوعی کامل بساز.**

---

# 32. استراتژی ورود به بازار

پیشنهاد نمی‌شود از روز اول به دنبال هزاران کاربر باشید.

## مرحله اول

پیدا کردن 20 تا 50 مشتری واقعی (شامل هر دو دسته: فروشگاه‌های بدون مهارت AI و طراحان/ادمین‌های دارای اکانت AI).

با آن‌ها:

- صحبت شود؛
- محصول آزمایشی داده شود؛
- خروجی (عکس نهایی یا پرامپت کالیبره‌شده) تست شود؛
- Feedback گرفته شود؛
- رفتارشان در انتخاب بین دو مسیر ثبت شود.

## هدف

اثبات:

> آیا مشتری حاضر است برای نتیجه نهایی (مسیر ۱) یا پرامپت هوشمند صنفی (مسیر ۲) Credit بخرد؟

---

# 33. جذب اولین مشتریان

روش پیشنهادی:

### 1. Landing Page

با:

- Demo دوگانه (مشاهده تولید مستقیم + نمونه پرامپت‌های کامپایل‌شده)
- Before / After
- نمونه خروجی برای اصناف مختلف (طلا، ساعت، کفش، عطر و...)
- جدول شفاف قیمت Credit برای هر دو مسیر
- CTA

### 2. Direct Outreach

به کسب‌وکارهای هدف.

### 3. همکاری با ادمین‌ها و طراحان

ادمین‌های Instagram و طراحان فریلنسر (که اغلب اکانت میدجورنی دارند و خریدار پرامپت حرفه‌ای هستند) مشتریان بسیار مناسبی هستند.

### 4. همکاری با آژانس‌ها

یک آژانس ممکن است چندین مشتری را وارد پلتفرم کند.

### 5. Case Study

مثلاً:

> یک فروشگاه طلا با ۱۰ عکس خام، ۳۰ تصویر ژورنالی کاتالوگی و ۳ ویدیوی معرفی با حفظ نگین ساخت.

---

# 34. قیف فروش دو شاخه (Dual-Track Sales Funnel)

```text
                       Traffic
                          ↓
                     Landing Page
                          ↓
              انتخاب نیاز کاربر در پلتفرم
             ┌────────────┴────────────┐
             ↓                         ↓
   🎨 تولید محتوا با AI ما      ✍️ فقط پرامپت حرفه‌ای
             ↓                         ↓
     تست رایگان تصویر          تست کامپایل پرامپت صنف
             ↓                         ↓
      خروجی تصویر نهایی          پرامپت کالیبره‌شده تست‌شده
             ↓                         ↓
      دانلود / دانلود وب       کپی و تست در Midjourney/GPT
             └────────────┬────────────┘
                          ↓
             ارزش اثبات‌شده (First Value)
                          ↓
                       Paywall
                          ↓
        خرید بسته‌های Credit یا اشتراک ماهانه
```

مهم‌ترین شاخص:

> **Time to First Value**

یعنی کاربر در هر دو مسیر چقدر سریع به اولین خروجی قابل استفاده می‌رسد (کمتر از ۲ دقیقه برای پرامپت، کمتر از ۵ دقیقه برای تصویر/ویدیو).

---

# 35. Free Plan

Free Plan نباید بیش از حد سخاوتمند باشد، اما باید قدرت هر دو مسیر را نشان دهد:

هدف Free:

> ایجاد تجربه «Wow» در اولین تعامل.

مدل پیشنهادی:

```text
اعطای ۱۰ الی ۲۰ Credit اولیه رایگان (پس از اعتبارسنجی شماره موبایل)
```

کاربر با این Credit رایگان می‌تواند:
- یا **۱ تصویر استاندارد با AI داخلی** تولید کند (مسیر ۱)؛
- یا **۵ تا ۱۰ پرامپت تخصصی و کالیبره‌شده** برای اصناف مختلف تولید و تست کند (مسیر ۲).

در بخش تولید با AI داخلی، می‌توان محدودیت‌هایی مانند:
- Watermark (واترمارک برند)
- رزولوشن استاندارد
- اولویت صف عادی

قرار داد، در حالی که در مسیر پرامپت، خروجی بدون واترمارک متنی به کاربر تحویل می‌شود تا در هوش مصنوعی خودش بیازماید.

---

# 36. Growth Loop

خروجی Free می‌تواند Watermark داشته باشد:

```text
Created with [Brand]
```

اگر کاربر خروجی را در شبکه اجتماعی منتشر کند، دیگران با محصول آشنا می‌شوند.

در صورت مناسب بودن شرایط:

```text
User
 ↓
Creates Content
 ↓
Shares Content
 ↓
Others See It
 ↓
Visit Platform
 ↓
Create Free Content
 ↓
New User
```

---

# 37. B2C در مقابل B2B

## B2C

مزایا:

- ورود آسان
- تعداد کاربر بالا
- Self-service

معایب:

- Churn بالاتر
- Average Revenue پایین‌تر
- حساسیت به قیمت

## B2B

مزایا:

- قرارداد بزرگ‌تر
- Retention بالاتر
- مصرف بیشتر
- ارزش بالاتر

## Agency

از نظر استراتژیک بسیار جذاب است.

یک آژانس می‌تواند:

```text
Agency
 ├── Client A
 ├── Client B
 ├── Client C
 ├── Client D
 └── Client E
```

را مدیریت کند.

---

# 38. White Label و قابلیت‌های Agency (آینده — Phase 4 & Phase 5)

> **توجه استراتژیک:** قابلیت‌های Agency Plans و White Label جزء مدل درآمدی فاز فعلی (MVP) نیستند و مطابق با نقشه راه مصوب، به ترتیب در فازهای ۴ و ۵ فعال خواهند شد. در فاز فعلی، پلتفرم منحصراً بر دو مسیر اعتباری (Credit) «تولید با AI ما» و «فقط پرامپت حرفه‌ای» متمرکز است.

در مراحل آینده B2B (Phase 4 & 5) می‌توان به Agency اجازه داد:

```text
agency.yourplatform.com
```

یا در سطح پیشرفته:

```text
ai.agency-domain.com
```

داشته باشد.

Agency از مشتری پول می‌گیرد و به پلتفرم بابت مصرف Credit یا اشتراک سازمانی هزینه می‌پردازد.

این مدل در فاز ۵ می‌تواند درآمد B2B را به شکل چشمگیری افزایش دهد، اما اولویت فعلی ساخت بهترین هسته استودیوی محصول و فروش مستقیم Credit در دو مسیر است.

---

# 39. ریسک‌های اصلی

## 39.1 وابستگی به AI Provider

قیمت یا کیفیت Provider ممکن است تغییر کند.

### راه‌حل

Provider Abstraction + Model Router.

---

## 39.2 هزینه Video

Video Generation می‌تواند هزینه قابل توجهی داشته باشد.

### راه‌حل

Credit System + Usage Limits + Cost Monitoring.

---

## 39.3 رقابت

شرکت‌های بزرگ می‌توانند قابلیت مشابه بسازند.

### راه‌حل

تمرکز روی:

```text
Workflow
+
Brand
+
Business Context
+
Localization
+
Automation
```

---

## 39.4 کیفیت خروجی

اگر خروجی ضعیف باشد، کاربر برنمی‌گردد.

### راه‌حل

Quality Evaluation و انتخاب هوشمند Model.

---

## 39.5 Churn

اگر کاربر فقط یک بار تولید کند و برود، SaaS جذابی نیست.

### راه‌حل

```text
Brand Kit
Product Library
Campaign
Content Calendar
Automation
```

---

# 40. KPIهای اصلی

## Product

```text
Activation Rate
Time to First Value
Generation Success Rate
Generation Completion Rate
```

## Business

```text
MRR
ARR
ARPU
Churn
Retention
LTV
CAC
Gross Margin
```

## AI Economics

```text
AI Cost / Generation
AI Cost / User
Average Credits Used
Average Generation Cost
```

## Conversion

```text
Free → Paid
Landing → Signup
Signup → First Generation
First Generation → Second Generation
Second Generation → Paid
```

---

# 41. معیار Go / No-Go

قبل از توسعه سنگین باید چند فرضیه تست شود.

## فرضیه 1

آیا کاربر می‌تواند بدون آموزش خاص اولین خروجی را بسازد؟

## فرضیه 2

آیا خروجی از نظر کاربر واقعاً قابل استفاده است؟

## فرضیه 3

آیا کاربر دوباره برای محصول دیگری Generation انجام می‌دهد؟

## فرضیه 4

آیا حاضر است بابت آن پول بدهد؟

## فرضیه 5

آیا هزینه تولید محتوا کمتر از درآمد حاصل از اشتراک است؟

اگر جواب این موارد مثبت باشد، توسعه محصول منطقی است.

---

# 42. چشم‌انداز نهایی

محصول نهایی نباید صرفاً:

```text
Text → Image
```

باشد.

بلکه:

```text
Business Goal
      ↓
Marketing Strategy
      ↓
Creative Ideas
      ↓
Campaign
      ↓
Images
      ↓
Videos
      ↓
Captions
      ↓
Content Calendar
      ↓
Ready to Publish
```

باشد.

---

# 43. مثال کامل

فرض کنیم مشتری یک فروشگاه عطر دارد.

او فقط می‌گوید:

```text
محصول جدید
عطر مردانه
هدف: فروش
پلتفرم: Instagram
```

سیستم پیشنهاد می‌دهد:

```text
Campaign:

1. Luxury Product Hero
2. Lifestyle Scene
3. Close-up
4. Promotional Story
5. Cinematic Reel
6. Product Detail
7. Sales Post
```

مشتری:

> Create Campaign

را می‌زند.

سیستم تمام Assetها را تولید می‌کند.

در آینده:

```text
Campaign
 ↓
Content Calendar
 ↓
Scheduled Publishing
```

نیز اضافه می‌شود.

---

# 44. برنامه اجرایی پیشنهادی

## مرحله 0 — Validation

قبل از کدنویسی:

- Landing Page
- Demo Prototype
- 20 مشتری بالقوه
- مصاحبه
- تست قیمت
- دریافت Feedback

### هدف

اثبات اینکه مشکل واقعی است.

---

## مرحله 1 — MVP

تمرکز فقط روی:

```text
Product Upload
+
Creative Builder
+
Image
+
Short Video
+
Credits
+
Payment
```

---

## مرحله 2 — Retention

اضافه کردن:

```text
Brand Kit
Product Library
History
Templates
Editing
```

---

## مرحله 3 — Revenue Expansion

اضافه کردن:

```text
Campaign
Content Calendar
Bulk Generation
```

---

## مرحله 4 — B2B

اضافه کردن:

```text
Workspace
Team
Clients
Agency
White Label
API
```

---

# 45. پیشنهاد معماری Domain در Laravel

برای اینکه پروژه بعداً قابل توسعه باشد، Domainهای اصلی می‌توانند شامل موارد زیر باشند:

```text
Auth
Users
Organizations
Brands
Products
Creative
Generations
AI
Credits
Billing
Media
Campaigns
Templates
Notifications
Analytics
```

برای MVP لازم نیست همه این‌ها به Microservice تبدیل شوند.

همه می‌توانند داخل یک Laravel Application باشند و فقط مرزهای منطقی Domain حفظ شود.

---

# 46. اصول فنی مهم

## اصل 1

API Keyها فقط سمت Backend.

## اصل 2

Generationها asynchronous.

## اصل 3

تمام Generationها قابل Tracking باشند.

## اصل 4

Providerها Abstract باشند.

## اصل 5

هزینه هر Generation قابل اندازه‌گیری باشد.

## اصل 6

Usage Limit از ابتدا وجود داشته باشد.

## اصل 7

Storage و Database از هم جدا باشند.

## اصل 8

Webhookها idempotent باشند.

## اصل 9

Retry و Timeout داشته باشیم.

## اصل 10

هیچ Feature بزرگی بدون اندازه‌گیری رفتار کاربر ساخته نشود.

---

# 47. مدل نهایی محصول

در نهایت محصول باید این چرخه را ارائه دهد:

```text
┌─────────────────────┐
│      Business       │
└──────────┬──────────┘
           ↓
┌─────────────────────┐
│       Product       │
└──────────┬──────────┘
           ↓
┌─────────────────────┐
│    Marketing Goal   │
└──────────┬──────────┘
           ↓
┌─────────────────────┐
│   Creative Engine   │
└──────────┬──────────┘
           ↓
┌─────────────────────┐
│     AI Router       │
└──────────┬──────────┘
           ↓
┌─────────────────────┐
│ Image / Video / Edit│
└──────────┬──────────┘
           ↓
┌─────────────────────┐
│    Campaign         │
└──────────┬──────────┘
           ↓
┌─────────────────────┐
│    Publish          │
└─────────────────────┘
```

---

# 48. جمع‌بندی مدیریتی

این ایده از نظر فنی قابل ساخت است.

اما موفقیت آن بیشتر از اینکه به توانایی اتصال چند AI API وابسته باشد، به **Product Design، کیفیت خروجی، Pricing، Distribution و Retention** وابسته است.

سه تصمیم کلیدی:

### تصمیم محصولی

محصول را به عنوان:

> **AI Marketing Content Platform**

بسازیم، نه صرفاً Image Generator.

### تصمیم فنی

از ابتدا:

> **AI Gateway + Model Router + Provider Abstraction**

را طراحی کنیم.

### تصمیم تجاری

با یک بازار محدود شروع کنیم، 20 تا 50 مشتری واقعی پیدا کنیم و قبل از توسعه سنگین، **Willingness to Pay** را اثبات کنیم.

---

# 49. نتیجه نهایی

فرصت اصلی این محصول در این نیست که:

> «ما هم می‌توانیم با AI عکس بسازیم.»

فرصت اصلی این است:

> **«ما پیچیدگی AI را از صاحب کسب‌وکار می‌گیریم و به جای آن نتیجه بازاریابی تحویل می‌دهیم.»**

و مسیر تکامل محصول:

```text
AI Generator
      ↓
Content Generator
      ↓
Content Studio
      ↓
Campaign Generator
      ↓
Marketing Assistant
      ↓
AI Marketing Operating System
```

است.

**هدف نهایی:**

> کاربر نباید متخصص هوش مصنوعی باشد؛ فقط باید بداند چه چیزی می‌خواهد بفروشد.

---

# 50. اقتصاد واقعی تولید محتوا و هزینه AI

این بخش بر اساس بررسی قیمت‌گذاری فعلی سرویس‌های AI در زمان تهیه این نسخه اضافه شده است. قیمت APIها ممکن است تغییر کند؛ بنابراین در محصول واقعی باید قیمت Providerها به صورت Configurable و قابل به‌روزرسانی نگهداری شود.

## 50.1 اصل مهم در محاسبه Cost Per Request در دو مسیر محصول

هزینه‌ای که کسب‌وکار بابت هر درخواست متحمل می‌شود وابسته به انتخاب کاربر میان دو مسیر اصلی فعلی محصول است:

```text
                               ورود کاربر و آپلود محصول
                                         ↓
                               تحلیل تصویر و صنف محصول
                                         ↓
                                Creative Brief هوشمند
                                         ↓
                           انتخاب شیوه تحویل ارزش توسط کاربر
                           ┌─────────────┴─────────────┐
                           ↓                           ↓
                 🎨 تولید محتوا با AI ما        ✍️ فقط پرامپت حرفه‌ای
                           ↓                           ↓
                 Prompt داخلی + کامپوزیت       کامپایل اختصاصی پرامپت مدل مقصد
                           ↓                           ↓
                 رندر سنگین مدل تصویر/ویدیو       تحویل پرامپت خودکفا به کاربر
                           ↓                           ↓
                  پردازش و ذخیره فایل          (رندر در اکانت شخصی کاربر)
                           ↓                           ↓
                    کسر X یا Y اعتبار                  کسر ۱، ۲ یا ۵ اعتبار
```

بنابراین برای Unit Economics باید دو Cost Model کاملاً مجزا لحاظ شوند:
1. **مسیر اول (تولید با AI ما):** هزینه تحلیل + هزینه پرامپت + **هزینه اصلی GPU رندر تصویر/ویدیو** + ترافیک و ذخیره‌سازی + ذخیره شکست (Retry Reserve).
2. **مسیر دوم (فقط پرامپت حرفه‌ای):** فقط هزینه توکن تحلیل سبک و کامپایل پرامپت (بدون هیچ‌گونه هزینه رندر تصویر یا ویدیو).

---

## 50.2 Google — گزینه اقتصادی و مناسب برای MVP

Google در حال حاضر یکی از گزینه‌های جذاب برای معماری اولیه محصول است، چون هم مدل‌های تحلیل/Reasoning و هم مدل‌های Image و Video در اکوسیستم آن وجود دارند.

### تحلیل تصویر و تولید متن

برای نمونه، Gemini 2.5 Flash-Lite در حدود:

```text
Input:  $0.10 / 1M tokens
Output: $0.40 / 1M tokens
```

قرار دارد.

اگر برای یک Request فرض کنیم:

```text
1,500 input tokens
500 output tokens
```

هزینه تقریبی:

```text
Input  = $0.00015
Output = $0.00020
-----------------
Total  = $0.00035
```

این عدد فقط یک Example است؛ تعداد Token واقعی تصویر به مدل، ابعاد تصویر و نحوه ارسال آن بستگی دارد.

### تولید تصویر

قیمت‌های فعلی نمونه برای مدل‌های Imagen:

```text
Imagen 4 Fast  → $0.02 / image
Imagen 4       → $0.04 / image
Imagen 4 Ultra → $0.06 / image
```

برای Gemini 2.5 Flash Image نیز خروجی تصویر استاندارد حدود:

```text
$0.039 / image
```

برای خروجی 1024×1024 گزارش شده است.

### تولید ویدئو

برای Veo 3.1 قیمت به ازای ثانیه است. نمونه‌های مهم:

```text
Veo 3.1 Lite 720p   → $0.05 / sec
Veo 3.1 Fast 720p   → $0.10 / sec
Veo 3.1 Fast 1080p  → $0.12 / sec
Veo 3.1 Standard    → $0.40 / sec
```

هزینه ویدئو بر اساس مدت انتخابی کاربر محاسبه می‌شود:

```text
Lite 720p      → duration × $0.05
Fast 720p      → duration × $0.10
Fast 1080p     → duration × $0.12
Standard       → duration × $0.40
```

این تفاوت قیمت نشان می‌دهد Video باید در محصول با Credit و Tier کنترل شود.

---

## 50.3 OpenAI

OpenAI برای تحلیل و Creative Reasoning گزینه قدرتمندی است و می‌تواند در لایه Creative Engine استفاده شود.

در معماری محصول نباید Business Logic مستقیماً به یک مدل OpenAI وابسته شود؛ Provider باید قابل تعویض باشد.

برای GPT-5.6 Luna، قیمت فعلی نمونه:

```text
Input  → $0.20 / 1M tokens
Output → $1.20 / 1M tokens
```

با همان فرض 1,500 ورودی و 500 خروجی:

```text
Input  = $0.00030
Output = $0.00060
-----------------
Total  = $0.00090
```

### Image

OpenAI در حال حاضر GPT-Image-2 را به عنوان مدل جدید تولید/ویرایش تصویر معرفی کرده است. قیمت دقیق per-image آن باید مستقیماً از Pricing API رسمی و در زمان پیاده‌سازی بررسی شود و نباید با قیمت مدل‌های قدیمی اشتباه گرفته شود.

برای مقایسه تاریخی، مدل chatgpt-image-latest قیمت‌هایی در حدود $0.009 تا $0.20 برای خروجی‌های مختلف داشته، اما این قیمت‌ها نباید به عنوان قیمت GPT-Image-2 در Business Plan استفاده شوند.

### Video

Sora 2 در قیمت فعلی حدود:

```text
$0.10 / sec
```

اما Sora 2 و Sora 2 Pro در حال حاضر در مسیر Deprecation قرار دارند و API آن‌ها طبق مستندات فعلی برای 24 سپتامبر 2026 برنامه خاموشی دارد. بنابراین نباید Sora 2 را به عنوان Provider اصلی و بلندمدت معماری انتخاب کرد.

---

## 50.4 Anthropic / Claude

Claude برای این محصول به عنوان Image/Video Generator اصلی مناسب نیست.

نقش مناسب Claude:

```text
Image Analysis
Creative Reasoning
Creative Brief
Prompt Engineering
Marketing Copy
Campaign Strategy
```

مثلاً Anthropic در مستندات Vision خود نمونه‌ای ارائه می‌کند که تحلیل یک تصویر 1000×1000 با Claude Haiku 4.5 حدود $0.0013 هزینه دارد.

بنابراین Claude می‌تواند یکی از Providerهای Creative Engine باشد، اما برای Generate تصویر یا ویدئو باید به Providerهای تخصصی Image/Video متصل شویم.

---

# 51. برآورد Cost واقعی یک درخواست کامل در هر دو مسیر

برای تصمیم تجاری، به جای نگاه کردن به یک API، Cost Envelope را به تفکیک دو مسیر اصلی تعریف می‌کنیم:

## 51.1 مسیر اول: درخواست تولید تصویر (In-App Image Generation)

سناریو:

```text
کاربر عکس محصول را Upload می‌کند
        ↓
AI تصویر و مشخصات صنف را تحلیل می‌کند
        ↓
Creative Brief ساخته می‌شود
        ↓
Prompt داخلی و تنظیمات کامپوزیت ساخته می‌شود
        ↓
مدل Image Generation اجرا می‌شود
        ↓
خروجی ذخیره و ارائه می‌شود
```

یک برآورد عملی برای MVP:

| بخش | Cost تقریبی |
|---|---:|
| Image Analysis | $0.001 |
| Creative / Prompt Generation | $0.001 |
| Image Generation (Provider) | $0.02 – $0.06 |
| Infrastructure / Storage | $0.002 – $0.005 |
| Retry / Failure Reserve | $0.004 – $0.012 |
| **Total Planning Cost** | **$0.028 – $0.079** |

سطوح متناظر:

```text
Economic Image   ≈ $0.03
Standard Image   ≈ $0.05
Premium Image    ≈ $0.10+
```

---

## 51.2 مسیر اول: درخواست تولید ویدئو با مدت انتخابی (In-App Video Generation)

سناریو:

```text
Upload Product
      ↓
Image Analysis
      ↓
Creative Brief
      ↓
Video Prompt & Camera Direction
      ↓
Image-to-Video Engine
      ↓
Video with User-selected Duration
      ↓
Storage / Delivery
```

برآورد هزینه:

```text
Generation       = duration × provider price per second ($0.05 to $0.40/sec)
Analysis + Prompt≈ $0.002
Infrastructure   = تابع مدت و حجم خروجی
Retry Reserve    = تابع هزینه Generation و نرخ شکست
--------------------------------
Total            = Generation + Analysis + Prompt + Infrastructure + Retry Reserve
```

این تفاوت قیمت مستلزم دریافت اعتبارات پویا (Y Credits) از کاربر است.

---

## 51.3 مسیر دوم: درخواست فقط پرامپت حرفه‌ای (Prompt-Only Compilation)

سناریو:

```text
Upload Product
      ↓
تحلیل مشخصات، مقیاس و جزئیات صنف (طلا، ساعت، کفش و...)
      ↓
Creative Brief بازاریابی
      ↓
کامپایل و بهینه‌سازی پرامپت برای سینتکس مدل انتخابی (Midjourney, Flux, ChatGPT, Claude, SD)
      ↓
تحویل پرامپت خودکفا با تثبیت مقیاس فیزیکی و هندسه کالا جهت کپی توسط کاربر
```

برآورد هزینه متغیر داخلی:

| بخش | Cost تقریبی |
|---|---:|
| Image & Category Analysis (Lightweight LLM) | $0.0003 – $0.0005 |
| Prompt Compilation & Formatting | $0.0002 – $0.0004 |
| Image/Video Rendering | **$0.00 (رندر توسط کاربر)** |
| Storage & Delivery (متن سبک) | $0.0001 |
| Retry / Failure Reserve | نزدیک به صفر |
| **Total Planning Cost** | **$0.0006 – $0.0010** |

> **نتیجه کلیدی:** هزینه متغیر یک پرامپت حرفه‌ای کمتر از یک سنت (حدود ۱/۵۰ هزینه تولید تصویر) است! بنابراین با دریافت اعتبارات اندک (۱، ۲ یا ۵ Credit)، حاشیه سود پلتفرم به **بیش از ۸۵٪ تا ۹۵٪** می‌رسد.

---

# 52. Cost Envelope پیشنهادی برای محصول (تفکیک دو مسیر)

برای نسخه MVP جدول Cost داخلی به این صورت است:

| نوع خدمت | حداقل برنامه‌ریزی | Cost معمول | Premium |
|---|---:|---:|---:|
| **Simple Prompt (پرامپت ساده)** | $0.0003 | $0.0005 | $0.0008 |
| **Image Prompt (پرامپت تخصصی عکس)** | $0.0005 | $0.0008 | $0.0015 |
| **Video Prompt (پرامپت تخصصی ویدیو)** | $0.0008 | $0.0012 | $0.0025 |
| **Image Generation (تولید تصویر با AI ما)** | $0.03 | $0.05 | $0.10+ |
| **Video Generation (تولید ویدیو با AI ما)** | پویا بر اساس مدت و مدل | پویا بر اساس مدت و مدل | پویا بر اساس مدت و مدل |

> **اصل محوری:** Credit واحد مشترک ارزش است؛ خدمات کم‌هزینه (پرامپت) با Credit کم، و خدمات پرهزینه (رندر GPU تصویر/ویدیو) با Credit متناسب و قابل تنظیم در پنل ادمین تسویه می‌شوند.

---

# 53. Credit System بر اساس Cost و ارزش (Universal Credit Architecture)

Credit در پلتفرم حله صرفاً یک شمارنده فایل تصویری نیست، بلکه **واحد ارزش قابل مصرف** است:

```text
Credit ≠ فقط واحد تولید تصویر/ویدئو

Credit = واحد ارزش قابل مصرف در پلتفرم
```

جدول مصرف Credit در فاز فعلی:

```text
Simple Prompt       → 1 Credit
Image Prompt        → 2 Credits
Video Prompt        → 5 Credits

Image Generation    → X Credits (پیش‌فرض ۱۰ تا ۲۵ اعتبار - قابل تنظیم توسط ادمین)
Video Generation    → Y Credits (پویا بر اساس مدت و مدل - قابل تنظیم توسط ادمین)
Image Utility Tools → ۵ تا ۱۵ Credits (حذف بک‌گراند، آپ‌اسکیل، واریانت‌ها)
```

> **نکته مدیریتی بسیار مهم:** مقادیر دقیق X و Y هرگز در کد به صورت صلب هاردکد نمی‌شوند، بلکه به عنوان متغیرهای پیکربندی در فایل تنظیمات (`config/credits.php`) و دیتابیس قرار دارند تا مدیر سیستم بر اساس نوسان قیمت ارز و تعرفه Providerها بتواند آن‌ها را در هر لحظه ویرایش کند.

---

# 54. Unit Economics واقعی و ثبت تفکیک‌شده

برای هر تراکنش در سیستم (چه تولید عکس/ویدیو و چه کامپایل پرامپت)، یک رکورد حسابداری مصرفی دقیق در دیتابیس ثبت می‌شود:

```text
Transaction ID
User ID
Organization ID
Delivery Path (in_app_generation | prompt_only)
Target Model / Compiler (Imagen 4, Veo 3.1, Midjourney v6.1, Flux.1, etc.)
Product Category (jewelry, watches, shoes, etc.)
Input / Output Tokens
Provider Cost ($)
Analysis Cost ($)
Prompt Compilation Cost ($)
Infrastructure & Storage Cost ($)
Total Internal Cost ($)
Credits Charged
Revenue Allocated (تومان / $)
Gross Margin (%)
Processing Time (ms)
Status (succeeded | failed | refunded)
```

## مقایسه سلامت مالی دو مسیر:

| شاخص | مسیر ۱: 🎨 تولید محتوا با AI ما | مسیر ۲: ✍️ فقط پرامپت حرفه‌ای |
|---|:---:|:---:|
| **متوسط هزینه متغیر (Cost)** | $0.03 – $0.10 (تصویر) / وابسته به ثانیه (ویدیو) | $0.0006 – $0.001 |
| **متوسط اعتبار دریافتی (Credits)** | X (۱۰ الی ۲۵) / Y (پویا) | ۱ الی ۵ Credit |
| **حاشیه سود ناخالص هدف (Gross Margin)** | **۵۰٪ تا ۷۰٪** | **۸۵٪ تا ۹۵٪** |
| **ریسک نوسان هزینه زیرساخت** | متوسط تا بالا (نوسان GPU و Provider) | بسیار پایین و قابل اغماض |
| **مخاطب اصلی** | فروشگاه‌داران عادی بدون مهارت هوش مصنوعی | طراحان، ادمین‌ها و دارندگان اکانت‌های خارجی AI |

این ترکیب دوگانه، پایداری مالی پلتفرم را از روز اول تضمین می‌کند؛ چرا که جریان نقدینگی مسیر پرامپت حاشیه سود بسیار بالایی ایجاد می‌کند و ریسک هزینه مسیر تولید را پوشش می‌دهد.

---

# 55. Model Router بر اساس قیمت و کیفیت

Model Router فقط نباید بگوید «بهترین مدل چیست؟».

باید مسئله را به شکل Optimization حل کند:

```text
User Requirement
        ↓
Quality Requirement
        ↓
Format Requirement
        ↓
Speed Requirement
        ↓
Budget / Credit Limit
        ↓
Model Router
        ↓
Best Cost/Quality Model
```

مثلاً:

```text
Simple Product Image
→ Economic Image Model

Premium Product Hero
→ High Quality Image Model

Fast Reel
→ Fast Video Model

Premium Campaign Reel
→ Premium Video Model
```

در نتیجه کاربر لازم نیست بداند خروجی توسط کدام Provider ساخته شده است.

### مسیریابی در مسیر دوم (Prompt Compiler Router)
در مسیر «فقط پرامپت حرفه‌ای می‌خواهم»، Model Router به عنوان یک **Compiler Router** عمل می‌کند:
- پرامپت را متناسب با سینتکس و پارامترهای اختصاصی مدل مقصد کاربر کامپایل می‌کند:
  - **Midjourney v6.1:** تزریق `--ar 4:5 --style raw --v 6.1`، تفکیک نورپردازی و پارامترهای منفی (`--no blur, watermark, distortion`).
  - **FLUX.1 [dev]:** ساخت پرامپت‌های نثری دقیق، شات ماکرو و حفظ بافت واقعی متریال.
  - **ChatGPT / DALL-E 3:** فرمت طبیعی داستان‌پردازانه با دستور صریح حفظ هندسه و نگین.
  - **Claude / SD:** ساختار بلوک‌بندی‌شده و منفی‌های تفکیک‌شده.
- قوانین مقیاس فیزیکی صنف (طلا، ساعت، کفش، عطر و...) به صورت خودکفا در متن پرامپت قفل می‌شوند.

---

# 56. Strategy پیشنهادی Provider برای MVP

برای شروع بهتر است تعداد Providerها محدود باشد.

### Analysis / Creative
```text
Primary   → Google Gemini (Flash-Lite / 2.5 Flash)
Fallback  → OpenAI / Claude
```

### Image Generation (مسیر اول)
```text
Primary Economic → Imagen 4 Fast
Primary Standard → Imagen 4
Editing / Inpaint→ Google Image Edit (Gemini 2.5 Flash Edit)
```

### Video Generation (مسیر اول)
```text
Primary Economic → Veo Fast / Lite
Standard         → Veo Fast 1080p
Premium          → Veo Standard
```

### Prompt Compilers (مسیر دوم)
```text
Compilers        → 12 Target AI Models (Midjourney, Flux, Imagen, Veo, ChatGPT, Claude, SD, etc.)
```

این ساختار از طریق Adapterها و Compilerها پیاده‌سازی می‌شود تا افزودن مدل‌های جدید بدون تغییر هسته سیستم انجام شود.

---

# 57. قانون Pricing محصول و توزیع ارزش دوگانه

قیمت‌گذاری محصول صرفاً بر اساس تعداد فایل محاسبه نمی‌شود؛ بلکه بر مبنای ارزش تحویلی و هزینه متغیر دو مسیر تعیین می‌شود:

```text
Customer Value (هوش بازاریابی + تسلط صنفی)
      +
Internal Cost (GPU برای تصویر/ویدیو vs توکن سبک برای پرامپت)
      +
Desired Gross Margin (۵۰٪–۷۰٪ برای ساخت مستقیم | ۸۵٪–۹۵٪ برای پرامپت)
```

### چرا مسیر پرامپت استراتژیک است؟
اگر یک کاربر اکانت میدجورنی یا چت‌جی‌پی‌تی ماهانه ۲۰ دلاری دارد، حاضر نیست دوباره برای یک ابزار رندر تصویر پول کامل بپردازد. اما حاضر است مبالغی برای دریافت **پرامپت مهندسی‌شده و بی‌نقص صنفی** بدهد تا وقتش تلف نشود.
این جریان کاربرانی را به مشتری تبدیل می‌کند که در حالت عادی اصلاً مشتری AI Generatorها نبودند!

---

# 58. Pricing اولیه پیشنهادی برای تست بازار (Credit-Based Bundles)

در این مدل، کاربر در هر پلن **Credit** دریافت می‌کند که می‌تواند آن را در هر دو مسیر (یا به صورت ترکیبی) مصرف کند:

### Free (آزمایشی پس از احراز هویت موبایل)
```text
اعتبار: ۲۰ Credit
کاربرد: ۱ تصویر استاندارد با AI ما (مسیر ۱) یا ۱۰ پرامپت حرفه‌ای صنفی (مسیر ۲)
محدودیت: واترمارک روی تصاویر خروجی AI داخلی، صف استاندارد
```

### Starter — حدود ۴۹۹,۰۰۰ تومان (یا $9.99) در ماه
```text
اعتبار: ۲۰۰ Credits
قابلیت مصرف:
  - تا ۱۰۰ پرامپت حرفه‌ای عکس (مسیر ۲)
  - یا ۱۰ الی ۱۵ تصویر تبلیغاتی با AI ما (مسیر ۱)
  - یا ترکیبی از پرامپت و تصویر
ویژگی: بدون واترمارک، کاتالوگ محصولات، ذخیره‌سازی ابری
```

### Creator — حدود ۱,۲۹۹,۰۰۰ تومان (یا $24.99) در ماه
```text
اعتبار: ۵۰۰ Credits
قابلیت مصرف:
  - تا ۲۵۰ پرامپت حرفه‌ای عکس یا ۵۰ پرامپت کارگردانی ویدیو
  - یا ۳۰ تا ۴۰ تصویر تبلیغاتی + ویدیوهای کوتاه
ویژگی: اولویت صف بالا، Brand Kit، خروجی چندکاناله (پست، استوری، ریلز)، ویرایش تعاملی
```

### Pro — حدود ۲,۴۹۹,۰۰۰ تومان (یا $49.99) در ماه
```text
اعتبار: ۱۵۰۰ Credits
قابلیت مصرف: حجم بالای تولید تصویر، ویدیوهای طولانی‌تر و پرامپت‌های کمپین کامل
ویژگی: دسترسی به مدل‌های پرچمدار، Campaign Generator، پشتیبانی ویژه
```

### بسته شارژ اعتباری آزاد (Top-Up Packs — بدون نیاز به تمدید ماهانه)
```text
Top-up 50   → ۵۰ Credit (مناسب نیازهای لحظه‌ای یا تست پرامپت)
Top-up 150  → ۱۵۰ Credit
Top-up 400  → ۴۰۰ Credit
```

> **اصل کلیدی:** مقادیر Credit هر خدمت (X و Y برای تصویر/ویدیو، و ۱، ۲ و ۵ برای پرامپت‌ها) در تنظیمات سیستم (`config/credits.php`) توسط مدیر سیستم قابل تنظیم است.

---

# 59. نکته بسیار مهم درباره Unlimited

پیشنهاد اکید:

```text
Unlimited Image
```

نیز با احتیاط عرضه شود و:

```text
Unlimited Video
```

در MVP ارائه نشود.

حتی اگر از عبارت Unlimited استفاده شود، باید Fair Usage Policy، Queue Policy و Abuse Protection وجود داشته باشد.

مدل امن‌تر:

```text
Subscription
+
Included Credits
+
Top-up Credits
```

است.

---

# 60. معماری مالی پیشنهادی

سیستم باید از همان ابتدا بتواند Cost را به صورت Real-Time یا Near Real-Time محاسبه کند.

```text
Generation Request
       ↓
Cost Estimator
       ↓
Credit Reservation
       ↓
AI Generation
       ↓
Actual Provider Cost
       ↓
Credit Settlement
       ↓
Usage Ledger
```

بهتر است Credit قبل از شروع Generation Reserve شود و بعد از مشخص شدن نتیجه واقعی Settlement شود.

مثلاً:

```text
User has 100 credits

Video request
↓
Reserve 80 credits
↓
Generation succeeds
↓
Actual cost recorded
↓
Charge 80
```

و در صورت شکست:

```text
Generation Failed
↓
Credit Refund / Partial Refund
```

این ساختار جلوی بسیاری از مشکلات Billing را می‌گیرد.

---

# 61. نتیجه اقتصادی نهایی به تفکیک دو مسیر

بر اساس قیمت‌های فعلی Providerهای بررسی‌شده، برای Business Planning مبنای هزینه‌ای دو مسیر به شرح زیر است:

```text
PROMPT COMPILATION REQUEST (مسیر دوم: فقط پرامپت حرفه‌ای)
Cost ≈ $0.0006 – $0.0010
اعتبار دریافتی: ۱، ۲ یا ۵ Credit
حاشیه سود ناخالص: ۸۵٪ تا ۹۵٪

IMAGE REQUEST (مسیر اول: تولید تصویر با AI ما)
Cost ≈ $0.03 – $0.10+
اعتبار دریافتی: X Credits (پیش‌فرض ۱۰ تا ۲۵ اعتبار)
حاشیه سود ناخالص: ۵۰٪ تا ۷۰٪

VIDEO REQUEST (مسیر اول: تولید ویدیو با AI ما)
Cost = supported duration × provider price per second ($0.05 to $0.40/s) + overhead
اعتبار دریافتی: Y Credits (پویا متناسب با مدت زمان و مدل)
حاشیه سود ناخالص: ۴۰٪ تا ۶۰٪
```

اما این اعداد باید در یک Cost Engine واقعی به صورت Dynamic محاسبه شوند.

هدف معماری این نیست که یک بار قیمت را محاسبه کنیم؛ هدف این است که هر درخواست (چه تصویر/ویدیو و چه کامپایل پرامپت) دقیقاً بداند:

```text
How much did it cost us?
How many credits did the user spend?
How much revenue did we receive?
How much margin did we make?
```

این موضوع برای ادامه پایداری مالی و تصمیم‌گیری درباره Pricing حیاتی است.

---

# 62. تصمیم مدیریتی جدید: Cost باید جزء Core Product باشد

تا اینجا AI Gateway و Model Router به عنوان اجزای فنی محصول تعریف شده بودند. با اضافه شدن مدل اقتصادی، مشخص می‌شود که این دو فقط اجزای فنی نیستند؛ بلکه بخشی از موتور درآمد محصول هستند.

معماری یکپارچه دو مسیر:

```text
                                User
                                 ↓
                           Creative Engine
                                 ↓
                           Cost Estimator
                                 ↓
                            Model Router
                       ┌─────────┴─────────┐
                       ↓                   ↓
            🎨 مسیر تولید با AI       ✍️ مسیر کامپایل پرامپت
                 ↙     ↓     ↘               ↓
             Cheap  Standard Premium    12 Target Model Compilers
                 \     |     /       (Midjourney, Flux, GPT, etc.)
                   AI Providers              ↓
                       ↓             تحویل پرامپت خودکفا به کاربر
                   Generation                │
                       └─────────┬───────────┘
                                 ↓
                            Usage Ledger
                                 ↓
                          Credit Settlement
                                 ↓
                          Business Metrics
```

این معماری باعث می‌شود محصول بتواند همزمان:

- کیفیت را در هر دو شیوه تحویل کنترل کند؛
- هزینه را در مسیر تولید مدیریت کرده و از حاشیه سود بالای پرامپت منتفع شود؛
- Provider و کامپایلرها را بدون دستکاری هسته منطق تغییر دهد؛
- Pricing را با تنظیم متغیرهای X و Y بهینه کند؛
- و در آینده با ورود مدل‌های جدید، سریع Adapt شود.

---

# 63. تغییر مهم در Roadmap

بر اساس اقتصاد واقعی AI، این قابلیت‌ها باید زودتر از آنچه در Roadmap اولیه آمده‌اند ساخته شوند:

```text
MVP
├── Credit Ledger (تراکنش‌های ریالی و اعتباری)
├── Universal Credit Manager (پشتیبانی از هر دو مسیر)
├── Provider Cost Tracking
├── Generation & Prompt Compilation Cost Tracking
├── Retry Tracking
├── Model Router & Prompt Compilers
└── Usage Analytics
```

این موارد نباید به Phaseهای خیلی دیر منتقل شوند، چون بدون آن‌ها نمی‌دانیم هر مشتری واقعاً چقدر برای ما هزینه ایجاد می‌کند.

---

# 64. تصمیم نهایی برای MVP اقتصادی و دو مسیر اصلی

نسخه MVP پیشنهادی از نظر تجاری:

```text
                           Product Upload
                                 ↓
                         AI Image Analysis
                                 ↓
                           Creative Brief
                                 ↓
                        انتخاب مسیر توسط کاربر
                        ┌────────┴────────┐
                        ↓                 ↓
              🎨 تولید محتوا با AI   ✍️ فقط پرامپت حرفه‌ای
                        ↓                 ↓
                   Model Router     Prompt Compilers
                        ↓                 ↓
              تولید تصویر / ویدیو     پرامپت کالیبره‌شده برای مدل کاربر
                        ↓                 ↓
                 Post Processing          ↓
                        └────────┬────────┘
                                 ↓
                         Credit Settlement
                                 ↓
                        Download / Copy
```

و در Backend:

```text
Laravel
├── Creative Engine (Domain)
│   ├── Category Studios & Presets
│   ├── Prompt Compilers (12 External Models)
│   └── Compositor (GD & Masks)
├── AI Gateway (Infrastructure)
├── Model Router & Estimator
├── Provider Adapters (Imagen, Veo, Gemini)
├── Cost Engine
├── Credit Ledger & Billing
├── Queue
└── Analytics
```

این ساختار برای شروع به اندازه کافی ساده است که داخل یک Laravel Modular Monolith ساخته شود و در عین حال برای Scale کردن آینده مناسب باقی بماند.

---

# 65. منابع قیمت‌گذاری و اعتبارسنجی

قیمت‌ها در این بخش بر اساس مستندات و صفحات رسمی Providerها در زمان تهیه این نسخه بررسی شده‌اند. چون قیمت AI به سرعت تغییر می‌کند، قبل از Launch باید یک Pricing Verification انجام شود.

Providerهای اصلی مورد بررسی:

- Google Gemini / Imagen / Veo
- OpenAI GPT / GPT-Image / Sora
- Anthropic Claude

**قاعده اجرایی:** قبل از هر تغییر Pricing مشتری، قیمت Providerها و Cost Engine داخلی باید دوباره بررسی شوند.

</div>
