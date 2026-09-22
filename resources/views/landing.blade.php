<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Hale استودیوی هوش مصنوعی ساخت محتوای تبلیغاتی؛ عکس محصولت را بده، تصویر و ویدئوی آماده اینستاگرام تحویل بگیر. بدون عکاس، بدون Prompt.">
    <meta name="theme-color" content="#06070B">
    <title>Hale | عکس محصولت، خروجی سینمایی و تبلیغاتی آماده انتشار</title>
    @vite(['resources/css/app.css', 'resources/css/landing.css', 'resources/js/app.js'])
</head>
<body class="landing-page">

    {{-- ============ STRIPE AURORA GLOW BACKGROUND ============ --}}
    <div class="aurora-mesh" aria-hidden="true">
        <div class="aurora-orb aurora-orb-1"></div>
        <div class="aurora-orb aurora-orb-2"></div>
        <div class="aurora-orb aurora-orb-3"></div>
    </div>

    {{-- ============ FRAMER FLOATING PILL NAVBAR ============ --}}
    <header class="framer-pill-nav">
        <a class="brand" href="/">H<span>•</span>le</a>
        <nav class="nav-links">
            <a class="nav-link" href="#simulator">استودیوی تعاملی</a>
            <a class="nav-link" href="#compare">مقایسه کیفیت</a>
            <a class="nav-link" href="#features">امکانات</a>
            <a class="nav-link" href="#pricing">تعرفه‌ها</a>
            <a class="nav-link" href="#faq">سؤالات</a>
        </nav>
        <button class="btn-aurora" data-open-auth="register">
            <span>شروع رایگان</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17l9.2-9.2M17 17V7H7"/></svg>
        </button>
    </header>

    <main>
        {{-- ============ HERO SECTION ============ --}}
        <section class="hero-wrapper" id="top">
            <div class="badge-glow">
                <span class="dot"></span>
                <span>استودیوی هوش مصنوعی عکاسی محصول · بدون نیاز به پرامپت</span>
            </div>

            <h1 class="hero-h1">
                عکس ساده محصولت،<br>
                <span class="text-gradient">محتوای تبلیغاتی فردا.</span>
            </h1>

            <p class="hero-subtitle">
                Hale عکس ساده موبایلی محصولت را می‌گیرد و تصویر لوکس استودیویی و ویدیوی آمادهٔ ریلز اینستاگرام تحویل می‌دهد. بدون عکاس، بدون دردسر هماهنگی، در چند ثانیه.
            </p>

            <div style="display: flex; gap: 16px; justify-content: center; align-items: center; margin-bottom: 60px;">
                <button class="btn-aurora" data-open-auth="register" style="padding: 14px 32px; font-size: 16px;">
                    <span>اولین خروجی‌ات را بساز</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
                <a class="btn-glass" href="#simulator" style="padding: 14px 26px; font-size: 15px;">
                    <span>شبیه‌ساز استودیو</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                </a>
            </div>

            {{-- ============ LOVABLE-STYLE INTERACTIVE STUDIO SIMULATOR ============ --}}
            <div class="studio-simulator" id="simulator">
                <div class="simulator-header">
                    <div class="simulator-dots">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="simulator-title">HALE STUDIO ENGINE — پیش‌نمایش تعاملی زنده</div>
                    <span style="font-size: 12px; color: var(--accent-emerald); display: flex; align-items: center; gap: 6px;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--accent-emerald); display: inline-block;"></span>
                        موتور فعال
                    </span>
                </div>

                <div class="simulator-body">
                    {{-- Controls --}}
                    <div class="simulator-controls">
                        <div>
                            <span class="sim-label">۱. انتخاب محصول نمونه:</span>
                            <div class="sim-btn-group" id="sim-products">
                                <button class="sim-chip active" data-sim-prod="perfume">عطر شیشه‌ای فرانسوی</button>
                                <button class="sim-chip" data-sim-prod="shoe">کتانی ورزشی مینیمال</button>
                                <button class="sim-chip" data-sim-prod="watch">ساعت هوشمند لوکس</button>
                            </div>
                        </div>

                        <div>
                            <span class="sim-label">۲. انتخاب سبک صحنه:</span>
                            <div class="sim-btn-group" id="sim-styles">
                                <button class="sim-chip active" data-sim-style="cinematic">✦ سینمایی و تاریک</button>
                                <button class="sim-chip" data-sim-style="minimal">مینیمال و پاکیزه</button>
                                <button class="sim-chip" data-sim-style="natural">نور طبیعی روز</button>
                                <button class="sim-chip" data-sim-style="neon">نئون الکتریک</button>
                            </div>
                        </div>

                        <div>
                            <span class="sim-label">۳. فرمت انتشار:</span>
                            <div class="sim-btn-group" id="sim-formats">
                                <button class="sim-chip active" data-sim-format="1:1">پست مربع (1:1)</button>
                                <button class="sim-chip" data-sim-format="9:16">ریلز و استوری (9:16)</button>
                            </div>
                        </div>

                        <div style="margin-top: auto; padding-top: 16px; border-top: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <small style="color: var(--text-muted); display: block; font-size: 11px;">هزینه محاسبه‌شده:</small>
                                <strong id="sim-cost" style="color: #FFFFFF; font-size: 15px;">۲ Credit (تولید تصویر)</strong>
                            </div>
                            <button class="btn-aurora" data-open-auth="register" style="padding: 8px 18px; font-size: 13px;">
                                ساخت همین محتوا ↗
                            </button>
                        </div>
                    </div>

                    {{-- Display Canvas --}}
                    <div class="simulator-display">
                        <div class="sim-render-card" id="sim-card">
                            <div class="sim-render-badge" id="sim-badge">
                                <span style="color: #A855F7;">✦</span>
                                <span id="sim-badge-text">سبک سینمایی لوکس</span>
                            </div>

                            {{-- Rendered Visual Element --}}
                            <div id="sim-graphic" style="text-align: center; transform: scale(1.05); transition: all 0.3s ease;">
                                <div style="width: 140px; height: 180px; margin: 0 auto; background: linear-gradient(180deg, rgba(255,255,255,0.2) 0%, rgba(139,92,246,0.3) 100%); border-radius: 20px; border: 1px solid rgba(255,255,255,0.3); backdrop-filter: blur(8px); display: flex; flex-direction: column; justify-content: flex-end; padding: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.4);">
                                    <span style="font-size: 10px; letter-spacing: 2px; color: #C084FC; font-weight: 700;">HALE</span>
                                    <strong id="sim-graphic-title" style="font-size: 14px; color: #FFFFFF; font-weight: 800;">L'EAU NOIR</strong>
                                </div>
                            </div>

                            <div class="sim-render-info">
                                <div>
                                    <strong id="sim-target-title" style="display: block; font-size: 14px; color: #FFFFFF;">عطر فرانسوی — زاویه روبرو</strong>
                                    <small id="sim-target-meta" style="color: var(--text-muted); font-size: 12px;">نورپردازی حجمی · فرمت 1:1 اینستاگرام</small>
                                </div>
                                <span style="font-size: 11px; background: rgba(168,85,247,0.15); border: 1px solid rgba(168,85,247,0.3); color: #C084FC; padding: 3px 10px; border-radius: 9999px;">
                                    Auto-Best
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ FRAMER-STYLE BEFORE / AFTER SLIDER ============ --}}
        <section class="before-after-section" id="compare">
            <div style="text-align: center; margin-bottom: 48px;">
                <div class="badge-glow" style="margin-bottom: 12px;">
                    <span>تحول کیفیت عکس محصول</span>
                </div>
                <h2 style="font-size: clamp(28px, 4vw, 44px); font-weight: 800; margin: 0 0 16px;">
                    عکس روی میز کار در برابر <span class="text-gradient">استودیوی هالیوودی</span>
                </h2>
                <p style="color: var(--text-secondary); max-width: 600px; margin: 0 auto; font-size: 15px;">
                    دستگیره وسط را به چپ و راست بکشید تا تفاوت عکس خام موبایلی را با خروجی پردازش‌شده توسط هوش مصنوعی Hale مقایسه کنید.
                </p>
            </div>

            <div class="ba-slider-container" id="ba-container">
                {{-- After Layer (AI Studio Result) --}}
                <div class="ba-layer ba-layer-after">
                    <div style="text-align: center; color: #FFFFFF; padding: 40px;">
                        <div style="display: inline-block; background: rgba(139,92,246,0.2); border: 1px solid rgba(139,92,246,0.5); padding: 6px 18px; border-radius: 9999px; font-size: 13px; font-weight: 700; color: #C084FC; margin-bottom: 20px;">
                            ✦ خروجی هوش مصنوعی استودیو Hale
                        </div>
                        <div style="font-size: 42px; font-weight: 900; letter-spacing: -1px; text-shadow: 0 10px 30px rgba(0,0,0,0.8);">
                            نورپردازی سینمایی، سایه‌های واقعی و بافت غنی
                        </div>
                        <p style="color: var(--text-secondary); font-size: 16px; margin-top: 14px;">
                            بدون پرده سبز، بدون سافت‌باکس و بدون هزینه میلیونی عکاسی
                        </p>
                    </div>
                </div>

                {{-- Before Layer (Raw Mobile Photo) --}}
                <div class="ba-layer ba-layer-before" id="ba-before">
                    <div style="text-align: center; color: #334155; padding: 40px; min-width: 800px;">
                        <div style="display: inline-block; background: #CBD5E1; padding: 6px 18px; border-radius: 9999px; font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 20px;">
                            عکس خام اولیه (با موبایل روی میز ساده)
                        </div>
                        <div style="font-size: 38px; font-weight: 800; letter-spacing: -1px;">
                            نور نامناسب، پس‌زمینه شلوغ و بازتاب‌های مات
                        </div>
                        <p style="color: #64748B; font-size: 16px; margin-top: 14px;">
                            عکسی که اسکرول اینستاگرام را متوقف نمی‌کند
                        </p>
                    </div>
                </div>

                {{-- Draggable Handle --}}
                <div class="ba-handle" id="ba-handle">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M8 7l-5 5 5 5M16 7l5 5-5 5"/></svg>
                </div>
            </div>
        </section>

        {{-- ============ ASYMMETRIC BENTO GRID SHOWCASE ============ --}}
        <section class="bento-section" id="features">
            <div style="text-align: center; margin-bottom: 48px;">
                <div class="badge-glow" style="margin-bottom: 12px;">
                    <span>مهندسی‌شده برای رشد فروش</span>
                </div>
                <h2 style="font-size: clamp(28px, 4vw, 44px); font-weight: 800; margin: 0 0 16px;">
                    چرا فروشگاه‌ها <span class="text-gradient">Hale</span> را ترجیح می‌دهند؟
                </h2>
                <p style="color: var(--text-secondary); max-width: 600px; margin: 0 auto; font-size: 15px;">
                    پلتفرمی که صفر تا صد تولید محتوای شبکه‌های اجتماعی را اتوماتیک می‌کند.
                </p>
            </div>

            <div class="bento-grid">
                {{-- Card 1 (Span 2) --}}
                <div class="bento-card bento-span-2">
                    <div>
                        <div class="bento-icon">🎬</div>
                        <h3>تولید ویدیوی Reels و استوری عمودی (۹:۱۶)</h3>
                        <p>
                            نه فقط تصویر؛ ویدیوهای تبلیغاتی متحرک و پویا با مدت‌های ۵، ۸ و ۱۰ ثانیه بسازید که الگوریتم اکسپلور اینستاگرام و تیک‌تاک عاشق آن است. حرکت دوربین، انیمیشن محصول و بازتاب‌های زنده بدون نیاز به افترافکت.
                        </p>
                    </div>
                    <div style="margin-top: 28px; display: flex; gap: 12px; flex-wrap: wrap;">
                        <span class="sim-chip active" style="font-size: 12px;">9:16 عمودی کامل</span>
                        <span class="sim-chip" style="font-size: 12px;">خروجی MP4 کدک H.264</span>
                        <span class="sim-chip" style="font-size: 12px;">پخش در ۶۰ فریم روان</span>
                    </div>
                </div>

                {{-- Card 2 --}}
                <div class="bento-card">
                    <div>
                        <div class="bento-icon">🪄</div>
                        <h3>موتور خودکار Auto-Best</h3>
                        <p>
                            نیاز به پرامپت‌نویسی پیچیده انگلیسی ندارید. با یک کلیک، هوش مصنوعی ما بهترین پالت رنگ، سبک نورپردازی و زاویه را متناسب با دسته‌بندی محصولتان انتخاب می‌کند.
                        </p>
                    </div>
                    <div style="margin-top: 24px;">
                        <span style="font-size: 12px; color: var(--accent-cyan); font-weight: 600;">✦ تحلیل هوشمند دسته‌بندی و فرم کالا</span>
                    </div>
                </div>

                {{-- Card 3 --}}
                <div class="bento-card">
                    <div>
                        <div class="bento-icon">🛡️</div>
                        <h3>بدون واترمارک و آماده انتشار تجاری</h3>
                        <p>
                            تمام خروجی‌های پلن‌های استارتر و کریتور صد درصد اختصاصی، بدون هیچ‌گونه واترمارک و با لایسنس استفاده نامحدود تجاری در وب‌سایت، کمپین‌ها و دیجی‌کالا ارائه می‌شوند.
                        </p>
                    </div>
                </div>

                {{-- Card 4 (Span 2) --}}
                <div class="bento-card bento-span-2">
                    <div>
                        <div class="bento-icon">⚡</div>
                        <h3>معماری صف ابری و گارانتی بازگشت اعتبار</h3>
                        <p>
                            سیستم توزیع‌شده با مقیاس‌پذیری آنی؛ حتی در زمان اوج ترافیک، پردازش‌های شما بدون افت کیفیت و در کسری از دقیقه آماده می‌شوند. در صورت هرگونه قطعی یا عدم رضایت از خروجی، اعتبار شما درجا و بدون کسر به حسابتان بازمی‌گردد.
                        </p>
                    </div>
                    <div style="margin-top: 24px; display: flex; gap: 24px; color: var(--text-muted); font-size: 13px;">
                        <span>✓ صف اختصاصی Redis</span>
                        <span>✓ رزرو اتمیک Credit</span>
                        <span>✓ بازگشت آنی در خطا</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ SOCIAL PROOF NUMBERS ============ --}}
        <section style="border-top: 1px solid var(--border-subtle); border-bottom: 1px solid var(--border-subtle); background: rgba(255,255,255,0.01); padding: 48px 24px; margin-bottom: 100px;">
            <div style="max-width: 1100px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 32px; text-align: center;">
                <div>
                    <div style="font-size: 42px; font-weight: 900; color: #FFFFFF;" class="text-gradient">۱۰,۰۰۰+</div>
                    <div style="color: var(--text-secondary); font-size: 14px; margin-top: 4px;">محتوای تبلیغاتی تولیدشده</div>
                </div>
                <div>
                    <div style="font-size: 42px; font-weight: 900; color: #FFFFFF;" class="text-gradient">۵ برابر</div>
                    <div style="color: var(--text-secondary); font-size: 14px; margin-top: 4px;">افزایش تعامل و کلیک در اینستاگرام</div>
                </div>
                <div>
                    <div style="font-size: 42px; font-weight: 900; color: #FFFFFF;" class="text-gradient">کمتر از ۲ دقیقه</div>
                    <div style="color: var(--text-secondary); font-size: 14px; margin-top: 4px;">زمان میانگین تا دریافت خروجی</div>
                </div>
                <div>
                    <div style="font-size: 42px; font-weight: 900; color: #FFFFFF;" class="text-gradient">۴.۹ / ۵</div>
                    <div style="color: var(--text-secondary); font-size: 14px; margin-top: 4px;">امتیاز رضایت فروشگاه‌های اینترنتی</div>
                </div>
            </div>
        </section>

        {{-- ============ STRIPE-STYLE PRICING CARDS ============ --}}
        <section class="pricing-section" id="pricing">
            <div class="badge-glow" style="margin-bottom: 12px;">
                <span>پلن‌های شفاف و بدون هزینه مخفی</span>
            </div>
            <h2 style="font-size: clamp(28px, 4vw, 44px); font-weight: 800; margin: 0 0 16px;">
                تعرفه متناسب با <span class="text-gradient">اندازه کسب‌وکار شما</span>
            </h2>
            <p style="color: var(--text-secondary); max-width: 600px; margin: 0 auto; font-size: 15px;">
                همین حالا با ۳۰ کریدیت هدیه ثبت‌نام رایگان شروع کنید و در صورت نیاز ارتقا دهید.
            </p>

            <div class="pricing-grid">
                {{-- Free Plan --}}
                <div class="pricing-card">
                    <h3>پلن رایگان (شروع)</h3>
                    <p style="color: var(--text-muted); font-size: 13px; margin: 0;">برای تست و شروع کسب‌وکارهای نوپا</p>
                    <div class="pricing-price">
                        ۰ <small>تومان / ماهانه</small>
                    </div>
                    <ul class="pricing-features">
                        <li><span class="check">✓</span> ۳۰ کریدیت هدیه با تایید پیامک</li>
                        <li><span class="check">✓</span> تولید تصاویر نامحدود</li>
                        <li><span class="check">✓</span> دسترسی به تمام سبک‌های تصویری</li>
                        <li><span class="check">✓</span> واترمارک نامحسوس Hale</li>
                    </ul>
                    <button class="btn-glass" data-open-auth="register" style="width: 100%; justify-content: center;">
                        شروع رایگان
                    </button>
                </div>

                {{-- Starter Plan --}}
                <div class="pricing-card">
                    <h3>استارتر (حرفه‌ای)</h3>
                    <p style="color: var(--text-muted); font-size: 13px; margin: 0;">مناسب برای پیج‌های فروشگاهی فعال</p>
                    <div class="pricing-price">
                        ۲۹۰,۰۰۰ <small>تومان / ماهانه</small>
                    </div>
                    <ul class="pricing-features">
                        <li><span class="check">✓</span> ۲۰۰ کریدیت ماهانه</li>
                        <li><span class="check">✓</span> بدون هیچ‌گونه واترمارک</li>
                        <li><span class="check">✓</span> امکان تولید ویدیوی Reels (۵ و ۸ ثانیه)</li>
                        <li><span class="check">✓</span> اولویت پردازش بالا در صف ابری</li>
                        <li><span class="check">✓</span> دانلود با بالاترین کیفیت 4K</li>
                    </ul>
                    <button class="btn-aurora" data-open-auth="register" style="width: 100%; justify-content: center;">
                        انتخاب پلن استارتر
                    </button>
                </div>

                {{-- Creator Plan (Featured) --}}
                <div class="pricing-card featured">
                    <div class="pricing-badge">محبوب‌ترین انتخاب برندها</div>
                    <h3>کریتور (نامحدود تجاری)</h3>
                    <p style="color: var(--text-muted); font-size: 13px; margin: 0;">برای آژانس‌ها و فروشگاه‌های پرتولید</p>
                    <div class="pricing-price">
                        ۶۹۰,۰۰۰ <small>تومان / ماهانه</small>
                    </div>
                    <ul class="pricing-features">
                        <li><span class="check">✓</span> ۶۰۰ کریدیت ماهانه</li>
                        <li><span class="check">✓</span> بدون واترمارک با لایسنس تجاری کامل</li>
                        <li><span class="check">✓</span> ویدیوهای ریلز تا ۱۰ ثانیه کامل</li>
                        <li><span class="check">✓</span> بالاترین اولویت پردازش سرور (VIP)</li>
                        <li><span class="check">✓</span> فاکتور رسمی با کد رهگیری مالیاتی</li>
                        <li><span class="check">✓</span> پشتیبانی اختصاصی تلگرام و تیکت</li>
                    </ul>
                    <button class="btn-aurora" data-open-auth="register" style="width: 100%; justify-content: center; background: linear-gradient(135deg, #EC4899, #8B5CF6);">
                        انتخاب پلن کریتور
                    </button>
                </div>
            </div>
        </section>

        {{-- ============ INTERACTIVE FAQ ACCORDION ============ --}}
        <section class="faq-section" id="faq" style="max-width: 860px; margin: 0 auto 120px; padding: 0 24px;">
            <div style="text-align: center; margin-bottom: 48px;">
                <div class="badge-glow" style="margin-bottom: 12px;">
                    <span>پاسخ به ابهامات متداول</span>
                </div>
                <h2 style="font-size: clamp(26px, 3.5vw, 40px); font-weight: 800; margin: 0;">
                    پرسش‌های پرتکرار شما
                </h2>
            </div>

            <div style="display: flex; flex-direction: column; gap: 14px;">
                <details style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 20px 24px; cursor: pointer;" open>
                    <summary style="font-weight: 700; font-size: 16px; color: #FFFFFF; outline: 0;">آیا نیاز به مهارت گرافیک یا نوشتن پرامپت دارم؟</summary>
                    <p style="color: var(--text-secondary); font-size: 14px; line-height: 1.8; margin-top: 12px; margin-bottom: 0;">
                        خیر، به هیچ وجه. پلتفرم Hale طوری طراحی شده که شما تنها عکس معمولی محصولتان را با موبایل آپلود کرده و سبک مد نظرتان را با لمس دکمه‌ها انتخاب می‌کنید. مهندسی پرامپت و پردازش‌های تخصصی تماماً توسط الگوریتم خودکار ما انجام می‌شود.
                    </p>
                </details>

                <details style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 20px 24px; cursor: pointer;">
                    <summary style="font-weight: 700; font-size: 16px; color: #FFFFFF; outline: 0;">آیا خروجی‌ها برای چاپ کاتالوگ و سایت هم مناسب هستند؟</summary>
                    <p style="color: var(--text-secondary); font-size: 14px; line-height: 1.8; margin-top: 12px; margin-bottom: 0;">
                        بله، فایل‌ها با رزولوشن استاندارد و وضوح بالا ذخیره می‌شوند و برای استفاده در سایت فروشگاهی (دیجی‌کالا، ترب، باسلام و فروشگاه شخصی) و همچنین چاپ با کیفیت عالی در دسترس هستند.
                    </p>
                </details>

                <details style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 20px 24px; cursor: pointer;">
                    <summary style="font-weight: 700; font-size: 16px; color: #FFFFFF; outline: 0;">اگر از تصویر خروجی راضی نبودم چه اتفاقی می‌افتد؟</summary>
                    <p style="color: var(--text-secondary); font-size: 14px; line-height: 1.8; margin-top: 12px; margin-bottom: 0;">
                        شما می‌توانید از کلید «تولید مجدد (Regenerate)» استفاده کنید تا همان سبک با زاویه یا نور تازه‌ای خلق شود. همچنین در صورت بروز خطای سیستمی، اعتبار کسرشده در همان لحظه به کیف پول شما برگشت داده می‌شود.
                    </p>
                </details>

                <details style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 20px 24px; cursor: pointer;">
                    <summary style="font-weight: 700; font-size: 16px; color: #FFFFFF; outline: 0;">نحوه پرداخت و دریافت فاکتور رسمی چگونه است؟</summary>
                    <p style="color: var(--text-secondary); font-size: 14px; line-height: 1.8; margin-top: 12px; margin-bottom: 0;">
                        پرداخت با کلیه کارت‌های عضو شبکه شتاب از طریق درگاه امن زرین‌پال انجام می‌شود و پس از پرداخت، فاکتور رسمی دیجیتال دارای شماره یکتا بلافاصله در پنل شما قابل مشاهده، پرینت و دانلود است.
                    </p>
                </details>
            </div>
        </section>

        {{-- ============ BOTTOM CTA ============ --}}
        <section style="max-width: 1000px; margin: 0 auto 120px; padding: 0 24px;">
            <div style="background: radial-gradient(circle at top, rgba(139,92,246,0.3) 0%, rgba(15,18,28,0.9) 70%); border: 1px solid rgba(139,92,246,0.4); border-radius: 32px; padding: 60px 32px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.6);">
                <h2 style="font-size: clamp(30px, 4.5vw, 52px); font-weight: 900; margin: 0 0 16px;">
                    آماده‌اید فروش محصولتان را متحول کنید؟
                </h2>
                <p style="color: var(--text-secondary); font-size: 16px; max-width: 540px; margin: 0 auto 36px;">
                    بدون نیاز به کارت بانکی ثبت‌نام کنید و ۳۰ کریدیت هدیه برای اولین تصاویر و ویدیوهای تبلیغاتی‌تان دریافت کنید.
                </p>
                <button class="btn-aurora" data-open-auth="register" style="padding: 16px 36px; font-size: 16px;">
                    <span>شروع رایگان در کمتر از ۱ دقیقه</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
            </div>
        </section>
    </main>

    {{-- ============ MODERN FOOTER ============ --}}
    <footer style="border-top: 1px solid var(--border-subtle); padding: 48px 24px; background: rgba(6,7,11,0.8); text-align: center;">
        <div style="max-width: 1100px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; gap: 20px;">
            <a class="brand" href="/" style="font-size: 28px;">H<span>•</span>le</a>
            <p style="color: var(--text-muted); font-size: 13px; max-width: 440px; margin: 0;">
                پلتفرم ابری هوش مصنوعی برای تولید محتوای تبلیغاتی محصولات فروشگاه‌ها
            </p>
            <div style="display: flex; gap: 24px; font-size: 13px; color: var(--text-secondary);">
                <a href="/terms" style="color: inherit; text-decoration: none;">شرایط استفاده</a>
                <a href="/privacy" style="color: inherit; text-decoration: none;">حریم خصوصی</a>
                <a href="#pricing" style="color: inherit; text-decoration: none;">تعرفه‌ها</a>
                <a href="mailto:support@hale.ai" style="color: inherit; text-decoration: none;">پشتیبانی</a>
            </div>
            <small style="color: var(--text-muted); font-size: 12px; margin-top: 12px;">
                © ۱۴۰۵ تمامی حقوق برای استودیو هوشمند Hale محفوظ است.
            </small>
        </div>
    </footer>

    {{-- ============ AUTH MODAL (PRESERVED DATA ATTRIBUTES) ============ --}}
    <div class="auth-modal" data-auth-modal hidden>
        <div class="modal-backdrop" data-close-auth></div>
        <section class="auth-panel" role="dialog" aria-modal="true" aria-labelledby="auth-title">
            <button class="close-button" data-close-auth aria-label="بستن">×</button>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <span style="font-size: 18px; color: var(--primary);">✦</span>
                <span style="font-size: 12px; font-weight: 700; color: var(--text-muted); letter-spacing: 1px;">HALE AI STUDIO</span>
            </div>
            <h2 id="auth-title" style="font-size: 26px; font-weight: 800; margin: 0 0 6px;">ورود به استودیو</h2>
            <p class="modal-copy" style="color: var(--text-secondary); font-size: 13px; margin: 0;">با ایمیل یا شماره موبایل وارد شو و بساز.</p>
            <div class="auth-tabs">
                <button class="active" data-auth-tab="login">ورود</button>
                <button data-auth-tab="register">ثبت‌نام رایگان</button>
            </div>
            <form data-auth-form>
                <label data-name-field hidden>نام و نام خانوادگی<input name="name" autocomplete="name" placeholder="مثلاً سارا احمدی"></label>
                <label>ایمیل یا شماره موبایل<input name="identifier" autocomplete="username" inputmode="email" placeholder="sara@example.com یا ۰۹۱۲..."></label>
                <label>رمز عبور<input name="password" type="password" autocomplete="current-password" placeholder="حداقل ۸ کاراکتر"></label>
                <label data-confirm-field hidden>تکرار رمز عبور<input name="password_confirmation" type="password" autocomplete="new-password"></label>
                <p class="form-message" data-form-message role="alert"></p>
                <button class="btn-aurora full-button" type="submit" data-submit-auth style="border-radius: 12px; padding: 13px;">ورود به Hale <span>←</span></button>
            </form>
            <p class="modal-footnote" style="color: var(--text-muted); font-size: 11px; margin-top: 18px; text-align: center;">با ثبت‌نام، ۳۰ کریدیت هدیه جهت شروع به حسابتان افزوده می‌شود.</p>
        </section>
    </div>

    {{-- ============ INTERACTIVE JS FOR SIMULATOR AND BEFORE/AFTER SLIDER ============ --}}
    <script>
        // 1. Lovable-Style Interactive Simulator Engine
        const simProducts = {
            perfume: { title: "عطر شیشه‌ای فرانسوی", meta: "نورپردازی حجمی · فرمت 1:1 اینستاگرام", badge: "سبک سینمایی لوکس", mark: "L'EAU NOIR", bg: "linear-gradient(180deg, rgba(255,255,255,0.2) 0%, rgba(139,92,246,0.3) 100%)" },
            shoe: { title: "کتانی ورزشی رانینگ", meta: "صحنه شهری با سایه‌های زنده", badge: "سبک نئون الکتریک", mark: "AERO SPRINT", bg: "linear-gradient(135deg, rgba(6,182,212,0.3) 0%, rgba(236,72,153,0.3) 100%)" },
            watch: { title: "ساعت مچی هوشمند", meta: "نورپردازی استودیویی مینیمال", badge: "سبک استودیویی سفید", mark: "CHRONO X", bg: "linear-gradient(180deg, rgba(255,255,255,0.3) 0%, rgba(100,116,139,0.3) 100%)" }
        };

        let currentSim = { prod: 'perfume', style: 'cinematic', format: '1:1' };

        const updateSimUI = () => {
            const data = simProducts[currentSim.prod];
            document.getElementById('sim-target-title').textContent = data.title;
            document.getElementById('sim-target-meta').textContent = `${data.meta} · ${currentSim.format}`;
            document.getElementById('sim-badge-text').textContent = document.querySelector(`[data-sim-style="${currentSim.style}"]`)?.textContent || data.badge;
            document.getElementById('sim-graphic-title').textContent = data.mark;
            document.getElementById('sim-graphic').firstElementChild.style.background = data.bg;
            document.getElementById('sim-cost').textContent = currentSim.format === '9:16' ? '۸ Credit (تولید ویدیو)' : '۲ Credit (تولید تصویر)';
        };

        document.querySelectorAll('#sim-products button').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#sim-products button').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentSim.prod = btn.dataset.simProd;
                updateSimUI();
            });
        });

        document.querySelectorAll('#sim-styles button').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#sim-styles button').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentSim.style = btn.dataset.simStyle;
                updateSimUI();
            });
        });

        document.querySelectorAll('#sim-formats button').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#sim-formats button').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentSim.format = btn.dataset.simFormat;
                updateSimUI();
            });
        });

        // 2. Framer-Style Interactive Drag Comparison Slider
        const baContainer = document.getElementById('ba-container');
        const baBefore = document.getElementById('ba-before');
        const baHandle = document.getElementById('ba-handle');

        if (baContainer && baBefore && baHandle) {
            let isDragging = false;
            const setSliderPos = (clientX) => {
                const rect = baContainer.getBoundingClientRect();
                let x = clientX - rect.left;
                if (x < 20) x = 20;
                if (x > rect.width - 20) x = rect.width - 20;
                const percent = (x / rect.width) * 100;
                baBefore.style.width = (100 - percent) + '%';
                baHandle.style.left = percent + '%';
            };

            baContainer.addEventListener('mousedown', (e) => { isDragging = true; setSliderPos(e.clientX); });
            window.addEventListener('mouseup', () => { isDragging = false; });
            window.addEventListener('mousemove', (e) => { if (isDragging) setSliderPos(e.clientX); });

            baContainer.addEventListener('touchstart', (e) => { isDragging = true; setSliderPos(e.touches[0].clientX); });
            window.addEventListener('touchend', () => { isDragging = false; });
            window.addEventListener('touchmove', (e) => { if (isDragging) setSliderPos(e.touches[0].clientX); });
        }

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
        }
    </script>
</body>
</html>
