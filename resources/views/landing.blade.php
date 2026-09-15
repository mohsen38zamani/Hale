<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Hale استودیوی هوش مصنوعی ساخت محتوای تبلیغاتی؛ عکس محصولت را بده، تصویر و ویدئوی آماده اینستاگرام تحویل بگیر. بدون عکاس، بدون Prompt.">
    <meta name="theme-color" content="#07090c">
    <title>Hale | عکس محصولت، محتوای تبلیغاتی آماده انتشار</title>
    @vite(['resources/css/app.css', 'resources/css/landing.css', 'resources/js/app.js'])
    <style>
        /* Pre-hydration guard: keeps the hero hidden (and immune to FOUC) until JS reveals it */
        .reveal[data-reveal-ready] { opacity: 0; transform: translateY(26px); }
    </style>
</head>
<body class="landing-page">
    <div class="land-wrap" id="top">
        <header class="land-nav">
            <a class="brand" href="#top">H<span>•</span>le</a>
            <nav>
                <a href="#showcase">کارها</a>
                <a href="#why">چرا Hale</a>
                <a href="#formats">خروجی‌ها</a>
                <a href="#faq">سؤالات</a>
            </nav>
            <button class="nav-cta" data-open-auth="register">شروع رایگان <span>↗</span></button>
        </header>

        <main>
            {{-- ============ HERO ============ --}}
            <section class="land-hero">
                <div class="hero-glows" aria-hidden="true">
                    <i class="glow glow-pink"></i><i class="glow glow-cyan"></i><i class="glow glow-amber"></i>
                </div>

                <div class="hero-index reveal">HALE — AI CONTENT STUDIO<br><span>نسل جدید عکاسی محصول</span></div>

                <div class="hero-title">
                    <p class="hero-kicker reveal">بدون عکاس · بدون Prompt · چند ثانیه صبر</p>
                    <h1 class="reveal">
                        <span class="line">عکس محصولت،</span>
                        <span class="line"><em>محتوای فردا.</em></span>
                    </h1>
                    <p class="hero-intro reveal">Hale استودیوی هوش مصنوعی تبلیغات است؛ عکس خام محصولت را می‌گیرد و تصویر و Reel آماده انتشار برای پست، استوری و TikTok تحویل می‌دهد.</p>
                    <div class="hero-actions reveal">
                        <button class="hero-link" data-open-auth="register">اولین خروجی را بساز <span>↓</span></button>
                        <a class="hero-link ghost" href="#showcase">کارهای Hale را ببین <span>↘</span></a>
                    </div>
                </div>

                <div class="hero-object" aria-hidden="true">
                    <div class="scene">
                        <div class="tilt-card" data-tilt>
                            <div class="tilt-poster">
                                <i class="poster-glow"></i>
                                <b class="poster-mark">HALE</b>
                                <strong class="poster-title">CINEMATIC<br>PRODUCT 01</strong>
                                <span class="poster-meta">LIGHT / FORM / 1:1</span>
                            </div>
                            <div class="tilt-chip chip-top">✦ سبک سینمایی</div>
                            <div class="tilt-chip chip-bottom">9:16 · Reel آماده</div>
                        </div>
                    </div>
                    <div class="object-caption">YOUR PRODUCT<br><strong>IN A NEW LIGHT</strong></div>
                </div>

                <div class="scroll-cue" aria-hidden="true"><span></span> اسکرول کن</div>
            </section>

            {{-- ============ LOGO / TRUST STRIP ============ --}}
            <section class="trust-strip">
                <div class="trust-track">
                    <span>۳۰ Credit رایگان</span><i>◆</i>
                    <span>خروجی 1:1 و 9:16</span><i>◆</i>
                    <span>سبک Luxury تا Minimal</span><i>◆</i>
                    <span>Reel و TikTok</span><i>◆</i>
                    <span>پرداخت ریالی</span><i>◆</i>
                    <span>۳۰ Credit رایگان</span><i>◆</i>
                    <span>خروجی 1:1 و 9:16</span><i>◆</i>
                    <span>سبک Luxury تا Minimal</span><i>◆</i>
                    <span>Reel و TikTok</span><i>◆</i>
                    <span>پرداخت ریالی</span><i>◆</i>
                </div>
            </section>

            {{-- ============ METHOD / STEPS ============ --}}
            <section class="method-section" id="method">
                <div class="section-number">۰۱ — روش</div>
                <h2 class="reveal">از یک عکس خام<br>تا <em>یک دلیل برای توقف.</em></h2>
                <p class="section-lede reveal">هر محصول داستانی برای گفتن دارد. Hale نور، فضا و حال‌وهوای درست را می‌سازد تا محصولت همان‌طور که باید دیده شود — فقط با سه حرکت.</p>
                <div class="steps-grid">
                    <article class="reveal">
                        <span class="step-no">۰۱</span>
                        <h3>محصول را بده</h3>
                        <p>عکس محصولت را از موبایل آپلود کن؛ JPG، PNG یا WebP تا ۱۰ مگابایت. همین و بس.</p>
                        <small>ورودی: یک عکس</small>
                    </article>
                    <article class="reveal">
                        <span class="step-no">۰۲</span>
                        <h3>جهت را انتخاب کن</h3>
                        <p>هدف، سبک و فرمت را با چند لمس مشخص کن؛ از Luxury ساکت تا Colorful پرانرژی.</p>
                        <small>کنترل: هدف · سبک · فرمت</small>
                    </article>
                    <article class="reveal">
                        <span class="step-no">۰۳</span>
                        <h3>آماده انتشار</h3>
                        <p>خروجی آماده را ببین، دانلود کن و منتشر کن. اگر راضی نبودی، یک نسخه تازه بساز.</p>
                        <small>خروجی: تصویر یا ویدئو</small>
                    </article>
                </div>
            </section>

            {{-- ============ SHOWCASE (reference-style numbered cards) ============ --}}
            <section class="showcase-section" id="showcase">
                <div class="section-number">۰۲ — کارها</div>
                <h2 class="reveal">گالری <em>صحنه‌ها.</em></h2>
                <p class="section-lede reveal">هر خروجی یک صحنه است: نور، بافت و زاویه‌ای که محصولت را قهرمان قاب می‌کند.</p>

                <article class="show-card reveal">
                    <div class="show-art art-poster1"><span class="show-art-no">SCENE 01</span></div>
                    <div class="show-body">
                        <div class="show-head"><span class="show-no">کار ۰۱</span><h3>پوستر سینمایی</h3></div>
                        <p>نور کم‌عمق، پس‌زمینه تیره و سایه‌های نرم؛ برای محصولاتی که باید لوکس و گران دیده شوند. کادر مربع، آماده پست اینستاگرام.</p>
                        <div class="show-meta">
                            <div><small>سبک</small><b>Luxury / Cinematic</b></div>
                            <div><small>فرمت</small><b>1:1 · پست</b></div>
                            <div><small>مناسب</small><b>عطر، ساعت، جواهر</b></div>
                        </div>
                        <button class="show-cta" data-open-auth="register">بسازش <span>←</span></button>
                    </div>
                </article>

                <article class="show-card flip reveal">
                    <div class="show-art art-poster2"><span class="show-art-no">SCENE 02</span></div>
                    <div class="show-body">
                        <div class="show-head"><span class="show-no">کار ۰۲</span><h3>نور روز مینیمال</h3></div>
                        <p>پس‌زمینه روشن، سایه‌های دقیق و حس یک عکاسی استودیویی تمیز؛ برای محصولاتی که باید ساده و قابل‌اعتماد دیده شوند.</p>
                        <div class="show-meta">
                            <div><small>سبک</small><b>Minimal / Natural</b></div>
                            <div><small>فرمت</small><b>1:1 · پست</b></div>
                            <div><small>مناسب</small><b>لوازم خانگی، مراقبت پوست</b></div>
                        </div>
                        <button class="show-cta" data-open-auth="register">بسازش <span>←</span></button>
                    </div>
                </article>

                <article class="show-card reveal">
                    <div class="show-art art-poster3"><span class="show-art-no">SCENE 03</span></div>
                    <div class="show-body">
                        <div class="show-head"><span class="show-no">کار ۰۳</span><h3>Reel عمودی نئون</h3></div>
                        <p>حرکت دوربین، بازتاب نئون و ریتم سریع؛ ویدئویی ۹:۱۶ که در سه ثانیه اول توقف می‌سازد و اسکرول را می‌شکند.</p>
                        <div class="show-meta">
                            <div><small>سبک</small><b>Colorful / Dark</b></div>
                            <div><small>فرمت</small><b>9:16 · Reel و TikTok</b></div>
                            <div><small>مناسب</small><b>گجت، نوشیدنی، مد</b></div>
                        </div>
                        <button class="show-cta" data-open-auth="register">بسازش <span>←</span></button>
                    </div>
                </article>
            </section>

            {{-- ============ FORMATS ============ --}}
            <section class="formats-section" id="formats">
                <div class="section-number">۰۳ — خروجی‌ها</div>
                <div class="formats-head">
                    <h2 class="reveal">یک محصول.<br><em>چند قاب.</em></h2>
                    <p class="reveal">برای پست، استوری، Reel و TikTok؛ خروجی‌ای که دقیقاً با جای انتشارش هم‌اندازه است.</p>
                </div>
                <div class="format-stage">
                    <div class="format-tile format-square reveal"><span>POST</span><strong>1:1</strong><small>برای حضور ماندگار</small></div>
                    <div class="format-tile format-tall reveal"><span>REEL / STORY</span><strong>9:16</strong><small>برای دیده‌شدن سریع</small></div>
                    <div class="format-tile format-wide reveal"><span>STYLE</span><strong>∞</strong><small>۸ سبک · ۶ هدف</small></div>
                </div>
            </section>

            {{-- ============ WHY HALE / COMPARISON ============ --}}
            <section class="why-section" id="why">
                <div class="section-number">۰۴ — چرا Hale؟</div>
                <div class="why-head">
                    <h2 class="reveal">عکاسی سنتی؟<br><em>هفته‌ها صبر.</em></h2>
                    <p class="reveal">یک عکاسی محصول یعنی هماهنگی عکاس، استودیو و نور؛ بعد صبر برای ویرایش. Hale همین مسیر را به چند ثانیه می‌رساند.</p>
                </div>
                <div class="compare-grid">
                    <article class="compare-card old reveal">
                        <header><span>روش قدیمی</span><b>عکاسی سنتی</b></header>
                        <ul>
                            <li>هزینه هر تیر عکاسی، بالا و پیش‌پرداختی</li>
                            <li>هماهنگی استودیو، عکاس و مدل — حداقل یک هفته</li>
                            <li>هر تغییر یعنی یک عکاسی جدید</li>
                            <li>خروجی فقط برای یک فرمت تحویل داده می‌شود</li>
                        </ul>
                    </article>
                    <article class="compare-card new reveal">
                        <header><span>با Hale</span><b>استودیوی هوش مصنوعی</b></header>
                        <ul>
                            <li>چند Credit برای هر خروجی؛ بدون هزینه اولیه</li>
                            <li>از آپلود تا دانلود، کمتر از ۵ دقیقه</li>
                            <li>Regenerate نامحدود تا رسیدن به قاب دلخواه</li>
                            <li>خروجی برای پست، استوری، Reel و TikTok</li>
                        </ul>
                        <button class="show-cta" data-open-auth="register">مسیر جدید را امتحان کن <span>←</span></button>
                    </article>
                </div>
            </section>

            {{-- ============ FAQ ============ --}}
            <section class="faq-section" id="faq">
                <div class="section-number">۰۵ — سؤال‌های پرتکرار</div>
                <h2 class="reveal">هرچه باید<br><em>بدانی.</em></h2>
                <div class="faq-list">
                    <details class="reveal" open>
                        <summary>آیا به دانش فنی یا نوشتن Prompt نیاز دارم؟</summary>
                        <p>نه. Hale دقیقاً برای همین ساخته شده: فقط عکس محصول را آپلود می‌کنی و هدف و سبک را از میان گزینه‌های تصویری انتخاب می‌کنی. نوشتن توضیحات فنی با ما است.</p>
                    </details>
                    <details class="reveal">
                        <summary>خروجی‌ها حق استفاده تجاری دارند؟</summary>
                        <p>بله. هر خروجی‌ای که با اعتبار حساب خودت بسازی، مالکیت استفاده تجاری‌اش با توست؛ در پوستر، پست، کمپین یا بسته‌بندی محصول.</p>
                    </details>
                    <details class="reveal">
                        <summary>Credit چطور مصرف می‌شود؟</summary>
                        <p>قبل از هر تولید، هزینه دقیق را می‌بینی و اعتبار همان لحظه رزرو می‌شود. اگر تولید شکست بخورد، Credit به‌صورت خودکار و کامل برمی‌گردد. با تأیید شماره موبایل هم ۳۰ Credit رایگان می‌گیری.</p>
                    </details>
                    <details class="reveal">
                        <summary>ویدئو روی کدام پلن‌ها فعال است؟</summary>
                        <p>تولید Reel و TikTok با مدت ۵، ۸ یا ۱۰ ثانیه روی پلن‌های Starter و Creator فعال است؛ پلن رایگان برای شروع، تصویر نامحدود می‌سازد.</p>
                    </details>
                    <details class="reveal">
                        <summary>اگر از خروجی راضی نباشم چه؟</summary>
                        <p>روی همان صحنه «ساخت دوباره» می‌زنی؛ با همان تنظیمات، قاب تازه‌ای می‌سازی. بازخورد 👍/👎 هم ثبت می‌شود تا خروجی‌های بعدی دقیق‌تر شوند.</p>
                    </details>
                    <details class="reveal">
                        <summary>عکس محصولم کجا نگه داشته می‌شود؟</summary>
                        <p>فقط در کتابخانه خصوصی خودت، روی فضای ابری اختصاصی؛ هیچ محصولی بدون اجازه تو در جای دیگری استفاده یا نمایش داده نمی‌شود.</p>
                    </details>
                </div>
            </section>

            {{-- ============ CLOSING ============ --}}
            <section class="about-section" id="about">
                <div class="section-number">۰۶ — Hale</div>
                <div class="about-body">
                    <h2 class="reveal">کمتر توضیح بده.<br><em>بیشتر نشان بده.</em></h2>
                    <p class="reveal">برای فروشگاه‌ها و برندهایی که می‌خواهند محصولشان حرفه‌ای دیده شود — بدون اینکه هر بار عکاسی و طراحی را از صفر شروع کنند.</p>
                    <ul class="about-facts reveal">
                        <li><b>۵ دقیقه</b><span>یا کمتر تا اولین خروجی</span></li>
                        <li><b>۸ سبک</b><span>از Luxury تا Dark</span></li>
                        <li><b>۴ فرمت</b><span>پست، استوری، Reel، TikTok</span></li>
                        <li><b>۳۰ Credit</b><span>هدیه شروع</span></li>
                    </ul>
                    <button class="hero-link" data-open-auth="register">شروع با ۳۰ Credit رایگان <span>↗</span></button>
                </div>
            </section>
        </main>

        <footer class="land-footer">
            <div class="footer-mark">H<span>•</span>le</div>
            <p>AI CONTENT STUDIO<br>FOR PRODUCT STORIES</p>
            <a href="mailto:hello@hale.studio">hello@hale.studio ↗</a>
            <small>© ۱۴۰۵ HALE — ساخته‌شده برای پست بعدی تو</small>
        </footer>
    </div>

    <div class="auth-modal" data-auth-modal hidden>
        <div class="modal-backdrop" data-close-auth></div>
        <section class="auth-panel" role="dialog" aria-modal="true" aria-labelledby="auth-title">
            <button class="close-button" data-close-auth aria-label="بستن">×</button>
            <p class="eyebrow">WELCOME TO HALE</p>
            <h2 id="auth-title">شروع ساخت</h2>
            <p class="modal-copy">با ایمیل یا شماره موبایل وارد شو.</p>
            <div class="auth-tabs"><button class="active" data-auth-tab="login">ورود</button><button data-auth-tab="register">ثبت‌نام</button></div>
            <form data-auth-form>
                <label data-name-field hidden>نام و نام خانوادگی<input name="name" autocomplete="name" placeholder="مثلاً سارا احمدی"></label>
                <label>ایمیل یا شماره موبایل<input name="identifier" autocomplete="username" inputmode="email" placeholder="sara@example.com یا +98912..."></label>
                <label>رمز عبور<input name="password" type="password" autocomplete="current-password" placeholder="حداقل ۸ کاراکتر"></label>
                <label data-confirm-field hidden>تکرار رمز عبور<input name="password_confirmation" type="password" autocomplete="new-password"></label>
                <p class="form-message" data-form-message role="alert"></p>
                <button class="primary-button full-button" type="submit" data-submit-auth>ورود به Hale <span>←</span></button>
            </form>
            <p class="modal-footnote">با ثبت‌نام، مسیر ساخت اولین محتوایت شروع می‌شود.</p>
        </section>
    </div>

    <script>
        // Scroll-reveal: only marks readiness after hydration to avoid a FOUC flash
        document.documentElement.classList.add('js');
        window.addEventListener('load', () => {
            document.querySelectorAll('.reveal').forEach((el) => el.setAttribute('data-reveal-ready', ''));
            const io = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) { entry.target.classList.add('in-view'); io.unobserve(entry.target); }
                });
            }, { threshold: 0.12 });
            document.querySelectorAll('.reveal').forEach((el) => io.observe(el));
        });
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
        }
    </script>
</body>
</html>
