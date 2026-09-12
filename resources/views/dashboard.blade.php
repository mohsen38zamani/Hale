<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>داشبورد | Hale</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="dashboard-page">
    <div class="app-shell">
        <header class="topbar"><a class="brand" href="/">H<span>•</span>le</a><div class="topbar-actions"><span class="status-dot"><i></i> فضای ساخت</span><button class="text-button" data-logout>خروج</button></div></header>
        <main class="dashboard-main">
            <p class="eyebrow">YOUR CREATIVE SPACE / ۰۱</p>
            <div class="dashboard-heading"><div><h1>امروز چی می‌خوای بسازی؟</h1><p data-welcome>در حال بارگذاری اطلاعات حساب...</p></div><div class="credit-badge"><span>اعتبار باقی‌مانده</span><strong data-credit>--</strong><small>Credit</small></div></div>
            <section class="create-grid"><button class="create-card create-card-pink" data-open-product><span class="card-number">۰۱</span><strong>عکس محصول</strong><small>یک تصویر تبلیغاتی آماده بساز</small><b>←</b></button><button class="create-card create-card-blue" data-open-product><span class="card-number">۰۲</span><strong>پست اینستاگرام</strong><small>فرمت مناسب انتشار را انتخاب کن</small><b>←</b></button><button class="create-card create-card-sand" data-open-product><span class="card-number">۰۳</span><strong>Reel کوتاه</strong><small>ویدئوی عمودی برای دیده‌شدن</small><b>←</b></button></section>
            <section class="product-library"><div class="section-heading"><h2>محصولات تو</h2><button class="small-button" data-open-product>+ محصول جدید</button></div><div class="product-grid" data-product-grid><p class="empty-state">در حال بارگذاری کتابخانه...</p></div></section>
            <section class="generation-history"><div class="section-heading"><h2>آخرین ساخت‌ها</h2><a class="quiet-link" href="/create">ساخت جدید ←</a></div><div class="generation-list" data-generation-list><p class="empty-state">در حال بارگذاری...</p></div></section>
            <section class="dashboard-lower"><div><div class="section-heading"><h2>شروع سریع</h2><span>۰۳ انتخاب</span></div><div class="quick-list"><div><b>محصولت را آماده کن</b><span>هنوز محصولی آپلود نشده</span></div><div><b>سبک مورد علاقه‌ات را انتخاب کن</b><span>Luxury · Minimal · Cinematic</span></div><div><b>یک خروجی جدید بساز</b><span>کمتر از ۵ دقیقه تا اولین نتیجه</span></div></div></div><div class="dashboard-aside"><span class="aside-mark">H</span><p>هر خروجی، شروع یک داستان تازه برای محصول توست.</p></div></section>
        </main>
    </div>
    <div class="product-modal" data-product-modal hidden><div class="modal-backdrop" data-close-product></div><section class="auth-panel product-panel" role="dialog" aria-modal="true"><button class="close-button" data-close-product aria-label="بستن">×</button><p class="eyebrow">NEW PRODUCT / ۰۱</p><h2>محصول جدید</h2><p class="modal-copy">عکس محصولت را اضافه کن تا ساخت را شروع کنیم.</p><form data-product-form><label>نام محصول<input name="name" required placeholder="مثلاً عطر بهار"></label><label>توضیح کوتاه<input name="description" placeholder="رنگ، جنس یا نکته مهم"></label><label class="file-label">عکس محصول<input name="image" type="file" accept="image/jpeg,image/png,image/webp" required></label><p class="form-message" data-product-message role="alert"></p><button class="primary-button full-button" type="submit">افزودن محصول <span>←</span></button></form></section></div>
</body>
</html>
