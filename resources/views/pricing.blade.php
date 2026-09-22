<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>پلن‌ها و اشتراک | Hale</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .payment-result-card {
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            animation: fadeIn 0.3s ease;
        }
        .payment-result-card.success {
            background: #e8f5e9;
            border: 1px solid #a5d6a7;
            color: #1b5e20;
        }
        .payment-result-card.failed {
            background: #ffebee;
            border: 1px solid #ef9a9a;
            color: #b71c1c;
        }
        .payment-result-details h3 {
            margin: 0 0 6px 0;
            font-size: 18px;
            font-weight: 700;
        }
        .payment-result-details p {
            margin: 0;
            font-size: 13px;
            opacity: 0.9;
        }
        .payment-result-actions {
            display: flex;
            gap: 12px;
            flex-shrink: 0;
        }
        .btn-action {
            background: #202420;
            color: #f4f0e8;
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            transition: opacity 0.2s;
        }
        .btn-action:hover {
            opacity: 0.85;
        }
        .payment-history {
            margin-top: 56px;
            border-top: 1px solid var(--line);
            padding-top: 36px;
        }
        .payment-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0;
            border-bottom: 1px solid rgba(32, 36, 32, 0.08);
            font-size: 13px;
        }
        .payment-row strong {
            font-size: 14px;
            min-width: 90px;
        }
        .payment-status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .payment-status.paid {
            background: #d4edda;
            color: #155724;
        }
        .payment-status.pending {
            background: #fff3cd;
            color: #856404;
        }
        .payment-status.failed {
            background: #f8d7da;
            color: #721c24;
        }
        .payment-actions {
            display: flex;
            gap: 10px;
        }
        .pagination-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 20px;
            font-size: 12px;
            color: #68726a;
        }
        .pagination-bar button {
            background: transparent;
            border: 1px solid var(--line);
            padding: 6px 14px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.2s;
        }
        .pagination-bar button:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .pagination-bar button:hover:not(:disabled) {
            background: rgba(32, 36, 32, 0.06);
        }
        .invoice-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 16px;
        }
        .invoice-modal[hidden] {
            display: none;
        }
        .invoice-paper {
            background: #fff;
            color: #202420;
            width: 100%;
            max-width: 540px;
            border-radius: 10px;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.2);
            padding: 32px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #202420;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .invoice-header h2 {
            margin: 0;
            font-size: 20px;
        }
        .invoice-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 20px;
            font-size: 13px;
            margin-bottom: 24px;
        }
        .invoice-item-box {
            background: #faf8f5;
            border: 1px solid #e8e4dc;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .invoice-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            border-top: 1px solid #eee;
            padding-top: 16px;
        }
        @media print {
            body * { visibility: hidden; }
            .invoice-modal, .invoice-modal * { visibility: visible; }
            .invoice-modal { position: absolute; left: 0; top: 0; width: 100%; background: none; }
            .invoice-modal-actions { display: none; }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="dashboard-page">
<div class="app-shell">
    <header class="topbar">
        <a class="brand" href="/">H<span>•</span>le</a>
        <div class="topbar-actions">
            <a class="text-button" href="/create">استودیو ساخت</a>
            <a class="text-button" href="/dashboard">داشبورد</a>
        </div>
    </header>

    <main class="pricing-page">
        <!-- Payment Result Banner -->
        <div id="payment-result-banner" class="payment-result-card" style="display: none;" data-payment-result>
            <div class="payment-result-details">
                <h3 data-payment-result-title>وضعیت پرداخت</h3>
                <p data-payment-result-desc>در حال بررسی...</p>
            </div>
            <div class="payment-result-actions" data-payment-result-actions>
                <a href="/create" class="btn-action">ورود به استودیو</a>
                <a href="/dashboard" class="btn-action" style="background: transparent; color: inherit; border: 1px solid currentColor;">داشبورد</a>
            </div>
        </div>

        <p class="eyebrow">PRICING / HALE</p>
        <div class="pricing-heading">
            <h1>پلن مناسب<br><em>رشد تو.</em></h1>
            <p>اعتبار بیشتر، فضای بیشتر برای تولید هوشمند تصویر و ویدئوی تجاری.</p>
        </div>

        <div class="pricing-grid" data-pricing-grid>
            <p class="empty-state">در حال بارگذاری پلن‌ها...</p>
        </div>

        <section class="payment-history">
            <div class="section-heading">
                <h2>تاریخچه پرداخت و فاکتورها</h2>
                <span data-payment-count></span>
            </div>
            <div class="payment-list" data-payment-list>
                <p class="empty-state">در حال بارگذاری...</p>
            </div>
            <div class="pagination-bar" data-payment-pagination style="display: none;">
                <button type="button" data-prev-page>صفحه قبلی</button>
                <span data-page-indicator>صفحه ۱</span>
                <button type="button" data-next-page>صفحه بعدی</button>
            </div>
        </section>

        <p class="form-message" data-pricing-message role="alert"></p>
    </main>
</div>

<!-- Invoice Modal -->
<div class="invoice-modal" data-invoice-modal hidden>
    <div class="invoice-paper" role="dialog" aria-modal="true" aria-labelledby="invoice-title">
        <div class="invoice-header">
            <h2 id="invoice-title">فاکتور رسمی فروش خدمات Hale</h2>
            <strong style="font-size: 18px; color: #bf646e;">H<span>•</span>le</strong>
        </div>
        <div class="invoice-details-grid">
            <div>
                <span style="color: #777; display: block;">شماره فاکتور:</span>
                <strong data-inv-number>-</strong>
            </div>
            <div>
                <span style="color: #777; display: block;">تاریخ صدور:</span>
                <span data-inv-date>-</span>
            </div>
            <div>
                <span style="color: #777; display: block;">وضعیت فاکتور:</span>
                <span class="payment-status paid" data-inv-status>پرداخت‌شده</span>
            </div>
            <div>
                <span style="color: #777; display: block;">شماره پیگیری درگاه:</span>
                <span data-inv-ref>-</span>
            </div>
        </div>
        <div class="invoice-item-box">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <strong data-inv-plan>طرح اشتراک</strong>
                <span data-inv-amount style="font-weight: 700;">- تومان</span>
            </div>
            <p style="margin: 0; font-size: 12px; color: #666;">شامل شارژ ماهیانه اعتبارات، اولویت پردازش، و دسترسی کامل به موتور هوش مصنوعی استودیو Hale.</p>
        </div>
        <div class="invoice-modal-actions">
            <button type="button" class="btn-action" onclick="window.print()">چاپ فاکتور</button>
            <button type="button" class="btn-action" style="background: transparent; color: #333; border: 1px solid #ccc;" data-close-invoice>بستن</button>
        </div>
    </div>
</div>

<script>
    const pricingToken = localStorage.getItem('hale_token');
    const pricingGrid = document.querySelector('[data-pricing-grid]');
    const pricingMessage = document.querySelector('[data-pricing-message]');
    const paymentList = document.querySelector('[data-payment-list]');
    const paymentPagination = document.querySelector('[data-payment-pagination]');
    const prevPageBtn = document.querySelector('[data-prev-page]');
    const nextPageBtn = document.querySelector('[data-next-page]');
    const pageIndicator = document.querySelector('[data-page-indicator]');
    const paymentResultBanner = document.querySelector('[data-payment-result]');
    const resultTitle = document.querySelector('[data-payment-result-title]');
    const resultDesc = document.querySelector('[data-payment-result-desc]');
    const invoiceModal = document.querySelector('[data-invoice-modal]');
    const closeInvoiceBtn = document.querySelector('[data-close-invoice]');

    let currentPage = 1;
    let lastPage = 1;

    // Handle payment status banner from query param
    const paymentStatus = new URLSearchParams(window.location.search).get('payment');
    if (paymentStatus) {
        paymentResultBanner.style.display = 'flex';
        if (paymentStatus === 'paid') {
            paymentResultBanner.className = 'payment-result-card success';
            resultTitle.textContent = 'پرداخت با موفقیت انجام شد';
            resultDesc.textContent = 'اشتراک شما فعال گردید و اعتبارات به حسابتان واریز شد. می‌توانید از هم‌اکنون پروژه‌های جدید خود را بسازید.';
            if (pricingToken) {
                fetch('/api/user/profile', {
                    headers: { Accept: 'application/json', Authorization: `Bearer ${pricingToken}` }
                }).then(res => res.json()).then(result => {
                    if (result.data) localStorage.setItem('hale_user', JSON.stringify(result.data));
                }).catch(() => {});
            }
        } else {
            paymentResultBanner.className = 'payment-result-card failed';
            resultTitle.textContent = 'پرداخت ناموفق بود';
            resultDesc.textContent = 'تراکنش از سوی درگاه بانکی تأیید نشد یا لغو گردید. در صورت کسر وجه از حسابتان، مبلغ ظرف ۷۲ ساعت توسط بانک بازگردانده خواهد شد.';
        }
    }

    const money = (value) => new Intl.NumberFormat('fa-IR').format(value / 10) + ' تومان';
    const paymentLabels = { pending: 'در انتظار پرداخت', paid: 'پرداخت موفق', failed: 'ناموفق' };

    const loadPayments = async (page = 1) => {
        if (!pricingToken) {
            paymentList.innerHTML = '<p class="empty-state">برای مشاهده تاریخچه و فاکتورها وارد حساب کاربری شوید.</p>';
            paymentPagination.style.display = 'none';
            return;
        }

        try {
            const response = await fetch(`/api/payments?page=${page}&per_page=10`, {
                headers: { Accept: 'application/json', Authorization: `Bearer ${pricingToken}` }
            });
            if (!response.ok) throw new Error('دریافت تاریخچه پرداخت انجام نشد.');

            const result = await response.json();
            const paginated = result.data || {};
            const payments = paginated.data || [];
            currentPage = paginated.current_page || 1;
            lastPage = paginated.last_page || 1;

            if (!payments.length) {
                paymentList.innerHTML = '<p class="empty-state">هنوز پرداختی ثبت نشده است.</p>';
                paymentPagination.style.display = 'none';
                return;
            }

            paymentList.innerHTML = payments.map(payment => `
                <div class="payment-row">
                    <div>
                        <strong>پلن ${payment.plan_key}</strong>
                        <div style="color: #777; font-size: 11px; margin-top: 3px;">شناسه: #${payment.id}</div>
                    </div>
                    <span>${money(payment.amount)}</span>
                    <span class="payment-status ${payment.status}">${paymentLabels[payment.status] || payment.status}</span>
                    <div class="payment-actions">
                        <button type="button" class="text-button" data-payment-receipt="${payment.id}">رسید</button>
                        ${payment.status === 'paid' ? `<button type="button" class="text-button" data-payment-invoice="${payment.id}" style="color: #bf646e; font-weight: 600;">فاکتور</button>` : ''}
                    </div>
                </div>
            `).join('');

            // Pagination setup
            if (lastPage > 1) {
                paymentPagination.style.display = 'flex';
                pageIndicator.textContent = `صفحه ${currentPage} از ${lastPage}`;
                prevPageBtn.disabled = currentPage <= 1;
                nextPageBtn.disabled = currentPage >= lastPage;
            } else {
                paymentPagination.style.display = 'none';
            }

            // Bind receipt download
            paymentList.querySelectorAll('[data-payment-receipt]').forEach(button => {
                button.addEventListener('click', async () => {
                    button.disabled = true;
                    try {
                        const receipt = await fetch(`/api/payments/${button.dataset.paymentReceipt}/receipt`, {
                            headers: { Accept: 'text/plain', Authorization: `Bearer ${pricingToken}` }
                        });
                        if (receipt.ok) {
                            const link = document.createElement('a');
                            link.href = URL.createObjectURL(await receipt.blob());
                            link.download = `payment-${button.dataset.paymentReceipt}-receipt.txt`;
                            link.click();
                        }
                    } finally {
                        button.disabled = false;
                    }
                });
            });

            // Bind invoice preview
            paymentList.querySelectorAll('[data-payment-invoice]').forEach(button => {
                button.addEventListener('click', async () => {
                    button.disabled = true;
                    try {
                        const invRes = await fetch(`/api/payments/${button.dataset.paymentInvoice}/invoice`, {
                            headers: { Accept: 'application/json', Authorization: `Bearer ${pricingToken}` }
                        });
                        if (invRes.ok) {
                            const invData = (await invRes.json()).data;
                            if (invData) {
                                document.querySelector('[data-inv-number]').textContent = invData.number || ('INV-' + button.dataset.paymentInvoice);
                                document.querySelector('[data-inv-date]').textContent = invData.issued_at ? new Date(invData.issued_at).toLocaleDateString('fa-IR') : 'امروز';
                                document.querySelector('[data-inv-amount]').textContent = money(invData.amount);
                                document.querySelector('[data-inv-ref]').textContent = invData.metadata?.reference || '-';
                                invoiceModal.hidden = false;
                            }
                        }
                    } finally {
                        button.disabled = false;
                    }
                });
            });

        } catch {
            paymentList.innerHTML = '<p class="empty-state">تاریخچه پرداخت فعلاً در دسترس نیست.</p>';
            paymentPagination.style.display = 'none';
        }
    };

    prevPageBtn.addEventListener('click', () => {
        if (currentPage > 1) loadPayments(currentPage - 1);
    });

    nextPageBtn.addEventListener('click', () => {
        if (currentPage < lastPage) loadPayments(currentPage + 1);
    });

    closeInvoiceBtn?.addEventListener('click', () => {
        invoiceModal.hidden = true;
    });

    invoiceModal?.addEventListener('click', (e) => {
        if (e.target === invoiceModal) invoiceModal.hidden = true;
    });

    loadPayments().catch(() => {});

    // Fetch Plans
    fetch('/api/plans').then(response => response.json()).then(result => {
        pricingGrid.innerHTML = result.data.filter(plan => plan.key !== 'free').map(plan => `
            <article class="pricing-card ${plan.key === 'creator' ? 'featured-plan' : ''}">
                <span class="plan-label">${plan.name}</span>
                <strong>${plan.monthly_credits}<small> Credit / ماه</small></strong>
                <p>${plan.video_limit ? `${plan.video_limit} خروجی ویدئویی در ماه` : 'ساخت تصویر نامحدود'}</p>
                <div class="plan-price">${money(plan.price_irr)}</div>
                <button class="primary-button plan-button" data-plan="${plan.key}">انتخاب پلن <span>←</span></button>
            </article>
        `).join('');

        document.querySelectorAll('[data-plan]').forEach(button => {
            button.addEventListener('click', async () => {
                if (!pricingToken) {
                    pricingMessage.className = 'form-message error-message';
                    pricingMessage.textContent = 'برای انتخاب پلن، ابتدا وارد حساب کاربری خود شوید.';
                    return;
                }
                button.disabled = true;
                button.textContent = 'در حال اتصال...';
                try {
                    const response = await fetch('/api/subscriptions/checkout', {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            Authorization: `Bearer ${pricingToken}`,
                            'Idempotency-Key': crypto.randomUUID()
                        },
                        body: JSON.stringify({ plan_key: button.dataset.plan })
                    });
                    const checkout = await response.json();
                    if (response.ok) {
                        window.location.href = checkout.data.redirect_url;
                    } else {
                        pricingMessage.textContent = response.status === 402
                            ? 'اعتبار یا پلن برای این پرداخت قابل استفاده نیست.'
                            : (checkout.error?.message || 'Checkout انجام نشد.');
                        button.disabled = false;
                        button.innerHTML = 'انتخاب پلن <span>←</span>';
                    }
                } catch {
                    pricingMessage.textContent = 'خطا در ارتباط با سرور.';
                    button.disabled = false;
                    button.innerHTML = 'انتخاب پلن <span>←</span>';
                }
            });
        });
    }).catch(() => {
        pricingMessage.textContent = 'دریافت پلن‌ها انجام نشد.';
    });
</script>
</body>
</html>
