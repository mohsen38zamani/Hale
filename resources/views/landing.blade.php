<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Hale استودیوی هوش مصنوعی ساخت محتوای تبلیغاتی؛ عکس محصولت را بده، تصویر و ویدئوی آماده اینستاگرام تحویل بگیر. بدون عکاس، بدون Prompt.">
    <meta name="theme-color" content="#06070B">
    <meta name="robots" content="index, follow">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="canonical" href="{{ url('/') }}">
    <title>Hale | عکس محصولت، خروجی سینمایی و تبلیغاتی آماده انتشار</title>

    {{-- Open Graph / Twitter: bare links shared in DMs and social feeds --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Hale">
    <meta property="og:locale" content="fa_IR">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:title" content="Hale | عکس محصولت، خروجی سینمایی و تبلیغاتی آماده انتشار">
    <meta property="og:description" content="Hale استودیوی هوش مصنوعی ساخت محتوای تبلیغاتی؛ عکس محصولت را بده، تصویر و ویدئوی آماده اینستاگرام تحویل بگیر. بدون عکاس، بدون Prompt.">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="Hale | عکس محصولت، خروجی سینمایی و تبلیغاتی آماده انتشار">
    <meta name="twitter:description" content="Hale استودیوی هوش مصنوعی ساخت محتوای تبلیغاتی؛ عکس محصولت را بده، تصویر و ویدئوی آماده اینستاگرام تحویل بگیر. بدون عکاس، بدون Prompt.">

    @vite(['resources/css/app.css', 'resources/css/landing.css', 'resources/js/app.js'])

    {{-- Structured data: the product card + the FAQ rendered below on this page --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "Hale",
        "alternateName": "Hale AI Content Studio",
        "url": "{{ url('/') }}",
        "applicationCategory": "DesignApplication",
        "operatingSystem": "Web",
        "inLanguage": "fa-IR",
        "description": "Hale استودیوی هوش مصنوعی ساخت محتوای تبلیغاتی؛ عکس محصولت را بده، تصویر و ویدئوی آماده اینستاگرام تحویل بگیر. بدون عکاس، بدون Prompt."
    }
    </script>
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": "آیا نیاز به مهارت گرافیک یا نوشتن پرامپت دارم؟",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "خیر، به هیچ وجه. پلتفرم Hale طوری طراحی شده که شما تنها عکس معمولی محصولتان را با موبایل آپلود کرده و سبک مد نظرتان را با لمس دکمه‌ها انتخاب می‌کنید. مهندسی پرامپت و پردازش‌های تخصصی تماماً توسط الگوریتم خودکار ما انجام می‌شود."
                }
            },
            {
                "@type": "Question",
                "name": "آیا خروجی‌ها برای چاپ کاتالوگ و سایت هم مناسب هستند؟",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "بله، فایل‌ها با رزولوشن استاندارد و وضوح بالا ذخیره می‌شوند و برای استفاده در سایت فروشگاهی (دیجی‌کالا، ترب، باسلام و فروشگاه شخصی) و همچنین چاپ با کیفیت عالی در دسترس هستند."
                }
            },
            {
                "@type": "Question",
                "name": "اگر از تصویر خروجی راضی نبودم چه اتفاقی می‌افتد؟",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "شما می‌توانید از کلید «تولید مجدد (Regenerate)» استفاده کنید تا همان سبک با زاویه یا نور تازه‌ای خلق شود. همچنین در صورت بروز خطای سیستمی، اعتبار کسرشده در همان لحظه به کیف پول شما برگشت داده می‌شود."
                }
            },
            {
                "@type": "Question",
                "name": "نحوه پرداخت و دریافت فاکتور رسمی چگونه است؟",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "پرداخت با کلیه کارت‌های عضو شبکه شتاب از طریق درگاه امن زرین‌پال انجام می‌شود و پس از پرداخت، فاکتور رسمی دیجیتال دارای شماره یکتا بلافاصله در پنل شما قابل مشاهده، پرینت و دانلود است."
                }
            }
        ]
    }
    </script>
</head>
<body class="landing-page">
    <a class="skip-link" href="#main">پرش به محتوای اصلی</a>

    {{-- ============ STRIPE AURORA GLOW BACKGROUND ============ --}}
    <div class="aurora-mesh" aria-hidden="true">
        <div class="aurora-orb aurora-orb-1"></div>
        <div class="aurora-orb aurora-orb-2"></div>
        <div class="aurora-orb aurora-orb-3"></div>
    </div>

    {{-- ============ FRAMER FLOATING PILL NAVBAR ============ --}}
    <header class="framer-pill-nav">
        <a class="brand" href="/">H<span>•</span>le</a>
        <nav class="nav-links" aria-label="پیمایش اصلی">
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

    <main id="main">
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

            <div class="hero-cta">
                <button class="btn-aurora" data-open-auth="register">
                    <span>اولین خروجی‌ات را بساز</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
                <a class="btn-glass" href="#simulator">
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
                    <span class="sim-status">
                        <span class="sim-status-dot"></span>
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

                        <div class="sim-footer">
                            <div>
                                <small class="sim-cost-label">هزینه محاسبه‌شده:</small>
                                <strong id="sim-cost" class="sim-cost">۲ Credit (تولید تصویر)</strong>
                            </div>
                            <button class="btn-aurora sim-cta" data-open-auth="register">
                                ساخت همین محتوا ↗
                            </button>
                        </div>
                    </div>

                    {{-- Display Canvas --}}
                    <div class="simulator-display">
                        <div class="sim-render-card scene-cinematic" id="sim-card">
                            <div class="sim-render-badge" id="sim-badge">
                                <span class="spark">✦</span>
                                <span id="sim-badge-text">سبک سینمایی لوکس</span>
                            </div>

                            {{-- Product artwork: inline SVG per sample product (no network requests) --}}
                            <div id="sim-graphic" class="sim-graphic" aria-hidden="true">
                                <div class="sim-art" data-art="perfume">
                                    <svg viewBox="0 0 200 260" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <defs>
                                            <linearGradient id="perfume-glass" x1="40" y1="70" x2="160" y2="215" gradientUnits="userSpaceOnUse">
                                                <stop stop-color="#E9D5FF"/>
                                                <stop offset="0.5" stop-color="#A855F7"/>
                                                <stop offset="1" stop-color="#4C1D95"/>
                                            </linearGradient>
                                            <linearGradient id="perfume-shine" x1="0" y1="0" x2="1" y2="0">
                                                <stop stop-color="#FFFFFF" stop-opacity="0.9"/>
                                                <stop offset="1" stop-color="#FFFFFF" stop-opacity="0"/>
                                            </linearGradient>
                                        </defs>
                                        <ellipse cx="100" cy="232" rx="60" ry="10" fill="#0F172A" fill-opacity="0.45"/>
                                        <rect x="83" y="16" width="34" height="32" rx="8" fill="#111827"/>
                                        <rect x="89" y="46" width="22" height="16" rx="3" fill="#1F2937"/>
                                        <rect x="54" y="62" width="92" height="156" rx="24" fill="url(#perfume-glass)"/>
                                        <rect x="55" y="63" width="90" height="154" rx="23" stroke="#FFFFFF" stroke-opacity="0.55" stroke-width="2"/>
                                        <rect x="64" y="74" width="14" height="130" rx="7" fill="url(#perfume-shine)"/>
                                        <rect x="70" y="126" width="60" height="58" rx="9" fill="#FFFFFF" fill-opacity="0.94"/>
                                        <text x="100" y="147" text-anchor="middle" font-size="9" letter-spacing="3" fill="#7C3AED" font-family="Georgia, serif">HALE</text>
                                        <text x="100" y="164" text-anchor="middle" font-size="11" font-weight="700" fill="#111827" font-family="Georgia, serif">L'EAU</text>
                                        <text x="100" y="177" text-anchor="middle" font-size="11" font-weight="700" fill="#111827" font-family="Georgia, serif">NOIR</text>
                                    </svg>
                                </div>
                                <div class="sim-art" data-art="shoe" hidden>
                                    <svg viewBox="0 0 200 260" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <defs>
                                            <linearGradient id="shoe-upper" x1="46" y1="128" x2="170" y2="198" gradientUnits="userSpaceOnUse">
                                                <stop stop-color="#22D3EE"/>
                                                <stop offset="0.55" stop-color="#3B82F6"/>
                                                <stop offset="1" stop-color="#EC4899"/>
                                            </linearGradient>
                                        </defs>
                                        <ellipse cx="102" cy="220" rx="72" ry="10" fill="#0F172A" fill-opacity="0.45"/>
                                        <path d="M44 196C40 154 68 130 106 130C134 130 152 142 162 158L170 178C173 187 168 196 158 196H44Z" fill="url(#shoe-upper)"/>
                                        <path d="M142 134C155 139 163 150 167 162H150C146 151 143 142 142 134Z" fill="#0F172A" fill-opacity="0.85"/>
                                        <g stroke="#FFFFFF" stroke-width="5" stroke-linecap="round">
                                            <path d="M72 148L92 140"/>
                                            <path d="M82 160L102 152"/>
                                            <path d="M92 172L112 164"/>
                                        </g>
                                        <path d="M58 188C86 178 124 178 150 187" stroke="#FFFFFF" stroke-opacity="0.9" stroke-width="6" stroke-linecap="round"/>
                                        <path d="M40 197C40 189 47 185 58 185H156C168 185 176 191 176 199C176 207 168 211 156 211H52C44 211 40 205 40 197Z" fill="#F8FAFC"/>
                                        <path d="M44 205H172" stroke="#CBD5E1" stroke-width="3"/>
                                        <rect x="52" y="232" width="96" height="22" rx="11" fill="#0B0D14" fill-opacity="0.72"/>
                                        <text x="100" y="247" text-anchor="middle" font-size="11" font-weight="700" letter-spacing="1.5" fill="#FFFFFF" font-family="Arial, sans-serif">AERO SPRINT</text>
                                    </svg>
                                </div>
                                <div class="sim-art" data-art="watch" hidden>
                                    <svg viewBox="0 0 200 260" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <defs>
                                            <linearGradient id="watch-case" x1="58" y1="88" x2="142" y2="172" gradientUnits="userSpaceOnUse">
                                                <stop stop-color="#475569"/>
                                                <stop offset="1" stop-color="#0F172A"/>
                                            </linearGradient>
                                            <linearGradient id="watch-screen" x1="70" y1="100" x2="130" y2="160" gradientUnits="userSpaceOnUse">
                                                <stop stop-color="#7C3AED"/>
                                                <stop offset="1" stop-color="#06B6D4"/>
                                            </linearGradient>
                                        </defs>
                                        <ellipse cx="100" cy="244" rx="46" ry="8" fill="#0F172A" fill-opacity="0.45"/>
                                        <path d="M78 24H122L119 96H81L78 24Z" fill="#1F2937"/>
                                        <path d="M81 164H119L122 236H78L81 164Z" fill="#1F2937"/>
                                        <path d="M81 34H119" stroke="#334155" stroke-width="3"/>
                                        <path d="M81 226H119" stroke="#334155" stroke-width="3"/>
                                        <rect x="58" y="88" width="84" height="84" rx="26" fill="url(#watch-case)"/>
                                        <rect x="64" y="94" width="72" height="72" rx="21" fill="#0B0D14"/>
                                        <rect x="70" y="100" width="60" height="60" rx="17" fill="url(#watch-screen)"/>
                                        <rect x="140" y="118" width="9" height="24" rx="4.5" fill="#64748B"/>
                                        <text x="100" y="124" text-anchor="middle" font-size="8" letter-spacing="3" fill="#E9D5FF" font-family="Arial, sans-serif">HALE</text>
                                        <text x="100" y="146" text-anchor="middle" font-size="10" font-weight="700" fill="#FFFFFF" font-family="Arial, sans-serif">CHRONO X</text>
                                    </svg>
                                </div>
                            </div>

                            <div class="sim-render-info">
                                <div>
                                    <strong id="sim-target-title">عطر فرانسوی — زاویه روبرو</strong>
                                    <small id="sim-target-meta">نورپردازی حجمی · فرمت 1:1 اینستاگرام</small>
                                </div>
                                <span class="sim-pill">
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
            <div class="section-head">
                <div class="badge-glow">
                    <span>تحول کیفیت عکس محصول</span>
                </div>
                <h2 class="section-title">
                    عکس روی میز کار در برابر <span class="text-gradient">استودیوی هالیوودی</span>
                </h2>
                <p class="section-lead">
                    دستگیره وسط را به چپ و راست بکشید تا تفاوت عکس خام موبایلی را با خروجی پردازش‌شده توسط هوش مصنوعی Hale مقایسه کنید.
                </p>
            </div>

            <div class="ba-slider-container" id="ba-container">
                {{-- After Layer (AI Studio Result) --}}
                <div class="ba-layer ba-layer-after">
                    <div class="ba-content">
                        <div class="ba-pill">
                            ✦ خروجی هوش مصنوعی استودیو Hale
                        </div>
                        <div class="ba-headline">
                            نورپردازی سینمایی، سایه‌های واقعی و بافت غنی
                        </div>
                        <p class="ba-copy">
                            بدون پرده سبز، بدون سافت‌باکس و بدون هزینه میلیونی عکاسی
                        </p>
                    </div>
                </div>

                {{-- Before Layer (Raw Mobile Photo) --}}
                <div class="ba-layer ba-layer-before" id="ba-before">
                    <div class="ba-content">
                        <div class="ba-pill">
                            عکس خام اولیه (با موبایل روی میز ساده)
                        </div>
                        <div class="ba-headline">
                            نور نامناسب، پس‌زمینه شلوغ و بازتاب‌های مات
                        </div>
                        <p class="ba-copy">
                            عکسی که اسکرول اینستاگرام را متوقف نمی‌کند
                        </p>
                    </div>
                </div>

                {{-- Draggable Handle (pointer + keyboard, see script below) --}}
                <div class="ba-handle" id="ba-handle" role="slider" tabindex="0"
                     aria-label="مقایسه عکس قبل و بعد: با کلیدهای جهت‌دار حرکت دهید"
                     aria-orientation="horizontal" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M8 7l-5 5 5 5M16 7l5 5-5 5"/></svg>
                </div>
            </div>
        </section>

        {{-- ============ ASYMMETRIC BENTO GRID SHOWCASE ============ --}}
        <section class="bento-section" id="features">
            <div class="section-head">
                <div class="badge-glow">
                    <span>مهندسی‌شده برای رشد فروش</span>
                </div>
                <h2 class="section-title">
                    چرا فروشگاه‌ها <span class="text-gradient">Hale</span> را ترجیح می‌دهند؟
                </h2>
                <p class="section-lead">
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
                    <div class="bento-tags">
                        <span class="sim-chip active">9:16 عمودی کامل</span>
                        <span class="sim-chip">خروجی MP4 کدک H.264</span>
                        <span class="sim-chip">پخش در ۶۰ فریم روان</span>
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
                    <div class="bento-note">
                        <span>✦ تحلیل هوشمند دسته‌بندی و فرم کالا</span>
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
                    <div class="bento-checks">
                        <span>✓ صف اختصاصی Redis</span>
                        <span>✓ رزرو اتمیک Credit</span>
                        <span>✓ بازگشت آنی در خطا</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ SOCIAL PROOF NUMBERS ============ --}}
        <section class="stats-section" aria-label="آمار عملکرد">
            <div class="stats-grid">
                <div>
                    <div class="stat-value">۱۰,۰۰۰+</div>
                    <div class="stat-label">محتوای تبلیغاتی تولیدشده</div>
                </div>
                <div>
                    <div class="stat-value">۵ برابر</div>
                    <div class="stat-label">افزایش تعامل و کلیک در اینستاگرام</div>
                </div>
                <div>
                    <div class="stat-value">کمتر از ۲ دقیقه</div>
                    <div class="stat-label">زمان میانگین تا دریافت خروجی</div>
                </div>
                <div>
                    <div class="stat-value">۴.۹ / ۵</div>
                    <div class="stat-label">امتیاز رضایت فروشگاه‌های اینترنتی</div>
                </div>
            </div>
        </section>

        {{-- ============ STRIPE-STYLE PRICING CARDS ============ --}}
        <section class="pricing-section" id="pricing">
            <div class="section-head">
                <div class="badge-glow">
                    <span>پلن‌های شفاف و بدون هزینه مخفی</span>
                </div>
                <h2 class="section-title">
                    تعرفه متناسب با <span class="text-gradient">اندازه کسب‌وکار شما</span>
                </h2>
                <p class="section-lead">
                    همین حالا با ۳۰ کریدیت هدیه ثبت‌نام رایگان شروع کنید و در صورت نیاز ارتقا دهید.
                </p>
            </div>

            <div class="pricing-grid">
                {{-- Free Plan --}}
                <div class="pricing-card">
                    <h3>پلن رایگان (شروع)</h3>
                    <p>برای تست و شروع کسب‌وکارهای نوپا</p>
                    <div class="pricing-price">
                        ۰ <small>تومان / ماهانه</small>
                    </div>
                    <ul class="pricing-features">
                        <li><span class="check">✓</span> ۳۰ کریدیت هدیه با تایید پیامک</li>
                        <li><span class="check">✓</span> تولید تصاویر نامحدود</li>
                        <li><span class="check">✓</span> دسترسی به تمام سبک‌های تصویری</li>
                        <li><span class="check">✓</span> واترمارک نامحسوس Hale</li>
                    </ul>
                    <button class="btn-glass pricing-cta" data-open-auth="register">
                        شروع رایگان
                    </button>
                </div>

                {{-- Starter Plan --}}
                <div class="pricing-card">
                    <h3>استارتر (حرفه‌ای)</h3>
                    <p>مناسب برای پیج‌های فروشگاهی فعال</p>
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
                    <button class="btn-aurora pricing-cta" data-open-auth="register">
                        انتخاب پلن استارتر
                    </button>
                </div>

                {{-- Creator Plan (Featured) --}}
                <div class="pricing-card featured">
                    <div class="pricing-badge">محبوب‌ترین انتخاب برندها</div>
                    <h3>کریتور (نامحدود تجاری)</h3>
                    <p>برای آژانس‌ها و فروشگاه‌های پرتولید</p>
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
                    <button class="btn-aurora pricing-cta" data-open-auth="register">
                        انتخاب پلن کریتور
                    </button>
                </div>
            </div>
        </section>

        {{-- ============ INTERACTIVE FAQ ACCORDION ============ --}}
        <section class="faq-section" id="faq">
            <div class="section-head">
                <div class="badge-glow">
                    <span>پاسخ به ابهامات متداول</span>
                </div>
                <h2 class="section-title">
                    پرسش‌های پرتکرار شما
                </h2>
            </div>

            <div class="faq-list">
                <details class="faq-item" open>
                    <summary>آیا نیاز به مهارت گرافیک یا نوشتن پرامپت دارم؟</summary>
                    <p>
                        خیر، به هیچ وجه. پلتفرم Hale طوری طراحی شده که شما تنها عکس معمولی محصولتان را با موبایل آپلود کرده و سبک مد نظرتان را با لمس دکمه‌ها انتخاب می‌کنید. مهندسی پرامپت و پردازش‌های تخصصی تماماً توسط الگوریتم خودکار ما انجام می‌شود.
                    </p>
                </details>

                <details class="faq-item">
                    <summary>آیا خروجی‌ها برای چاپ کاتالوگ و سایت هم مناسب هستند؟</summary>
                    <p>
                        بله، فایل‌ها با رزولوشن استاندارد و وضوح بالا ذخیره می‌شوند و برای استفاده در سایت فروشگاهی (دیجی‌کالا، ترب، باسلام و فروشگاه شخصی) و همچنین چاپ با کیفیت عالی در دسترس هستند.
                    </p>
                </details>

                <details class="faq-item">
                    <summary>اگر از تصویر خروجی راضی نبودم چه اتفاقی می‌افتد؟</summary>
                    <p>
                        شما می‌توانید از کلید «تولید مجدد (Regenerate)» استفاده کنید تا همان سبک با زاویه یا نور تازه‌ای خلق شود. همچنین در صورت بروز خطای سیستمی، اعتبار کسرشده در همان لحظه به کیف پول شما برگشت داده می‌شود.
                    </p>
                </details>

                <details class="faq-item">
                    <summary>نحوه پرداخت و دریافت فاکتور رسمی چگونه است؟</summary>
                    <p>
                        پرداخت با کلیه کارت‌های عضو شبکه شتاب از طریق درگاه امن زرین‌پال انجام می‌شود و پس از پرداخت، فاکتور رسمی دیجیتال دارای شماره یکتا بلافاصله در پنل شما قابل مشاهده، پرینت و دانلود است.
                    </p>
                </details>
            </div>
        </section>

        {{-- ============ BOTTOM CTA ============ --}}
        <section class="cta-section">
            <div class="cta-panel">
                <h2 class="cta-title">
                    آماده‌اید فروش محصولتان را متحول کنید؟
                </h2>
                <p class="cta-copy">
                    بدون نیاز به کارت بانکی ثبت‌نام کنید و ۳۰ کریدیت هدیه برای اولین تصاویر و ویدیوهای تبلیغاتی‌تان دریافت کنید.
                </p>
                <button class="btn-aurora cta-final" data-open-auth="register">
                    <span>شروع رایگان در کمتر از ۱ دقیقه</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
            </div>
        </section>
    </main>

    {{-- ============ MODERN FOOTER ============ --}}
    <footer class="site-footer">
        <div class="site-footer-inner">
            <a class="brand" href="/">H<span>•</span>le</a>
            <p class="site-footer-note">
                پلتفرم ابری هوش مصنوعی برای تولید محتوای تبلیغاتی محصولات فروشگاه‌ها
            </p>
            <div class="site-footer-links">
                <a href="/terms">شرایط استفاده</a>
                <a href="/privacy">حریم خصوصی</a>
                <a href="#pricing">تعرفه‌ها</a>
                <a href="mailto:support@hale.ai">پشتیبانی</a>
            </div>
            <small class="site-footer-copy">
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
            perfume: { title: "عطر شیشه‌ای فرانسوی", meta: "نورپردازی حجمی · فرمت 1:1 اینستاگرام", badge: "سبک سینمایی لوکس" },
            shoe: { title: "کتانی ورزشی رانینگ", meta: "صحنه شهری با سایه‌های زنده", badge: "سبک نئون الکتریک" },
            watch: { title: "ساعت مچی هوشمند", meta: "نورپردازی استودیویی مینیمال", badge: "سبک استودیویی سفید" }
        };

        const simSceneClasses = ['scene-cinematic', 'scene-minimal', 'scene-natural', 'scene-neon'];
        let currentSim = { prod: 'perfume', style: 'cinematic', format: '1:1' };

        const updateSimUI = () => {
            const data = simProducts[currentSim.prod];
            document.getElementById('sim-target-title').textContent = data.title;
            document.getElementById('sim-target-meta').textContent = `${data.meta} · ${currentSim.format}`;
            document.getElementById('sim-badge-text').textContent = document.querySelector(`[data-sim-style="${currentSim.style}"]`)?.textContent || data.badge;

            // swap the product artwork and the scene backdrop for the picked style
            document.querySelectorAll('.sim-art').forEach((art) => { art.hidden = art.dataset.art !== currentSim.prod; });
            const simCard = document.getElementById('sim-card');
            simCard.classList.remove(...simSceneClasses);
            simCard.classList.add(`scene-${currentSim.style}`);

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

        // 2. Framer-Style Interactive Drag Comparison Slider (pointer + touch + keyboard)
        const baContainer = document.getElementById('ba-container');
        const baBefore = document.getElementById('ba-before');
        const baHandle = document.getElementById('ba-handle');

        if (baContainer && baBefore && baHandle) {
            let isDragging = false;
            let sliderPercent = 50;

            const applySlider = (percent) => {
                sliderPercent = Math.min(97, Math.max(3, percent));
                baBefore.style.width = (100 - sliderPercent) + '%';
                baHandle.style.left = sliderPercent + '%';
                baHandle.setAttribute('aria-valuenow', String(Math.round(sliderPercent)));
            };

            const setSliderPos = (clientX) => {
                const rect = baContainer.getBoundingClientRect();
                applySlider(((clientX - rect.left) / rect.width) * 100);
            };

            baContainer.addEventListener('mousedown', (e) => { isDragging = true; setSliderPos(e.clientX); });
            window.addEventListener('mouseup', () => { isDragging = false; });
            window.addEventListener('mousemove', (e) => { if (isDragging) setSliderPos(e.clientX); });

            baContainer.addEventListener('touchstart', (e) => { isDragging = true; setSliderPos(e.touches[0].clientX); });
            window.addEventListener('touchend', () => { isDragging = false; });
            window.addEventListener('touchmove', (e) => { if (isDragging) setSliderPos(e.touches[0].clientX); });

            baHandle.addEventListener('keydown', (e) => {
                const step = 5;
                let next = null;
                if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') next = sliderPercent - step;
                else if (e.key === 'ArrowRight' || e.key === 'ArrowUp') next = sliderPercent + step;
                else if (e.key === 'Home') next = 3;
                else if (e.key === 'End') next = 97;
                if (next !== null) {
                    e.preventDefault();
                    applySlider(next);
                }
            });
        }

        // 3. Pill navbar compact state once the hero scrolls away
        const pillNav = document.querySelector('.framer-pill-nav');
        if (pillNav) {
            const syncNav = () => pillNav.classList.toggle('is-scrolled', window.scrollY > 24);
            syncNav();
            window.addEventListener('scroll', syncNav, { passive: true });
        }

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
        }
    </script>
</body>
</html>
