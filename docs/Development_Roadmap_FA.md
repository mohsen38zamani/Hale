<div dir="rtl">

# نقشه راه کدنویسی — Hale (AI Content Studio)

**نوع سند:** Development Roadmap / Implementation Roadmap  
**نسخه:** 1.1  
**تاریخ:** ۱۴۰۴/۰۶/۱۷  
**وضعیت:** پیش‌نویس اولیه  
**مرجع محصول:** [`MVP_Specification_FA.md`](MVP_Specification_FA.md)

---

## درباره این سند

برنامه زمانی و فنی توسعه Hale — **چه چیزهایی، به چه ترتیبی، با چه تکنولوژی** ساخته می‌شوند.

مشخصات محصول Phase 1 در سند جداگانه [`MVP_Specification_FA.md`](MVP_Specification_FA.md) آمده است.

---

## اصول راهنمای توسعه

1. **Modular Monolith** — یک Laravel app با مرزهای Domain مشخص، نه Microservice از روز اول
2. **Async-first** — همه Generationها از Queue عبور کنند
3. **Provider Abstraction** — Business Logic به هیچ AI Provider خاصی وابسته نباشد
4. **Cost-aware** — هزینه هر Generation از روز اول Track شود
5. **Mobile-first PWA** — مخاطب اصلی روی موبایل است
6. **Measure everything** — KPIهای فنی از Phase 1 فعال باشند

### وضعیت اجرای فعلی

علامت `✅` یعنی Backend آن بخش پیاده‌سازی و تست شده است؛ نبودن علامت به معنی باقی‌ماندن کار یا تکمیل‌نبودن بخش Frontend/عملیاتی است.

---

## نمای کلی فازها

```text
Phase 0 ──► Phase 1 ──► Phase 2 ──► Phase 3 ──► Phase 4 ──► Phase 5
Validation   Core Product   Retention    Marketing    B2B/Agency   Automation
(۲–۳ هفته)   (۸–۱۲ هفته)   (۶–۸ هفته)   (۸–۱۰ هفته)  (۱۰–۱۲ هفته) (آینده)
```

---

# Phase 0 — آماده‌سازی و Validation فنی
**مدت تقریبی:** ۲–۳ هفته  
**هدف:** ریسک‌های بحرانی را حل و زیرساخت توسعه را آماده کنیم

## 0.1 تصمیم‌های معماری (هفته ۱)

| # | کار | خروجی |
|---|-----|--------|
| 0.1.1 | انتخاب Stack نهایی | Laravel 11+, PHP 8.3+, MySQL 8, Redis, Horizon |
| 0.1.2 | طراحی ساختار پوشه‌های Domain | سند `docs/architecture/domain-structure.md` |
| 0.1.3 | طراحی ERD اولیه | Migration plan برای Phase 1 |
| 0.1.4 | انتخاب Object Storage | S3-compatible (MinIO local / Arvan / Liara) |
| 0.1.5 | انتخاب Frontend approach | PWA با Livewire/Inertia + Vue یا Blade + Alpine |

## 0.2 ریسک AI Provider (بحرانی — هفته ۱–۲)

| # | کار | خروجی |
|---|-----|--------|
| 0.2.1 | بررسی دسترسی Providerها از ایران | گزارش Go/No-Go برای هر Provider |
| 0.2.2 | تست API: Gemini, Imagen, Veo | POC ساده: یک عکس + یک ویدئو |
| 0.2.3 | طراحی Fallback Strategy | Open-weight self-host یا زیرساخت خارج |
| 0.2.4 | پیاده‌سازی POC برای AI Gateway | یک Script/Service تست Provider Abstraction |

## 0.3 زیرساخت توسعه (هفته ۲)

| # | کار | خروجی |
|---|-----|--------|
| 0.3.1 | Setup Docker Compose (local) | PHP, MySQL, Redis, MinIO, Mailpit |
| 0.3.2 | Setup CI/CD پایه | GitHub Actions: lint, test, deploy staging |
| 0.3.3 | Setup Staging environment | سرور staging با SSL |
| 0.3.4 | Setup Error Tracking | Sentry یا مشابه |
| 0.3.5 | Setup Git branching strategy | `main` → staging, `develop` → feature branches |

## 0.4 Validation تجاری (موازی با فنی)

| # | کار | خروجی |
|---|-----|--------|
| 0.4.1 | Landing Page ساده | صفحه جمع‌آوری ایمیل + Demo |
| 0.4.2 | ۲۰ Outreach به ICP | لیست مشتریان + بازخورد |
| 0.4.3 | Content Policy + ToS اولیه | سند حقوقی ساده |
| 0.4.4 | انتخاب درگاه پرداخت | زرین‌پال / آی‌دی‌پی — تست Recurring |

### ✅ معیار خروج Phase 0
- [ ] حداقل یک Provider تصویر و یک Provider ویدئو تست شده
- [ ] Docker local بالا می‌آید
- [ ] ERD و Domain Structure تأیید شده
- [ ] ۱۰+ مکالمه با مشتری بالقوه انجام شده

---

# Phase 1 — Core Product
**مدت تقریبی:** ۸–۱۲ هفته  
**مرجع محصول:** [`MVP_Specification_FA.md`](MVP_Specification_FA.md)

## Sprint 1 — Foundation (هفته ۱–۲)

### Backend
```
app/
├── Domains/
│   ├── Auth/
│   ├── Users/
│   └── Shared/
```

| # | Feature | Tasks |
|---|---------|-------|
| 1.1 | Laravel Project Setup | Install, config, .env template |
| 1.2 | Database Migrations | users, organizations (schema only), sessions |
| 1.3 | Authentication | ✅ Register، Login با email/phone، Logout، Password Reset |
| 1.4 | Email Verification | Optional, recommended |
| 1.5 | API Auth (Sanctum) | ✅ Token-based for PWA با email یا phone |
| 1.6 | Base API Response Format | ✅ Standard JSON envelope |
| 1.7 | Exception Handling | Global handler, error codes |

### Frontend
| # | Feature | Tasks |
|---|---------|-------|
| 1.8 | PWA Shell | Layout, navigation, RTL support |
| 1.9 | Auth Pages | Login, Register, Forgot Password |
| 1.10 | Landing Page | Hero, CTA, pricing preview |

### DevOps
| # | Feature | Tasks |
|---|---------|-------|
| 1.11 | PHPUnit Setup | Base test structure |
| 1.12 | Feature Tests | ✅ Auth، Phone OTP و Profile flow tests |

---

## Sprint 2 — Products & Media (هفته ۳–۴)

### Backend
```
app/Domains/
├── Products/
├── Media/
```

| # | Feature | Tasks |
|---|---------|-------|
| 2.1 | Product Model | name, description, user_id, status |
| 2.2 | ProductAsset Model | original image, thumbnails |
| 2.3 | Media Upload Service | Validation, resize, storage to S3 |
| 2.4 | Product CRUD API | Create, Read, Update, Delete, List |
| 2.5 | Product Library API | Paginated list with thumbnails |
| 2.6 | Image Processing | Thumbnail generation, max size limits |

### Frontend
| # | Feature | Tasks |
|---|---------|-------|
| 2.7 | Upload Product Page | Drag & drop, camera capture (mobile) |
| 2.8 | Product Library Page | Grid view, search, delete |
| 2.9 | Product Detail Page | View, edit name, re-upload image |

### Database Tables
```sql
products
product_assets
media_assets
```

---

## Sprint 3 — Creative Engine (هفته ۵–۶)

### Backend
```
app/Domains/
├── Creative/
├── Generations/
```

| # | Feature | Tasks |
|---|---------|-------|
| 3.1 | CreativeProject Model | product_id, goal, style, format, video_duration_seconds, settings (JSON) |
| 3.2 | Goal/Style/Format Enums | Predefined options (not free text) |
| 3.3 | Creative Brief Generator | Input → structured brief (via LLM) |
| 3.4 | Prompt Generator | Brief → optimized prompt |
| 3.5 | "Auto Best" Mode | System selects style/environment/camera |
| 3.6 | Generation Model | status, type, credits_used, metadata |
| 3.7 | Generation API | POST /generations, GET /generations/{id} |

### Frontend
| # | Feature | Tasks |
|---|---------|-------|
| 3.8 | Creative Builder Wizard | Step-by-step: Product → Goal → Style → Format → Video Duration |
| 3.9 | Visual Style Picker | Card-based selection (not text input) |
| 3.10 | Preview Settings | Show what system will generate |
| 3.11 | "Auto Best" Button | One-click generation |

### Database Tables
```sql
creative_projects
generations
generation_settings
```

---

## Sprint 4 — AI Gateway & Generation Pipeline (هفته ۷–۸)

### Backend
```
app/Domains/
├── AI/
│   ├── Gateway/
│   ├── Router/
│   ├── Providers/
│   │   ├── Google/
│   │   └── Contracts/
│   └── Jobs/
```

| # | Feature | Tasks |
|---|---------|-------|
| 4.1 | AI Gateway Service | Single entry point for all AI calls |
| 4.2 | Provider Interface | `ImageGenerator`, `VideoGenerator`, `ImageAnalyzer`؛ اعلام مدت‌های پشتیبانی‌شده هر Video Provider |
| 4.3 | Google Provider Adapter | Gemini (analysis), Imagen (image), Veo (video) |
| 4.4 | Model Router | Select model based on type, quality, duration support, cost |
| 4.5 | Generation Job (Queue) | Async processing with Horizon |
| 4.6 | Webhook Handler | Provider callback, idempotent |
| 4.7 | Generation Status Updates | queued → processing → completed/failed |
| 4.8 | Retry Logic | Configurable retries with backoff |
| 4.9 | Cost Tracking | Log provider, model, tokens, cost per generation |
| 4.10 | Content Moderation (basic) | Pre-generation prompt filter |

### Frontend
| # | Feature | Tasks |
|---|---------|-------|
| 4.11 | Generation Progress UI | Polling/WebSocket, progress bar |
| 4.12 | Generation Result Page | Preview, download, regenerate |
| 4.13 | Generation History | List past generations with filters |
| 4.14 | Error States | Failed generation, retry button |

### Infrastructure
| # | Feature | Tasks |
|---|---------|-------|
| 4.15 | Queue Workers | Horizon config, worker scaling |
| 4.16 | Failed Job Handling | Dead letter queue, alerting |

### Database Tables
```sql
ai_providers
ai_models
generation_jobs
usage_logs
```

---

## Sprint 5 — Credits & Billing (هفته ۹–۱۰)

### Backend
```
app/Domains/
├── Credits/
├── Billing/
```

| # | Feature | Tasks |
|---|---------|-------|
| 5.1 | Credit Model | ✅ balance, reserved, lifetime_used |
| 5.2 | Credit Transaction Ledger | ✅ All credit movements logged، شامل bonus و purchase |
| 5.3 | Credit Reservation | ✅ Estimate by video duration/model، reserve before generation، settle after |
| 5.4 | Credit Refund | ✅ On failed generation |
| 5.5 | Subscription Plans | ✅ Free، Starter، Creator (config-based) |
| 5.6 | Subscription Model | ✅ plan، status، starts_at و ends_at |
| 5.7 | Payment Gateway Integration | ✅ Zarinpal adapter پیش‌فرض + Fake provider برای تست |
| 5.8 | Payment Webhook | ✅ Verify، activate subscription، add credits؛ callback رسمی زرین‌پال نیز فعال است |
| 5.9 | Invoice/Receipt | Basic payment history |
| 5.10 | Usage Limits | ⚠️ محدودیت ویدئو بر اساس پلن پیاده شده؛ image limit و renewal باقی‌مانده |
| 5.11 | Anti-Fraud (basic) | ✅ Phone OTP برای فعال‌سازی Credit رایگان |

### Frontend
| # | Feature | Tasks |
|---|---------|-------|
| 5.12 | Credit Balance Display | Header widget |
| 5.13 | Pricing Page | Plans comparison |
| 5.14 | Checkout Flow | Select plan → payment → confirmation |
| 5.15 | Payment History | List transactions |
| 5.16 | Low Credit Warning | Notification when credits low |
| 5.17 | Paywall | Block generation when no credits |

### Database Tables
```sql
credits
credit_transactions
subscriptions
payments
plans (config or table)
```

---

## Sprint 6 — Polish & Launch Prep (هفته ۱۱–۱۲)

| # | Feature | Tasks |
|---|---------|-------|
| 6.1 | Admin Panel (minimal) | View users, generations, refund credits |
| 6.2 | Notifications | Email: generation ready, low credits, subscription |
| 6.3 | Feedback Loop | 👍/👎 after generation |
| 6.4 | Download & Export | Download image/video, share link |
| 6.5 | Watermark (Free plan) | Add watermark to free tier outputs |
| 6.6 | Rate Limiting | API rate limits per user/plan |
| 6.7 | Circuit Breaker | Daily AI cost budget alert |
| 6.8 | Logging & Monitoring | Structured logs, basic dashboards |
| 6.9 | Security Audit | API key protection, input validation |
| 6.10 | Performance | Query optimization, caching |
| 6.11 | E2E Tests | Critical user flows |
| 6.12 | Documentation | API docs, deployment guide |
| 6.13 | Soft Launch | Deploy + beta user onboarding |

### ✅ معیار خروج Phase 1
- [ ] تمام Sprintهای ۱–۶ تکمیل شده
- [ ] CI/CD و staging پایدار
- [ ] Generation pipeline end-to-end کار می‌کند
- [ ] Billing و Credit ledger تست شده
- [ ] Admin panel برای عملیات پشتیبانی آماده است

---

# Phase 2 — Productization (Retention)
**مدت تقریبی:** ۶–۸ هفته  
**هدف:** افزایش Retention و استفاده مجدد

| Sprint | Feature | Tasks |
|--------|---------|--------|
| 2.1 | Brand Kit | Models, API, UI — logo, colors, font, tone |
| 2.2 | Templates | Pre-built scenarios, template engine |
| 2.3 | AI Editing | Conversational edit pipeline |
| 2.4 | Creative Suggestions | LLM-based idea generation |
| 2.5 | Generation Variants | Variant model + regenerate flow |
| 2.6 | Onboarding by Persona | Branching signup flow |
| 2.7 | Improved History | Search, filter, favorites, collections |
| 2.8 | PWA Enhancements | Service worker, push notifications |

---

# Phase 3 — Marketing Platform (Revenue Expansion)
**مدت تقریبی:** ۸–۱۰ هفته  
**هدف:** Campaign و تولید محتوای bulk

| Sprint | Feature | Tasks |
|--------|---------|--------|
| 3.1 | Campaign Generator | Multi-asset orchestration |
| 3.2 | Campaign Orchestration | Saga pattern, partial failure handling |
| 3.3 | Caption Generator | Persian caption + hashtag service |
| 3.4 | Content Calendar | Calendar model, scheduling UI |
| 3.5 | Bulk Generation | Batch queue jobs |
| 3.6 | Persian Localization | Jalali, Iranian occasions |
| 3.7 | Content Ideas Engine | AI suggestion API |

---

# Phase 4 — B2B / Agency
**مدت تقریبی:** ۱۰–۱۲ هفته  
**هدف:** Multi-tenant، تیم‌ها و آژانس‌ها

| Sprint | Feature | Tasks |
|--------|---------|--------|
| 4.1 | Organizations & Workspaces | Multi-tenant architecture, tenant scoping |
| 4.2 | Team Members | Invite, RBAC (admin, editor, viewer) |
| 4.3 | Client Management | Agency-client relationship model |
| 4.4 | Approval Workflow | Comment / Approve / Reject states |
| 4.5 | Brand Governance | Multi-brand per organization |
| 4.6 | Usage Analytics | Admin dashboards, usage reports |
| 4.7 | Public API | REST API, API keys, rate limits |
| 4.8 | White Label (basic) | Custom subdomain, branding config |

---

# Phase 5 — Automation
**مدت تقریبی:** TBD  
**هدف:** اتوماسیون بازاریابی و انتشار

| Sprint | Feature | Tasks |
|--------|---------|--------|
| 5.1 | Auto Content Calendar | AI planning + scheduled generation |
| 5.2 | Social Media Integration | Instagram/TikTok connectors |
| 5.3 | Performance Suggestions | Analytics-driven recommendations |
| 5.4 | Campaign Automation | Event-triggered campaigns |
| 5.5 | AI Marketing Assistant | Chat interface, advisor agent |

---

# ساختار پوشه‌های Laravel (پیشنهادی)

```
app/
├── Domains/
│   ├── Auth/
│   │   ├── Actions/
│   │   ├── Models/
│   │   ├── Requests/
│   │   └── Services/
│   ├── Users/
│   ├── Organizations/
│   ├── Products/
│   ├── Media/
│   ├── Creative/
│   ├── Generations/
│   ├── AI/
│   │   ├── Gateway/
│   │   ├── Router/
│   │   ├── Providers/
│   │   │   ├── Contracts/
│   │   │   ├── Google/
│   │   │   └── OpenWeight/
│   │   └── Jobs/
│   ├── Credits/
│   ├── Billing/
│   ├── Campaigns/
│   ├── Notifications/
│   └── Admin/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Resources/
└── Support/
    ├── Helpers/
    └── Traits/

config/
├── ai.php
├── credits.php
├── plans.php
└── creative.php

database/
├── migrations/
├── seeders/
└── factories/

resources/
├── js/
├── css/
└── views/

tests/
├── Feature/
│   ├── Auth/
│   ├── Products/
│   ├── Generations/
│   └── Billing/
└── Unit/
    ├── AI/
    └── Credits/
```

---

# Stack تکنولوژی

| Layer | Technology | Notes |
|-------|------------|-------|
| Backend | Laravel 11+, PHP 8.3+ | Modular Monolith |
| Frontend | PWA (Inertia + Vue 3 or Livewire 3) | Mobile-first, RTL |
| Database | MySQL 8 / MariaDB 10.6+ | Primary data store |
| Cache/Queue | Redis 7+ | Cache, sessions, queues |
| Queue Manager | Laravel Horizon | Monitor & scale workers |
| Storage | S3-compatible | Images, videos, assets |
| Search | MySQL fulltext → Meilisearch | Phase 2+ |
| Email | Mailgun/Postmark + Mailpit (local) | Transactional emails |
| Payments | Zarinpal / IDPay | Iranian gateways |
| Monitoring | Sentry + Laravel Telescope (dev) | Errors & debugging |
| CI/CD | GitHub Actions | Test, lint, deploy |
| Container | Docker Compose (local) | Dev environment |

---

# KPIهای فنی

| Metric | Target | Tool |
|--------|--------|------|
| Generation Success Rate | > 90% | Custom dashboard |
| Avg Generation Time (Image) | < 30 sec | usage_logs |
| Avg Generation Time (Video) | < 3 min | usage_logs |
| API Response Time (p95) | < 500ms | Sentry/APM |
| Queue Job Failure Rate | < 5% | Horizon |
| AI Cost per Image | Track & optimize | Cost Engine |
| AI Cost per Video | Track & optimize | Cost Engine |
| Test Coverage (critical paths) | > 70% | PHPUnit |

---

# ریسک‌های فنی و Mitigation

| ریسک | احتمال | Mitigation | Phase |
|------|--------|------------|-------|
| AI Provider block از ایران | بالا | Open-weight fallback, external infra | 0 |
| Video cost بالا | بالا | Credit system, tier limits | 1 |
| Free plan abuse | متوسط | Phone verify, IP limits | 1 |
| Payment recurring مشکل | متوسط | تست زودهنگام با Zarinpal | 0 |
| کیفیت خروجی ضعیف | متوسط | Feedback loop, model router | 1 |
| Storage cost رشد | پایین | Retention policy, CDN | 2 |
| Tenant data leak | متوسط | Tenant scoping در Repository layer | 4 |

---

*این سند زنده است و با پیشرفت پروژه به‌روزرسانی می‌شود.*

</div>
