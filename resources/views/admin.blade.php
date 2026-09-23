<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>پنل مدیریت و پایش استودیو | Hale Command Center</title>
    @vite(['resources/css/app.css', 'resources/css/landing.css'])
</head>
<body class="dashboard-page">
    {{-- Ambient Aurora Background --}}
    <div class="aurora-mesh" aria-hidden="true">
        <div class="aurora-orb aurora-orb-1" style="opacity: 0.2;"></div>
        <div class="aurora-orb aurora-orb-2" style="opacity: 0.2;"></div>
    </div>

    <div class="app-shell" style="position: relative; z-index: 1;">
        <header class="topbar">
            <div style="display: flex; align-items: center; gap: 16px;">
                <a class="brand" href="/">H<span>•</span>le</a>
                <span class="badge-glow" style="margin-bottom: 0; font-size: 11px;">COMMAND CENTER</span>
            </div>
            <div class="topbar-actions">
                <a class="btn-glass" href="/dashboard" style="font-size: 13px; padding: 6px 16px;">
                    میز کار کاربر
                </a>
                <button class="btn-glass" id="admin-logout-btn" style="font-size: 13px; padding: 6px 14px;">
                    خروج
                </button>
            </div>
        </header>

        <main style="padding: 48px 0 80px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 36px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 24px;">
                <div>
                    <h1 style="font-size: 32px; font-weight: 800; margin: 0 0 8px;">پایش عملکرد و شاخص‌های کلیدی (PRD KPIs)</h1>
                    <p style="color: var(--text-secondary); margin: 0; font-size: 14px;">رصد زنده ۵ شاخص تصمیم‌گیری Go / No-Go و مدیریت کاربران استودیو</p>
                </div>
                <button class="btn-aurora" id="btn-refresh-metrics" style="padding: 8px 18px; font-size: 13px;">
                    <span>به‌روزرسانی آمار ↻</span>
                </button>
            </div>

            {{-- 5 PRD KPI CARDS --}}
            <div class="admin-kpi-grid" id="kpi-cards-container">
                {{-- Activation --}}
                <div class="admin-kpi-card">
                    <div class="admin-kpi-header">
                        <span>نرخ فعال‌سازی (Activation)</span>
                        <span class="admin-kpi-target">تارگت: > ۶۰٪</span>
                    </div>
                    <div class="admin-kpi-value" id="kpi-activation">--%</div>
                    <div class="admin-kpi-footer">
                        <span id="kpi-activation-status">در حال دریافت...</span>
                    </div>
                </div>

                {{-- Second Generation --}}
                <div class="admin-kpi-card">
                    <div class="admin-kpi-header">
                        <span>نرخ تولید دوم (Second Gen)</span>
                        <span class="admin-kpi-target">تارگت: > ۳۰٪</span>
                    </div>
                    <div class="admin-kpi-value" id="kpi-second-gen">--%</div>
                    <div class="admin-kpi-footer">
                        <span id="kpi-second-gen-status">در حال دریافت...</span>
                    </div>
                </div>

                {{-- Thumbs Up Rate --}}
                <div class="admin-kpi-card">
                    <div class="admin-kpi-header">
                        <span>نرخ رضایت خروجی‌ها (👍)</span>
                        <span class="admin-kpi-target">تارگت: > ۵۰٪</span>
                    </div>
                    <div class="admin-kpi-value" id="kpi-thumbs-up">--%</div>
                    <div class="admin-kpi-footer">
                        <span id="kpi-thumbs-up-status">در حال دریافت...</span>
                    </div>
                </div>

                {{-- Free to Paid --}}
                <div class="admin-kpi-card">
                    <div class="admin-kpi-header">
                        <span>تبدیل به پرداختی (Free → Paid)</span>
                        <span class="admin-kpi-target">تارگت: > ۵٪</span>
                    </div>
                    <div class="admin-kpi-value" id="kpi-free-to-paid">--%</div>
                    <div class="admin-kpi-footer">
                        <span id="kpi-free-to-paid-status">در حال دریافت...</span>
                    </div>
                </div>

                {{-- Gross Margin --}}
                <div class="admin-kpi-card">
                    <div class="admin-kpi-header">
                        <span>مارجین سود ناخالص</span>
                        <span class="admin-kpi-target">تارگت: > ۵۰٪</span>
                    </div>
                    <div class="admin-kpi-value" id="kpi-margin">--%</div>
                    <div class="admin-kpi-footer">
                        <span id="kpi-margin-status">در حال دریافت...</span>
                    </div>
                </div>
            </div>

            {{-- Revenue & AI Model Cost Overview --}}
            <div style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 20px; padding: 24px 28px; margin-bottom: 50px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px;">
                <div>
                    <small style="color: var(--text-muted); font-size: 12px; display: block;">کل درآمد ناخالص (تومان):</small>
                    <strong id="financial-revenue" style="font-size: 22px; color: #34D399; font-weight: 800;">--</strong>
                </div>
                <div>
                    <small style="color: var(--text-muted); font-size: 12px; display: block;">هزینه دلاری مصرف مدل‌های AI:</small>
                    <strong id="financial-cost-usd" style="font-size: 22px; color: #F87171; font-weight: 800;">--</strong>
                </div>
                <div>
                    <small style="color: var(--text-muted); font-size: 12px; display: block;">کل کاربران ثبت‌نامی:</small>
                    <strong id="overview-users" style="font-size: 22px; color: #FFFFFF; font-weight: 800;">--</strong>
                </div>
                <div>
                    <small style="color: var(--text-muted); font-size: 12px; display: block;">کل جاب‌های تکمیل‌شده صف:</small>
                    <strong id="overview-completed-jobs" style="font-size: 22px; color: #C084FC; font-weight: 800;">--</strong>
                </div>
            </div>

            {{-- Users Management Section --}}
            <section style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 24px; padding: 32px; margin-bottom: 50px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <div>
                        <h2 style="font-size: 20px; font-weight: 800; margin: 0 0 4px;">مدیریت کاربران و حساب‌های اعتباری</h2>
                        <p style="color: var(--text-muted); font-size: 13px; margin: 0;">مشاهده سابقه، سطح اشتراک و امکان بازگشت دستی اعتبار (Refund)</p>
                    </div>
                    <input id="admin-user-search" type="search" placeholder="جستجوی نام، ایمیل یا موبایل..." style="background: rgba(255,255,255,0.04); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 8px 16px; font-size: 13px; color: #FFFFFF; outline: 0; min-width: 260px;">
                </div>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: right; font-size: 13px;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border-subtle); color: var(--text-muted);">
                                <th style="padding: 12px 16px;">کاربر</th>
                                <th style="padding: 12px 16px;">پلن فعال</th>
                                <th style="padding: 12px 16px;">موجودی کریدیت</th>
                                <th style="padding: 12px 16px;">تعداد ساخت</th>
                                <th style="padding: 12px 16px;">تاریخ عضویت</th>
                                <th style="padding: 12px 16px;">عملیات</th>
                            </tr>
                        </thead>
                        <tbody id="admin-users-table-body">
                            <tr>
                                <td colspan="6" style="padding: 32px; text-align: center; color: var(--text-muted);">در حال دریافت فهرست کاربران...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Generations Queue Monitor --}}
            <section style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 24px; padding: 32px;">
                <div style="margin-bottom: 24px;">
                    <h2 style="font-size: 20px; font-weight: 800; margin: 0 0 4px;">مانیتورینگ آخرین جاب‌های صف</h2>
                    <p style="color: var(--text-muted); font-size: 13px; margin: 0;">وضعیت زنده پردازش تصاویر و ویدیوها در ورکرها</p>
                </div>

                <div id="admin-generations-list">
                    <p style="color: var(--text-muted); padding: 20px 0; text-align: center;">در حال دریافت اطلاعات صف...</p>
                </div>
            </section>
        </main>
    </div>

    {{-- Manual Refund Modal --}}
    <div id="admin-refund-modal" class="auth-modal" hidden>
        <div class="modal-backdrop" id="admin-close-refund-backdrop"></div>
        <section class="auth-panel" style="max-width: 440px;">
            <button class="close-button" id="admin-close-refund-btn" aria-label="بستن">×</button>
            <h3 style="font-size: 20px; font-weight: 800; margin: 0 0 8px;">شارژ یا بازگشت دستی اعتبار (Refund)</h3>
            <p style="color: var(--text-muted); font-size: 12px; margin: 0 0 18px;">این عملیات در دفتر کل تراکنش‌ها به صورت رسمی ثبت می‌شود.</p>

            <form id="admin-refund-form">
                <input type="hidden" id="refund-user-id">
                <label>
                    کاربر مقصد:
                    <input id="refund-user-name" readonly style="background: rgba(255,255,255,0.02); color: var(--text-secondary); cursor: not-allowed;">
                </label>
                <label style="margin-top: 14px;">
                    تعداد اعتبار جهت بازگشت (Credit):
                    <input id="refund-amount" type="number" min="1" required placeholder="مثلاً ۱۰">
                </label>
                <label style="margin-top: 14px;">
                    دلیل بازگشت یا پاداش دستی:
                    <input id="refund-reason" required placeholder="مثلاً جبران خطای موقت مدل هوش مصنوعی">
                </label>
                <p id="refund-msg" class="form-message"></p>
                <button class="btn-aurora full-button" type="submit" style="padding: 12px; border-radius: 12px;">
                    ثبت و اعمال افزایش اعتبار
                </button>
            </form>
        </section>
    </div>

    {{-- Script for fetching Admin Data --}}
    <script>
        // Session is carried by an HttpOnly cookie; no token lives in
        // localStorage anymore. adminFetch forwards the cookie and redirects
        // to the landing page when the session expires (401).
        const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        };
        const adminFetch = async (url, options = {}) => {
            const res = await fetch(url, {
                credentials: 'same-origin',
                ...options,
                headers: { ...headers, ...(options.headers || {}) }
            });
            if (res.status === 401) window.location.href = '/';
            return res;
        };

        const ensureSession = async () => {
            try {
                const res = await adminFetch('/api/user/profile');
                return res.status !== 401;
            } catch {
                return true;
            }
        };

        const loadMetrics = async () => {
            try {
                const res = await adminFetch('/api/admin/metrics');
                if (!res.ok) throw new Error('عدم دسترسی به پنل مدیریت (۴۰۳)');
                const result = await res.json();
                const d = result.data;

                // Overview
                document.getElementById('overview-users').textContent = d.overview.total_users;
                document.getElementById('overview-completed-jobs').textContent = d.overview.completed_generations;

                // Financials
                document.getElementById('financial-revenue').textContent = d.kpis.gross_margin.revenue_toman.toLocaleString('fa-IR') + ' تومان';
                document.getElementById('financial-cost-usd').textContent = '$' + d.kpis.gross_margin.cost_usd.toFixed(4);

                // KPIs
                const setKpi = (id, kpi) => {
                    const elVal = document.getElementById(`kpi-${id}`);
                    const elStatus = document.getElementById(`kpi-${id}-status`);
                    if (elVal && elStatus) {
                        elVal.textContent = kpi.rate_percentage + '%';
                        const isPass = kpi.status === 'pass';
                        elStatus.innerHTML = isPass ? '<span class="kpi-pass">✓ در محدوده هدف Go</span>' : '<span class="kpi-attention">⚠ نیاز به بهینه‌سازی</span>';
                    }
                };

                setKpi('activation', d.kpis.activation);
                setKpi('second-gen', d.kpis.second_generation);
                setKpi('thumbs-up', d.kpis.thumbs_up_rate);
                setKpi('free-to-paid', d.kpis.free_to_paid);

                const marginEl = document.getElementById('kpi-margin');
                const marginStatus = document.getElementById('kpi-margin-status');
                marginEl.textContent = d.kpis.gross_margin.margin_percentage + '%';
                marginStatus.innerHTML = d.kpis.gross_margin.status === 'pass' ? '<span class="kpi-pass">✓ سودآور</span>' : '<span class="kpi-attention">تراز اولیه</span>';

            } catch (err) {
                console.error(err);
                document.getElementById('kpi-cards-container').innerHTML = `<p style="color: #F87171; padding: 20px;">خطا در دریافت اطلاعات مدیریت: ${err.message}. لطفاً با حساب ادمین لاگین کنید.</p>`;
            }
        };

        const loadUsers = async (search = '') => {
            try {
                const query = search ? `?search=${encodeURIComponent(search)}` : '';
                const res = await adminFetch(`/api/admin/users${query}`);
                if (!res.ok) return;
                const result = await res.json();
                const users = result.data?.data || [];
                const tbody = document.getElementById('admin-users-table-body');
                if (!users.length) {
                    tbody.innerHTML = '<tr><td colspan="6" style="padding: 24px; text-align: center; color: var(--text-muted);">کاربری یافت نشد.</td></tr>';
                    return;
                }

                tbody.innerHTML = users.map(u => `
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.04);">
                        <td style="padding: 14px 16px;">
                            <strong style="color: #FFFFFF; display: block;">${u.name}</strong>
                            <small style="color: var(--text-muted);">${u.email || u.phone || 'بدون شناسه'}</small>
                        </td>
                        <td style="padding: 14px 16px;"><span class="sim-chip" style="font-size: 11px;">${u.plan_key}</span></td>
                        <td style="padding: 14px 16px; font-weight: 700; color: #C084FC;">${u.credit_account?.balance ?? 0}</td>
                        <td style="padding: 14px 16px;">${u.generations_count ?? 0}</td>
                        <td style="padding: 14px 16px; color: var(--text-muted);">${u.created_at ? new Date(u.created_at).toLocaleDateString('fa-IR') : '-'}</td>
                        <td style="padding: 14px 16px;">
                            <button class="small-button" onclick="openRefundModal(${u.id}, '${u.name}')">+ بازگشت اعتبار</button>
                        </td>
                    </tr>
                `).join('');
            } catch (err) { console.error(err); }
        };

        const loadGenerations = async () => {
            try {
                const res = await adminFetch('/api/admin/generations?per_page=10');
                if (!res.ok) return;
                const result = await res.json();
                const gens = result.data?.data || [];
                const list = document.getElementById('admin-generations-list');
                if (!gens.length) {
                    list.innerHTML = '<p style="color: var(--text-muted); text-align: center;">هنوز جنریشنی ثبت نشده است.</p>';
                    return;
                }
                list.innerHTML = gens.map(g => `
                    <div class="generation-row">
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <span class="generation-icon ${g.status}">${g.type === 'video' ? '🎬' : '🖼️'}</span>
                            <div>
                                <strong style="color: #FFFFFF; font-size: 14px; display: block;">${g.user?.name || 'کاربر'} — ${g.type}</strong>
                                <small style="color: var(--text-muted); font-size: 12px;">مدل: ${g.model || 'Standard'} · هزینه: $${Number(g.cost_usd || 0).toFixed(4)}</small>
                            </div>
                        </div>
                        <span class="sim-chip ${g.status === 'completed' ? 'active' : ''}" style="font-size: 11px;">
                            ${g.status === 'completed' ? '✓ موفق' : g.status === 'failed' ? '✕ ناموفق' : '⏳ در صف'}
                        </span>
                    </div>
                `).join('');
            } catch (err) { console.error(err); }
        };

        window.openRefundModal = (id, name) => {
            document.getElementById('refund-user-id').value = id;
            document.getElementById('refund-user-name').value = name;
            document.getElementById('refund-amount').value = '';
            document.getElementById('refund-reason').value = '';
            document.getElementById('refund-msg').textContent = '';
            document.getElementById('admin-refund-modal').removeAttribute('hidden');
        };

        const closeRefund = () => document.getElementById('admin-refund-modal').setAttribute('hidden', '');
        document.getElementById('admin-close-refund-btn')?.addEventListener('click', closeRefund);
        document.getElementById('admin-close-refund-backdrop')?.addEventListener('click', closeRefund);

        document.getElementById('admin-refund-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('refund-user-id').value;
            const amount = Number(document.getElementById('refund-amount').value);
            const reason = document.getElementById('refund-reason').value;
            const msg = document.getElementById('refund-msg');
            msg.textContent = 'در حال ثبت...';

            try {
                const res = await adminFetch(`/api/admin/users/${id}/refund`, {
                    method: 'POST',
                    body: JSON.stringify({ amount, reason })
                });
                const result = await res.json();
                if (!res.ok) throw new Error(result.error?.message || 'خطا در ثبت شارژ');
                msg.className = 'form-message success-message';
                msg.textContent = `موفقیت: ${amount} کریدیت افزوده شد. موجودی جدید: ${result.data.new_balance}`;
                setTimeout(() => { closeRefund(); loadUsers(); }, 1200);
            } catch (err) {
                msg.className = 'form-message error-message';
                msg.textContent = err.message;
            }
        });

        document.getElementById('btn-refresh-metrics')?.addEventListener('click', () => {
            loadMetrics();
            loadUsers();
            loadGenerations();
        });

        document.getElementById('admin-user-search')?.addEventListener('input', (e) => {
            loadUsers(e.target.value.trim());
        });

        document.getElementById('admin-logout-btn')?.addEventListener('click', async () => {
            await adminFetch('/api/auth/logout', { method: 'POST' }).catch(() => {});
            window.location.href = '/';
        });

        ensureSession().then((sessionOk) => {
            if (!sessionOk) return;
            loadMetrics();
            loadUsers();
            loadGenerations();
        });
    </script>
</body>
</html>
