import './bootstrap';

const modal = document.querySelector('[data-auth-modal]');
const form = document.querySelector('[data-auth-form]');
const message = document.querySelector('[data-form-message]');
const nameField = document.querySelector('[data-name-field]');
const confirmField = document.querySelector('[data-confirm-field]');
const submit = document.querySelector('[data-submit-auth]');
let authMode = 'login';

// Auth is carried by an HttpOnly cookie (set on login/register); tokens are
// no longer stored in localStorage. authFetch sends the cookie implicitly and
// sends the user home when the session has expired (401).
const rawFetch = window.fetch.bind(window);
const authFetch = async (url, options = {}) => {
	const response = await rawFetch(url, {
		credentials: 'same-origin',
		...options,
		headers: { Accept: 'application/json', ...(options.headers || {}) },
	});
	if (response.status === 401) window.location.href = '/';
	return response;
};

// --- shared helpers: debounce, safe HTML, search-term highlighting ---

const debounce = (fn, wait = 300) => {
	let timer = null;
	return (...args) => {
		clearTimeout(timer);
		timer = setTimeout(() => fn(...args), wait);
	};
};

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));

// Escape first, then wrap every occurrence of the term in <mark>; without a
// term this is just an escaped string, so user data stays inert markup.
const highlight = (value, term) => {
	const text = escapeHtml(value);
	const needle = escapeHtml(String(term ?? '').trim());
	if (!needle) return text;
	const needleLower = needle.toLowerCase();
	let result = '';
	let rest = text;
	let at = rest.toLowerCase().indexOf(needleLower);
	while (at !== -1) {
		result += rest.slice(0, at) + '<mark class="search-hit">' + rest.slice(at, at + needle.length) + '</mark>';
		rest = rest.slice(at + needle.length);
		at = rest.toLowerCase().indexOf(needleLower);
	}
	return result + rest;
};

const setAuthMode = (mode) => {
	authMode = mode;
	modal?.removeAttribute('hidden');
	if (nameField) {
		nameField.hidden = mode !== 'register';
		const nameInput = nameField.querySelector('input');
		if (nameInput) nameInput.required = mode === 'register';
	}
	if (confirmField) {
		confirmField.hidden = mode !== 'register';
		const confirmInput = confirmField.querySelector('input');
		if (confirmInput) confirmInput.required = mode === 'register';
	}
	const authTitle = document.querySelector('#auth-title');
	if (authTitle) authTitle.textContent = mode === 'register' ? 'حساب بساز' : 'خوش آمدی';
	if (submit) submit.innerHTML = mode === 'register' ? 'ساخت حساب رایگان <span>←</span>' : 'ورود به حله <span>←</span>';
	document.querySelectorAll('[data-auth-tab]').forEach((tab) => tab.classList.toggle('active', tab.dataset.authTab === mode));
	if (message) {
		message.textContent = '';
		message.className = 'form-message';
	}
};

// --- modal accessibility: move focus in, close on Escape, trap Tab, restore ---
let authOpener = null;

const focusAuthField = () => {
	const field = modal ? Array.from(modal.querySelectorAll('input')).find((el) => !el.closest('[hidden]')) : null;
	(field || modal?.querySelector('.auth-panel'))?.focus();
};

const openAuth = (mode, opener) => {
	authOpener = opener || document.activeElement;
	setAuthMode(mode);
	focusAuthField();
};

const closeAuth = () => {
	if (!modal || modal.hasAttribute('hidden')) return;
	modal.setAttribute('hidden', '');
	if (authOpener && document.contains(authOpener)) authOpener.focus();
	authOpener = null;
};

document.querySelectorAll('[data-open-auth]').forEach((button) => button.addEventListener('click', () => openAuth(button.dataset.openAuth, button)));
document.querySelectorAll('[data-close-auth]').forEach((button) => button.addEventListener('click', closeAuth));
document.querySelectorAll('[data-auth-tab]').forEach((button) => button.addEventListener('click', () => {
	setAuthMode(button.dataset.authTab);
	focusAuthField();
}));

document.addEventListener('keydown', (event) => {
	if (!modal || modal.hasAttribute('hidden')) return;

	if (event.key === 'Escape') {
		closeAuth();
		return;
	}
	if (event.key !== 'Tab') return;

	const items = Array.from(modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'))
		.filter((el) => el.getClientRects().length > 0);
	if (!items.length) return;

	const first = items[0];
	const last = items[items.length - 1];
	if (event.shiftKey && document.activeElement === first) {
		event.preventDefault();
		last.focus();
	} else if (!event.shiftKey && document.activeElement === last) {
		event.preventDefault();
		first.focus();
	} else if (!modal.contains(document.activeElement)) {
		event.preventDefault();
		first.focus();
	}
});

form?.addEventListener('submit', async (event) => {
	event.preventDefault();
	const values = Object.fromEntries(new FormData(form));
	const rawIdentifier = (values.identifier || '').trim();
	const payload = { password: values.password, device_name: 'web' };

	if (authMode === 'register') {
		payload.name = (values.name || '').trim();
		payload.password_confirmation = values.password_confirmation;
		if (rawIdentifier.includes('@')) {
			payload.email = rawIdentifier;
		} else {
			payload.phone = rawIdentifier;
		}
	} else {
		payload.identifier = rawIdentifier;
	}

	submit.disabled = true;
	message.className = 'form-message';
	message.textContent = 'در حال ارسال اطلاعات...';
	try {
		const response = await rawFetch(`/api/auth/${authMode}`, {
			credentials: 'same-origin',
			method: 'POST',
			headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
			body: JSON.stringify(payload)
		});
		const result = await response.json();
		if (!response.ok) {
			let errorMsg = result.error?.message || result.message;
			if (result.errors) {
				const firstKey = Object.keys(result.errors)[0];
				if (firstKey && Array.isArray(result.errors[firstKey]) && result.errors[firstKey][0]) {
					errorMsg = result.errors[firstKey][0];
				}
			}
			throw new Error(errorMsg || 'اطلاعات واردشده صحیح نیست.');
		}
		message.className = 'form-message success-message';
		message.textContent = authMode === 'register' ? 'حساب کاربری با موفقیت ساخته شد. در حال انتقال...' : 'ورود موفق بود. در حال انتقال...';
		setTimeout(() => { window.location.href = '/dashboard'; }, 450);
	} catch (error) {
		message.className = 'form-message error-message';
		message.textContent = error.message;
	} finally {
		submit.disabled = false;
	}
});

const credit = document.querySelector('[data-credit]');
const welcome = document.querySelector('[data-welcome]');

if (credit || welcome) {
	authFetch('/api/user/profile')
		.then(async (response) => {
			if (!response.ok) throw new Error('unauthenticated');
			return response.json();
		})
		.then((result) => {
			if (welcome) welcome.textContent = `${result.data.name}، آماده‌ای یک خروجی تازه بسازی؟`;
			if (credit) credit.textContent = result.data.credits_balance ?? '۰';

			const quota = document.querySelector('[data-quota]');
			const imageUsage = result.data.usage?.image;
			if (quota && imageUsage && imageUsage.limit > 0) {
				quota.hidden = false;
				quota.textContent = `· ${imageUsage.used}/${imageUsage.limit} تصویر`;
			}

			// The premium 2K tier is a paid-plan feature; lock it for free.
			if (result.data.plan_key === 'free') {
				document.querySelectorAll('[data-paid-only]').forEach((option) => {
					option.disabled = true;
					option.textContent = `${option.textContent} 🔒`;
				});
			}

			const unverifiedBanner = document.querySelector('[data-unverified-banner]');
			if (unverifiedBanner && result.data.requires_email_verification) {
				unverifiedBanner.removeAttribute('hidden');
			}

			const bannedBanner = document.querySelector('[data-banned-banner]');
			if (bannedBanner && result.data.is_banned) {
				bannedBanner.removeAttribute('hidden');
				const bannedMsg = document.querySelector('[data-banned-message]');
				if (bannedMsg && result.data.ban_reason) {
					bannedMsg.textContent = `حساب کاربری شما مسدود شده است (${result.data.ban_reason}). برای اطلاعات بیشتر با پشتیبانی تماس بگیرید.`;
				}
			}
		})
		.catch(() => {
			window.location.href = '/';
		});
}

const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('email_verified') === '1') {
	const verifiedBanner = document.querySelector('[data-verified-success-banner]');
	if (verifiedBanner) verifiedBanner.removeAttribute('hidden');
} else if (urlParams.get('email_verification_error')) {
	const unverifiedBanner = document.querySelector('[data-unverified-banner]');
	if (unverifiedBanner) {
		unverifiedBanner.removeAttribute('hidden');
		const resendStatus = document.querySelector('[data-resend-status]');
		if (resendStatus) resendStatus.textContent = 'لینک قبلی منقضی یا نامعتبر بود. لطفاً لینک جدید بگیرید.';
	}
}

document.querySelector('[data-resend-verification]')?.addEventListener('click', async () => {
	const resendBtn = document.querySelector('[data-resend-verification]');
	const statusEl = document.querySelector('[data-resend-status]');
	if (resendBtn) resendBtn.disabled = true;
	if (statusEl) statusEl.textContent = 'در حال ارسال...';
	try {
		const res = await authFetch('/api/auth/email/verification-notification', { method: 'POST' });
		const data = await res.json();
		if (res.ok) {
			if (statusEl) statusEl.textContent = 'لینک جدید با موفقیت ارسال شد. ایمیل خود را بررسی کنید.';
		} else {
			if (statusEl) statusEl.textContent = data.error?.message || 'ارسال نشد.';
			if (resendBtn) resendBtn.disabled = false;
		}
	} catch {
		if (statusEl) statusEl.textContent = 'خطا در برقراری ارتباط.';
		if (resendBtn) resendBtn.disabled = false;
	}
});

document.querySelector('[data-logout]')?.addEventListener('click', async () => {
	await authFetch('/api/auth/logout', { method: 'POST' }).catch(() => {});
	window.location.href = '/';
});

// ---- AI utility tools: background removal, upscale, expand, shadow ----
// Shared by the product-tile modal (dashboard) and the tools panel
// (generation page): both render the same [data-tool-*] markup, this
// module binds one controller per root element.

let imageToolsCosts = null; // { costs, balance, plan }
const loadedProductNames = new Map();

const loadImageToolsCosts = async (force = false) => {
	if (imageToolsCosts && !force) return imageToolsCosts;
	const response = await authFetch('/api/edits/costs', { headers: { Accept: 'application/json' } });
	if (!response.ok) throw new Error('دریافت تعرفهٔ ابزارها ممکن نیست.');
	imageToolsCosts = (await response.json()).data;
	return imageToolsCosts;
};

const toolErrorMessages = {
	INSUFFICIENT_CREDITS: 'اعتبار کافی نیست.',
	PREMIUM_QUALITY_REQUIRED: 'ارتقای تصویر به 2K/4K فقط در پلن پرمیوم فعال است.',
	SOURCE_NOT_FOUND: 'منبع ویرایش یافت نشد.',
	SOURCE_NOT_PROCESSABLE: 'این تصویر هنوز خروجی تکمیلی‌ای ندارد.',
};

const mountImageTools = (root, { sourceType, getSourceId }) => {
	if (!root) return null;
	const opButtons = [...root.querySelectorAll('[data-tool-op]')];
	const optionLabels = [...root.querySelectorAll('[data-tool-opt]')];
	const background = root.querySelector('[data-tool-background]');
	const target = root.querySelector('[data-tool-target]');
	const ratio = root.querySelector('[data-tool-ratio]');
	const effect = root.querySelector('[data-tool-effect]');
	const costLabel = root.querySelector('[data-tool-cost]');
	const balanceLabel = root.querySelector('[data-tool-balance]');
	const runButton = root.querySelector('[data-tool-run]');
	const status = root.querySelector('[data-tool-status]');
	const resultBox = root.querySelector('[data-tool-result]');
	const preview = root.querySelector('[data-tool-preview]');
	const download = root.querySelector('[data-tool-download]');
	const meta = root.querySelector('[data-tool-meta]');
	let operation = 'remove_bg';
	let objectUrl = null;
	let polling = false;

	const setStatus = (text, isError = false) => {
		if (!status) return;
		status.textContent = text || '';
		status.style.color = isError ? '#F87171' : 'var(--text-muted)';
	};

	const currentCost = () => {
		const costs = imageToolsCosts?.costs;
		if (!costs) return null;
		return operation === 'upscale'
			? (costs.upscale?.[target?.value || 'hd'] ?? null)
			: (costs[operation] ?? null);
	};

	const refreshLabels = () => {
		const cost = currentCost();
		if (costLabel) costLabel.textContent = cost != null ? `هزینه: ${cost} Credit` : 'تعرفه در حال بارگذاری...';
		if (balanceLabel && imageToolsCosts) balanceLabel.textContent = `موجودی: ${imageToolsCosts.balance} Credit`;
	};

	// Plan gate: free plans may only upscale to HD (server enforces it too).
	const applyPremiumGate = () => {
		const maxTarget = imageToolsCosts?.plan?.max_upscale_target || 'hd';
		if (!target) return;
		['2k', '4k'].forEach((value) => {
			const option = target.querySelector(`option[value="${value}"]`);
			if (!option) return;
			option.disabled = maxTarget === 'hd';
			if (maxTarget === 'hd' && !option.textContent.includes('پرمیوم')) option.textContent += ' (پرمیوم)';
		});
	};

	const applyOperation = () => {
		opButtons.forEach((button) => {
			const active = button.dataset.toolOp === operation;
			button.setAttribute('aria-pressed', String(active));
			button.style.color = active ? '#FFFFFF' : '';
			button.style.borderColor = active ? 'var(--primary)' : '';
		});
		optionLabels.forEach((label) => { label.hidden = label.dataset.toolOpt !== operation; });
		refreshLabels();
	};

	const refreshCosts = async () => {
		try {
			await loadImageToolsCosts(true);
			applyPremiumGate();
			refreshLabels();
		} catch {
			// Keep the stale numbers; the run itself re-validates server-side.
		}
	};

	const settleRun = () => {
		polling = false;
		if (runButton) runButton.disabled = false;
	};

	const showResult = async (edit) => {
		try {
			const response = await authFetch(`/api/edits/${edit.id}/download`, { headers: { Accept: 'image/*' } });
			if (!response.ok) throw new Error('دریافت نتیجه ممکن نیست.');
			if (objectUrl) URL.revokeObjectURL(objectUrl);
			objectUrl = URL.createObjectURL(await response.blob());
			if (preview) preview.src = objectUrl;
			if (download) {
				download.href = objectUrl;
				const mime = response.headers.get('Content-Type') || 'image/png';
				download.download = `edit-${edit.id}.${mime === 'image/webp' ? 'webp' : mime === 'image/jpeg' ? 'jpg' : 'png'}`;
			}
			if (resultBox) resultBox.hidden = false;
			if (meta) meta.textContent = `هزینه: ${edit.credits_spent} Credit`;
			setStatus('✓ ویرایش با موفقیت تکمیل شد.');
		} catch (error) {
			setStatus(error.message, true);
		}
		await refreshCosts();
	};

	const pollEdit = (editId, attempt = 0) => {
		if (!polling) return;
		setTimeout(async () => {
			if (!polling) return;
			try {
				const response = await authFetch(`/api/edits/${editId}`, { headers: { Accept: 'application/json' } });
				const result = await response.json();
				if (!response.ok) throw new Error(result.error?.message || 'پیگیری نتیجه ممکن نیست.');
				const edit = result.data;
				if (edit.status === 'completed') {
					settleRun();
					await showResult(edit);
					return;
				}
				if (edit.status === 'failed') {
					settleRun();
					setStatus('ویرایش ناموفق بود؛ اعتبار پرداخت‌شده برگشت داده شد.', true);
					await refreshCosts();
					return;
				}
				setStatus('در حال پردازش تصویر...');
				pollEdit(editId, attempt + 1);
			} catch (error) {
				// Transient network blips: retry a few times before giving up.
				if (attempt < 3) {
					pollEdit(editId, attempt + 1);
					return;
				}
				settleRun();
				setStatus(error.message, true);
			}
		}, Math.min(10000, 2000 + attempt * 1500));
	};

	const run = async () => {
		const sourceId = getSourceId();
		if (!sourceId) {
			setStatus('منبع ویرایش در دسترس نیست.', true);
			return;
		}
		const payload = { source_type: sourceType, source_id: sourceId, operation };
		if (operation === 'remove_bg' && background) payload.background = background.value;
		if (operation === 'upscale' && target) payload.target = target.value;
		if (operation === 'expand' && ratio) payload.aspect_ratio = ratio.value;
		if (operation === 'shadow' && effect) payload.effect = effect.value;

		if (runButton) runButton.disabled = true;
		polling = true;
		if (resultBox) resultBox.hidden = true;
		setStatus('در حال ارسال درخواست...');
		try {
			const response = await authFetch('/api/edits', {
				method: 'POST',
				headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
				body: JSON.stringify(payload),
			});
			const result = await response.json();
			if (!response.ok) {
				settleRun();
				if (response.status === 402) {
					setStatus(`${result.error?.message || 'اعتبار کافی نیست.'} — برای خرید اعتبار به صفحهٔ پلن‌ها بروید.`, true);
				} else if (response.status === 429) {
					setStatus('چند لحظه صبر کنید و دوباره تلاش کنید.', true);
				} else {
					const code = result.error?.code;
					setStatus(result.error?.message || result.message || toolErrorMessages[code] || 'ویرایش انجام نشد.', true);
				}
				return;
			}
			const edit = result.data;
			if (edit.status === 'completed') {
				settleRun();
				await showResult(edit);
				return;
			}
			setStatus('در صف پردازش...');
			pollEdit(edit.id);
		} catch (error) {
			settleRun();
			setStatus(error.message, true);
		}
	};

	opButtons.forEach((button) => button.addEventListener('click', () => {
		operation = button.dataset.toolOp;
		applyOperation();
	}));
	[background, target, ratio, effect].forEach((control) => control?.addEventListener('change', refreshLabels));
	runButton?.addEventListener('click', run);
	applyOperation();
	loadImageToolsCosts()
		.then(() => { applyPremiumGate(); refreshLabels(); })
		.catch(() => setStatus('تعرفهٔ ابزارها بارگذاری نشد؛ دوباره تلاش کنید.', true));

	return {
		reset() {
			polling = false;
			if (runButton) runButton.disabled = false;
			if (resultBox) resultBox.hidden = true;
			setStatus('');
		},
	};
};

// Dashboard: the product tile button opens the shared tools modal.
const editModal = document.querySelector('[data-edit-modal]');
const editSourceLabel = document.querySelector('[data-edit-source]');
let editProductId = null;
const imageToolsController = mountImageTools(editModal, { sourceType: 'product', getSourceId: () => editProductId });

// Delegated: tiles are re-rendered on every loadProducts() call.
document.querySelector('[data-product-grid]')?.addEventListener('click', (event) => {
	const button = event.target.closest('[data-tool-product]');
	if (!button) return;
	editProductId = Number(button.dataset.toolProduct);
	const name = loadedProductNames.get(editProductId);
	if (editSourceLabel) editSourceLabel.textContent = name ? `روی عکس خام «${name}»` : 'روی عکس خام محصول';
	imageToolsController?.reset();
	editModal?.removeAttribute('hidden');
});
document.querySelectorAll('[data-close-edit]').forEach((button) => button.addEventListener('click', () => editModal?.setAttribute('hidden', '')));

const productModal = document.querySelector('[data-product-modal]');
const productForm = document.querySelector('[data-product-form]');
const productGrid = document.querySelector('[data-product-grid]');
const productMessage = document.querySelector('[data-product-message]');
const productSearch = document.querySelector('[data-product-search]');
const productFormTitle = document.querySelector('[data-product-form-title]');
const productSubmit = document.querySelector('[data-product-submit]');
const productImage = productForm?.querySelector('input[name="image"]');
const productPagination = document.querySelector('[data-product-pagination]');
const productPage = document.querySelector('[data-product-page]');
const productPrev = document.querySelector('[data-product-prev]');
const productNext = document.querySelector('[data-product-next]');
let productPageNumber = 1;
let editingProductId = null;
let productsFavoriteOnly = false;

// Toggle a favorite target; returns the new state (or false on failure)
// and keeps the star button in sync.
const toggleFavorite = async (type, id, button) => {
	try {
		const response = await authFetch('/api/favorites/toggle', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ type, id }),
		});
		if (!response.ok) return false;
		const result = await response.json();
		const favorited = Boolean(result.data?.favorited);
		if (button) {
			button.setAttribute('aria-pressed', String(favorited));
			button.textContent = favorited ? '★' : '☆';
			button.setAttribute('aria-label', favorited ? 'حذف از موردعلاقه‌ها' : 'افزودن به موردعلاقه‌ها');
		}
		return favorited;
	} catch (_) {
		return false;
	}
};

const renderProductSkeletons = (count = 4) => {
	return Array.from({ length: count }).map(() => `
		<article class="product-tile product-tile-skeleton" aria-hidden="true">
			<div class="product-tile-art skeleton-shimmer"></div>
			<div class="skeleton-line skeleton-title skeleton-shimmer"></div>
			<div class="skeleton-line skeleton-desc skeleton-shimmer"></div>
			<div class="product-tile-actions">
				<div class="skeleton-button skeleton-shimmer"></div>
				<div class="skeleton-button skeleton-shimmer"></div>
			</div>
		</article>
	`).join('');
};

// --- Bulk catalog processing: pick several products, queue them at once ---
const bulkToggle = document.querySelector('[data-bulk-toggle]');
const bulkAllWrap = document.querySelector('[data-bulk-all-wrap]');
const bulkAll = document.querySelector('[data-bulk-all]');
const bulkBar = document.querySelector('[data-bulk-bar]');
const bulkCount = document.querySelector('[data-bulk-count]');
const bulkRun = document.querySelector('[data-bulk-run]');
const bulkCancel = document.querySelector('[data-bulk-cancel]');
const bulkMessage = document.querySelector('[data-bulk-message]');
let bulkMode = false;
const bulkSelected = new Set();

const updateBulkUi = () => {
	bulkToggle?.setAttribute('aria-pressed', String(bulkMode));
	productGrid?.classList.toggle('bulk-mode', bulkMode);
	if (bulkAllWrap) bulkAllWrap.hidden = !bulkMode;
	if (bulkBar) bulkBar.hidden = !bulkMode || bulkSelected.size === 0;
	if (bulkCount) bulkCount.textContent = `${bulkSelected.size} محصول انتخاب شده`;
};

const syncBulkCheckboxes = (visibleIds = []) => {
	document.querySelectorAll('[data-bulk-select]').forEach((box) => {
		const id = Number(box.dataset.bulkSelect);
		box.checked = bulkSelected.has(id);
		box.closest('.product-tile')?.classList.toggle('is-selected', box.checked);
	});
	if (bulkAll) bulkAll.checked = visibleIds.length > 0 && visibleIds.every((id) => bulkSelected.has(id));
	updateBulkUi();
};

const exitBulkMode = () => {
	bulkMode = false;
	bulkSelected.clear();
	if (bulkAll) bulkAll.checked = false;
	if (bulkMessage) {
		bulkMessage.textContent = '';
		bulkMessage.className = 'form-message';
	}
	syncBulkCheckboxes([]);
};

bulkToggle?.addEventListener('click', () => {
	bulkMode = !bulkMode;
	if (!bulkMode) exitBulkMode();
	else updateBulkUi();
});

bulkAll?.addEventListener('change', () => {
	document.querySelectorAll('[data-bulk-select]').forEach((box) => {
		box.checked = bulkAll.checked;
		box.closest('.product-tile')?.classList.toggle('is-selected', box.checked);
		const id = Number(box.dataset.bulkSelect);
		if (bulkAll.checked) bulkSelected.add(id);
		else bulkSelected.delete(id);
	});
	updateBulkUi();
});

productGrid?.addEventListener('change', (event) => {
	const box = event.target.closest('[data-bulk-select]');
	if (!box) return;
	const id = Number(box.dataset.bulkSelect);
	if (box.checked) bulkSelected.add(id);
	else bulkSelected.delete(id);
	box.closest('.product-tile')?.classList.toggle('is-selected', box.checked);
	updateBulkUi();
});

bulkCancel?.addEventListener('click', exitBulkMode);

bulkRun?.addEventListener('click', async () => {
	if (!bulkSelected.size || bulkRun.disabled) return;
	const ids = [...bulkSelected].sort((a, b) => a - b);
	const reasonLabels = {
		PLAN_LIMIT_REACHED: 'به سقف مجاز پلن رسیدیم',
		INSUFFICIENT_CREDITS: 'اعتبار کافی نیست',
		MODERATION_REJECTED: 'برخی متن‌ها از قوانین محتوایی عبور نکردند',
		INVALID_PRODUCTS: 'برخی محصولات دیگر موجود نیستند',
	};
	bulkRun.disabled = true;
	if (bulkMessage) {
		bulkMessage.className = 'form-message';
		bulkMessage.textContent = 'در حال قراردادن در صف ساخت...';
	}
	try {
		const response = await authFetch('/api/generations/bulk', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
			body: JSON.stringify({ product_ids: ids }),
		});
		const result = await response.json();
		if (!response.ok) throw new Error(result.error?.message || 'پردازش دسته‌ای انجام نشد.');
		const data = result.data || {};
		let text = `✓ ${data.created} خروجی در صف ساخت قرار گرفت.`;
		if (data.skipped) text += ` ${data.skipped} محصول رد شد (${reasonLabels[data.reason] || 'محدودیت'}).`;
		// Success exits bulk mode but keeps the report visible.
		bulkMode = false;
		bulkSelected.clear();
		if (bulkAll) bulkAll.checked = false;
		syncBulkCheckboxes([]);
		if (bulkMessage) {
			bulkMessage.className = 'form-message success-message';
			bulkMessage.textContent = text;
		}
	} catch (error) {
		// Keep the mode and the selection so the user can adjust and retry.
		if (bulkMessage) {
			bulkMessage.className = 'form-message error-message';
			bulkMessage.textContent = error.message;
		}
	} finally {
		bulkRun.disabled = false;
	}
});

const loadProducts = async () => {
	if (!productGrid) return;
	productGrid.innerHTML = renderProductSkeletons(4);
	const query = new URLSearchParams({ per_page: '12', page: String(productPageNumber) });
	if (productSearch?.value.trim()) query.set('search', productSearch.value.trim());
	if (productsFavoriteOnly) query.set('favorite', '1');
	try {
		const response = await authFetch(`/api/products?${query}`, { headers: { Accept: 'application/json' } });
		if (!response.ok) {
			productGrid.innerHTML = '<p class="empty-state">خطا در دریافت لیست محصولات.</p>';
			return;
		}
		const result = await response.json();
		const products = result.data?.data || [];
		products.forEach((product) => loadedProductNames.set(product.id, product.name));
		const pagination = result.data || {};
		productPagination.hidden = (pagination.last_page || 1) <= 1;
		productPage.textContent = `${pagination.current_page || 1} / ${pagination.last_page || 1}`;
		productPrev.disabled = (pagination.current_page || 1) <= 1;
		productNext.disabled = (pagination.current_page || 1) >= (pagination.last_page || 1);
		productGrid.innerHTML = products.length ? products.map((product) => {
			const primary = product.assets?.[0];
			const loved = Boolean(product.is_favorite);
			return `<article class="product-tile"><label class="product-select-box"><input type="checkbox" data-bulk-select="${product.id}" aria-label="انتخاب ${escapeHtml(product.name)}"></label><div class="product-tile-art ${primary ? 'skeleton-shimmer is-loading' : ''}">${primary ? `<img data-product-asset="${primary.id}" alt="${escapeHtml(product.name)}" style="opacity: 0;">` : '<b>H</b>'}</div><strong>${highlight(product.name, productSearch?.value)}</strong><small>${product.description ? highlight(product.description, productSearch?.value) : 'آماده برای ساخت محتوا'}</small><div class="product-tile-actions" style="flex-wrap: wrap;"><button class="favorite-star" type="button" data-favorite-product="${product.id}" aria-pressed="${loved}" aria-label="${loved ? 'حذف از موردعلاقه‌ها' : 'افزودن به موردعلاقه‌ها'}" title="موردعلاقه‌ها">${loved ? '★' : '☆'}</button><button class="small-button" type="button" data-tool-product="${product.id}" title="ابزارهای هوشمند تصویر روی عکس خام">ابزار ✦</button><button class="small-button" data-edit-product="${product.id}">ویرایش</button><button class="small-button" data-delete-product="${product.id}">حذف</button></div></article>`;
		}).join('') : `<p class="empty-state">${productsFavoriteOnly ? 'هنوز محصولی به موردعلاقه‌ها اضافه نکرده‌ای.' : 'محصولی با این مشخصات پیدا نشد.'}</p>`;

		syncBulkCheckboxes(products.map((product) => product.id));

		await Promise.all(products.filter((product) => product.assets?.[0]).map(async (product) => {
			const asset = product.assets[0];
			try {
				const response = await authFetch(`/api/products/${product.id}/assets/${asset.id}/download`, { headers: { Accept: 'image/*' } });
				const image = document.querySelector(`[data-product-asset="${asset.id}"]`);
				const artBox = image?.parentElement;
				if (!response.ok) {
					if (artBox) {
						artBox.classList.remove('skeleton-shimmer', 'is-loading');
						artBox.innerHTML = '<b>H</b>';
					}
					return;
				}
				if (image) {
					image.onload = () => {
						image.style.opacity = '1';
						artBox?.classList.remove('skeleton-shimmer', 'is-loading');
					};
					image.src = URL.createObjectURL(await response.blob());
				}
			} catch (_) {
				const image = document.querySelector(`[data-product-asset="${asset.id}"]`);
				image?.parentElement?.classList.remove('skeleton-shimmer', 'is-loading');
			}
		}));
	} catch (e) {
		productGrid.innerHTML = '<p class="empty-state">خطا در برقراری ارتباط با سرور.</p>';
	}
};

document.querySelectorAll('[data-open-product]').forEach((button) => button.addEventListener('click', () => { editingProductId = null; productForm?.reset(); productFormTitle.textContent = 'محصول جدید'; productSubmit.innerHTML = 'افزودن محصول <span>←</span>'; productImage.required = true; productModal?.removeAttribute('hidden'); }));
document.querySelectorAll('[data-close-product]').forEach((button) => button.addEventListener('click', () => productModal?.setAttribute('hidden', '')));
const productsFavoriteFilter = document.querySelector('[data-products-favorite-filter]');
productsFavoriteFilter?.addEventListener('click', () => {
	productsFavoriteOnly = !productsFavoriteOnly;
	productsFavoriteFilter.setAttribute('aria-pressed', String(productsFavoriteOnly));
	productPageNumber = 1;
	loadProducts();
});
productSearch?.addEventListener('input', debounce(() => { productPageNumber = 1; loadProducts(); }));
productPrev?.addEventListener('click', () => { if (productPageNumber > 1) { productPageNumber -= 1; loadProducts(); } });
productNext?.addEventListener('click', () => { productPageNumber += 1; loadProducts(); });
productGrid?.addEventListener('click', async (event) => {
	const favoriteButton = event.target.closest('[data-favorite-product]');
	if (favoriteButton) {
		const favorited = await toggleFavorite('product', favoriteButton.dataset.favoriteProduct, favoriteButton);
		if (favorited === false && productsFavoriteOnly) await loadProducts();
		return;
	}
	const editButton = event.target.closest('[data-edit-product]');
	const deleteButton = event.target.closest('[data-delete-product]');
	if (editButton) {
		const response = await authFetch(`/api/products/${editButton.dataset.editProduct}`, { headers: { Accept: 'application/json' } });
		const product = await response.json();
		const data = product.data;
		editingProductId = data.id;
		productForm.elements.name.value = data.name;
		productForm.elements.description.value = data.description || '';
		productFormTitle.textContent = 'ویرایش محصول';
		productSubmit.innerHTML = 'ذخیره تغییرات <span>←</span>';
		productImage.required = false;
		productModal?.removeAttribute('hidden');
	}
	if (deleteButton && window.confirm('این محصول و assetهای بدون استفاده حذف شوند؟')) {
		try {
			const response = await authFetch(`/api/products/${deleteButton.dataset.deleteProduct}`, { method: 'DELETE', headers: { Accept: 'application/json' } });
			if (response.ok) {
				bulkSelected.delete(Number(deleteButton.dataset.deleteProduct));
				await loadProducts();
			} else {
				const result = await response.json().catch(() => null);
				alert(result?.error?.message || 'حذف محصول انجام نشد.');
			}
		} catch (error) {
			alert('خطا در برقراری ارتباط با سرور.');
		}
	}
});
productForm?.addEventListener('submit', async (event) => {
	event.preventDefault();
	const values = new FormData(productForm);
	const button = productForm.querySelector('button[type="submit"]');
	button.disabled = true;
	productMessage.textContent = editingProductId ? 'در حال ذخیره...' : 'در حال آپلود...';
	try {
		const productResponse = await authFetch(editingProductId ? `/api/products/${editingProductId}` : '/api/products', { method: editingProductId ? 'PUT' : 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ name: values.get('name'), description: values.get('description') }) });
		const productResult = await productResponse.json();
		if (!productResponse.ok) throw new Error(productResult.error?.message || 'ذخیره محصول انجام نشد.');
		const upload = new FormData();
		if (values.get('image')?.size) {
			upload.append('image', values.get('image'));
			const assetResponse = await authFetch(`/api/products/${productResult.data.id}/assets`, { method: 'POST', headers: { Accept: 'application/json' }, body: upload });
			if (!assetResponse.ok) throw new Error('آپلود تصویر انجام نشد.');
		}
		productModal.setAttribute('hidden', '');
		productForm.reset();
		editingProductId = null;
		await loadProducts();
	} catch (error) {
		productMessage.className = 'form-message error-message';
		productMessage.textContent = error.message;
	} finally { button.disabled = false; }
});
loadProducts().catch(() => {
	if (productMessage) {
		productMessage.className = 'form-message error-message';
		productMessage.textContent = 'بارگذاری محصولات ناموفق بود.';
	}
});

const generationList = document.querySelector('[data-generation-list]');
const historySearch = document.querySelector('[data-history-search]');
const historyType = document.querySelector('[data-history-type]');
const historyStatus = document.querySelector('[data-history-status]');
const historyFrom = document.querySelector('[data-history-from]');
const historyTo = document.querySelector('[data-history-to]');
const historyFavorite = document.querySelector('[data-history-favorite]');
const historyReset = document.querySelector('[data-history-reset]');

if (generationList) {
	const historyStatusLabel = { queued: 'در صف', processing: 'در حال ساخت', completed: 'آماده', failed: 'ناموفق', cancelled: 'لغو شد' };
	let historyLoading = false;

	const historyFavoriteActive = () => historyFavorite?.getAttribute('aria-pressed') === 'true';

	const loadHistory = async () => {
		if (historyLoading) return;
		historyLoading = true;
		try {
			const query = new URLSearchParams();
			const filters = { type: historyType?.value, status: historyStatus?.value, search: historySearch?.value.trim(), from: historyFrom?.value, to: historyTo?.value };
			Object.entries(filters).forEach(([key, value]) => { if (value) query.set(key, value); });
			if (historyFavoriteActive()) query.set('favorite', '1');
			const hasFilter = Boolean(Object.values(filters).some((value) => value) || historyFavoriteActive());
			query.set('per_page', hasFilter ? '50' : '6');

			const response = await authFetch(`/api/generations?${query}`, { headers: { Accept: 'application/json' } });
			if (!response.ok) throw new Error('history');
			const result = await response.json();
			const generations = result.data?.data || [];
			generationList.innerHTML = generations.length ? generations.map((generation) => {
				const loved = Boolean(generation.is_favorite);
				return `<div class="generation-row"><span class="generation-icon ${generation.status}">${generation.type === 'video' ? '▶' : '✦'}</span><strong>${highlight(generation.creative_project?.product?.name || 'محصول', historySearch?.value)}</strong><span>${historyStatusLabel[generation.status] || generation.status}</span><small>${generation.created_at ? new Date(generation.created_at).toLocaleDateString('fa-IR') : ''}</small><button class="favorite-star" type="button" data-favorite-generation="${generation.id}" aria-pressed="${loved}" aria-label="${loved ? 'حذف از موردعلاقه‌ها' : 'افزودن به موردعلاقه‌ها'}" title="موردعلاقه‌ها">${loved ? '★' : '☆'}</button></div>`;
			}).join('') : `<p class="empty-state">${hasFilter ? 'خروجی‌ای با این فیلترها پیدا نشد.' : 'هنوز محتوایی نساخته‌ای.'}</p>`;
		} catch {
			generationList.innerHTML = '<p class="empty-state">تاریخچه فعلاً در دسترس نیست.</p>';
		} finally {
			historyLoading = false;
		}
	};

	[historyType, historyStatus, historyFrom, historyTo].forEach((element) => element?.addEventListener('change', loadHistory));
	historySearch?.addEventListener('input', debounce(loadHistory, 300));
	historyFavorite?.addEventListener('click', () => {
		historyFavorite.setAttribute('aria-pressed', String(!historyFavoriteActive()));
		loadHistory();
	});
	historyReset?.addEventListener('click', () => {
		[historyType, historyStatus, historyFrom, historyTo].forEach((element) => { if (element) element.value = ''; });
		if (historySearch) historySearch.value = '';
		historyFavorite?.setAttribute('aria-pressed', 'false');
		loadHistory();
	});
	generationList.addEventListener('click', async (event) => {
		const star = event.target.closest('[data-favorite-generation]');
		if (!star) return;
		const favorited = await toggleFavorite('generation', star.dataset.favoriteGeneration, star);
		if (favorited === false && historyFavoriteActive()) loadHistory();
	});

	loadHistory();
}

const notificationList = document.querySelector('[data-notification-list]');
if (notificationList) {
	const loadNotifications = async () => {
		const response = await authFetch('/api/notifications?per_page=8', { headers: { Accept: 'application/json' } });
		if (!response.ok) throw new Error('دریافت اعلان‌ها انجام نشد.');
		const result = await response.json();
		const notifications = result.data?.items || [];
		notificationList.innerHTML = notifications.length ? notifications.map((notification) => `<button class="notification-item ${notification.read_at ? '' : 'unread'}" data-notification-id="${notification.id}"><strong>${notification.data?.message || 'اعلان جدید'}</strong><small>${notification.created_at ? new Date(notification.created_at).toLocaleDateString('fa-IR') : ''}</small></button>`).join('') : '<p class="empty-state">اعلان جدیدی نداری.</p>';
	};
	loadNotifications().catch(() => { notificationList.innerHTML = '<p class="empty-state">اعلان‌ها فعلاً در دسترس نیستند.</p>'; });
	notificationList.addEventListener('click', async (event) => { const item = event.target.closest('[data-notification-id]'); if (!item || !item.classList.contains('unread')) return; const response = await authFetch(`/api/notifications/${item.dataset.notificationId}/read`, { method: 'POST', headers: { Accept: 'application/json' } }); if (response.ok) item.classList.remove('unread'); });
	document.querySelector('[data-read-all-notifications]')?.addEventListener('click', async () => { const response = await authFetch('/api/notifications/read-all', { method: 'POST', headers: { Accept: 'application/json' } }); if (response.ok) notificationList.querySelectorAll('.unread').forEach((item) => item.classList.remove('unread')); });
}

// --- Content calendar: month grid, per-day list and campaign builder ---
const calGrid = document.querySelector('[data-cal-grid]');
if (calGrid) {
	const calTitle = document.querySelector('[data-cal-title]');
	const calMessage = document.querySelector('[data-cal-message]');
	const calDayPanel = document.querySelector('[data-cal-day]');
	const calDayTitle = document.querySelector('[data-cal-day-title]');
	const calDayList = document.querySelector('[data-cal-day-list]');
	const campaignForm = document.querySelector('[data-campaign-form]');
	const campaignName = document.querySelector('[data-campaign-name]');
	const campaignStart = document.querySelector('[data-campaign-start]');
	const campaignInterval = document.querySelector('[data-campaign-interval]');
	const campaignOptions = document.querySelector('[data-campaign-options]');
	const campaignMessage = document.querySelector('[data-campaign-message]');
	const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
	const iso = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
	// Persian calendar rendering without any extra dependency.
	const persianDay = new Intl.DateTimeFormat('fa-IR', { calendar: 'persian', day: 'numeric' });
	const persianMonth = new Intl.DateTimeFormat('fa-IR', { calendar: 'persian', month: 'long', year: 'numeric' });
	const persianLong = new Intl.DateTimeFormat('fa-IR', { calendar: 'persian', weekday: 'long', day: 'numeric', month: 'long' });
	let cursor = new Date();
	cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
	let monthPosts = [];
	let selectedDay = null;
	let optionsLoaded = false;

	const monthRange = () => ({
		from: iso(cursor),
		to: iso(new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0)),
	});

	const calFail = (error) => {
		if (!calMessage) return;
		calMessage.textContent = error.message || 'عملیات در تقویم انجام نشد.';
		calMessage.className = 'form-message error-message';
	};

	const renderCalendar = () => {
		if (calTitle) calTitle.textContent = persianMonth.format(cursor);
		const lead = (new Date(cursor.getFullYear(), cursor.getMonth(), 1).getDay() + 1) % 7;
		const byDate = {};
		monthPosts.forEach((post) => { (byDate[post.scheduled_at] ||= []).push(post); });
		const days = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0).getDate();
		let html = '';
		for (let index = 0; index < lead; index++) html += '<span aria-hidden="true" style="min-height: 44px;"></span>';
		for (let day = 1; day <= days; day++) {
			const date = new Date(cursor.getFullYear(), cursor.getMonth(), day);
			const key = iso(date);
			const count = (byDate[key] || []).length;
			const active = key === selectedDay;
			html += `<button type="button" class="calendar-cell${count ? ' has-posts' : ''}" data-cal-key="${key}" aria-label="${key}" style="min-height: 44px; border: 1px solid ${active ? 'var(--primary)' : count ? 'var(--border-subtle)' : 'transparent'}; border-radius: 12px; background: ${count ? 'rgba(124,92,255,0.12)' : 'rgba(255,255,255,0.02)'}; color: #FFFFFF; font-size: 13px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; cursor: pointer;">${persianDay.format(date)}${count ? `<small style="font-size: 10px; color: var(--primary);">${count} پست</small>` : ''}</button>`;
		}
		calGrid.innerHTML = html;
	};

	const renderDay = (key) => {
		selectedDay = key;
		if (calDayPanel) calDayPanel.hidden = false;
		if (calDayTitle) calDayTitle.textContent = persianLong.format(new Date(`${key}T00:00:00`));
		const posts = monthPosts.filter((post) => post.scheduled_at === key);
		if (calDayList) {
			calDayList.innerHTML = posts.length ? posts.map((post) => `
				<div class="calendar-post" data-post-id="${post.id}" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; border: 1px solid var(--border-subtle); border-radius: 12px; padding: 10px 12px;">
					<a href="/generations/${post.generation?.id || ''}" style="font-size: 12px; color: var(--primary);">${esc(post.generation?.product || 'خروجی')} ↗</a>
					<span style="flex: 1; min-width: 140px; font-size: 12px; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${esc(post.caption || '')}">${esc(post.caption || 'بدون کپشن')}${post.campaign ? ` | ${esc(post.campaign.name)}` : ''}</span>
					<select data-post-status aria-label="وضعیت پست" style="background: rgba(255,255,255,0.04); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 6px 8px; color: #FFFFFF; font-size: 12px; outline: 0;">
						<option value="draft"${post.status === 'draft' ? ' selected' : ''}>پیش‌نویس</option>
						<option value="scheduled"${post.status === 'scheduled' ? ' selected' : ''}>زمان‌بندی‌شده</option>
						<option value="published"${post.status === 'published' ? ' selected' : ''}>منتشرشده</option>
					</select>
					<input type="date" data-post-date value="${post.scheduled_at}" min="${iso(new Date())}" aria-label="تاریخ انتشار" style="background: rgba(255,255,255,0.04); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 6px 8px; color: #FFFFFF; font-size: 12px; outline: 0;">
					<button type="button" class="small-button" data-post-delete aria-label="حذف پست" title="حذف پست">🗑</button>
				</div>`).join('') : '<p class="empty-state" style="font-size: 12px; padding: 8px 0;">پستی برای این روز ثبت نشده است.</p>';
		}
		renderCalendar();
	};

	const loadCalendar = async () => {
		const { from, to } = monthRange();
		const response = await authFetch(`/api/calendar/posts?from=${from}&to=${to}`, { headers: { Accept: 'application/json' } });
		const result = await response.json();
		if (!response.ok) throw new Error(result.error?.message || 'دریافت تقویم ممکن نیست.');
		monthPosts = Array.isArray(result.data) ? result.data : [];
		if (selectedDay && (selectedDay < from || selectedDay > to)) {
			selectedDay = null;
			if (calDayPanel) calDayPanel.hidden = true;
		}
		renderCalendar();
		if (selectedDay) renderDay(selectedDay);
	};

	const goToMonth = (offset) => {
		cursor = new Date(cursor.getFullYear(), cursor.getMonth() + offset, 1);
		selectedDay = null;
		if (calDayPanel) calDayPanel.hidden = true;
		loadCalendar().catch(calFail);
	};
	document.querySelector('[data-cal-prev]')?.addEventListener('click', () => goToMonth(-1));
	document.querySelector('[data-cal-next]')?.addEventListener('click', () => goToMonth(1));
	calGrid.addEventListener('click', (event) => {
		const cell = event.target.closest('[data-cal-key]');
		if (cell) renderDay(cell.dataset.calKey);
	});
	document.querySelector('[data-cal-day-close]')?.addEventListener('click', () => {
		if (calDayPanel) calDayPanel.hidden = true;
		selectedDay = null;
		renderCalendar();
	});

	calDayList.addEventListener('change', async (event) => {
		const row = event.target.closest('[data-post-id]');
		if (!row) return;
		const body = event.target.matches('[data-post-status]') ? { status: event.target.value }
			: event.target.matches('[data-post-date]') ? { scheduled_at: event.target.value } : null;
		if (!body) return;
		try {
			const response = await authFetch(`/api/calendar/posts/${row.dataset.postId}`, {
				method: 'PATCH',
				headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
				body: JSON.stringify(body),
			});
			const result = await response.json();
			if (!response.ok) throw new Error(result.error?.message || 'ذخیرهٔ تغییرات ممکن نیست.');
			await loadCalendar();
			if (selectedDay) renderDay(selectedDay);
			if (calMessage) { calMessage.textContent = 'تغییرات ذخیره شد.'; calMessage.className = 'form-message'; }
		} catch (error) { calFail(error); }
	});

	calDayList.addEventListener('click', async (event) => {
		const button = event.target.closest('[data-post-delete]');
		if (!button) return;
		const row = button.closest('[data-post-id]');
		if (!row) return;
		button.disabled = true;
		try {
			const response = await authFetch(`/api/calendar/posts/${row.dataset.postId}`, { method: 'DELETE', headers: { Accept: 'application/json' } });
			if (!response.ok) throw new Error('حذف پست ممکن نیست.');
			await loadCalendar();
			if (selectedDay) renderDay(selectedDay);
			if (calMessage) { calMessage.textContent = 'پست حذف شد.'; calMessage.className = 'form-message'; }
		} catch (error) { calFail(error); }
		finally { button.disabled = false; }
	});

	const loadCampaignOptions = async () => {
		if (optionsLoaded) return;
		const response = await authFetch('/api/generations?status=completed&per_page=50', { headers: { Accept: 'application/json' } });
		const result = await response.json();
		if (!response.ok) throw new Error(result.error?.message || 'دریافت خروجی‌ها ممکن نیست.');
		const items = Array.isArray(result.data) ? result.data : (result.data?.data || []);
		optionsLoaded = true;
		if (!items.length) {
			campaignOptions.innerHTML = '<span class="empty-state" style="font-size: 12px;">خروجی تکمیل‌شده‌ای نداری؛ اول یک خروجی بساز.</span>';
			return;
		}
		campaignOptions.innerHTML = items.map((item) => `
			<label style="display: flex; gap: 8px; align-items: center; cursor: pointer;">
				<input type="checkbox" data-campaign-gen value="${item.id}">
				<span>${esc(item.creative_project?.product?.name || 'خروجی')} · ${item.type === 'video' ? 'ویدیو' : 'تصویر'}</span>
			</label>`).join('');
	};

	document.querySelector('[data-campaign-open]')?.addEventListener('click', async () => {
		const opening = campaignForm.hidden;
		campaignForm.hidden = !opening;
		if (!opening) return;
		if (campaignMessage) { campaignMessage.textContent = ''; campaignMessage.className = 'form-message'; }
		campaignStart.value = iso(new Date());
		try { await loadCampaignOptions(); } catch (error) { calFail(error); }
	});
	document.querySelector('[data-campaign-cancel]')?.addEventListener('click', () => { campaignForm.hidden = true; });

	document.querySelector('[data-campaign-save]')?.addEventListener('click', async (event) => {
		const button = event.currentTarget;
		const ids = [...campaignOptions.querySelectorAll('[data-campaign-gen]:checked')].map((box) => Number(box.value));
		const failWith = (text) => { campaignMessage.textContent = text; campaignMessage.className = 'form-message error-message'; };
		if (!campaignName.value.trim()) return failWith('نام کمپین را بنویسید.');
		if (!campaignStart.value) return failWith('تاریخ شروع را انتخاب کنید.');
		if (!ids.length) return failWith('حداقل یک خروجی را انتخاب کنید.');
		button.disabled = true;
		try {
			const response = await authFetch('/api/campaigns', {
				method: 'POST',
				headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
				body: JSON.stringify({
					name: campaignName.value.trim(),
					generation_ids: ids,
					start_date: campaignStart.value,
					interval_days: Number(campaignInterval.value) || 1,
				}),
			});
			const result = await response.json();
			if (!response.ok) throw new Error(result.error?.message || 'ساخت کمپین انجام نشد.');
			campaignForm.hidden = true;
			campaignName.value = '';
			if (calMessage) { calMessage.textContent = `کمپین «${result.data.name}» با ${result.data.posts_count} پست زمان‌بندی شد.`; calMessage.className = 'form-message'; }
			await loadCalendar();
		} catch (error) { failWith(error.message); }
		finally { button.disabled = false; }
	});

	loadCalendar().catch(calFail);
}

// --- Web push: subscribe this browser and keep the toggle in sync ---
const pushButton = document.querySelector('[data-push-subscribe]');
if (pushButton && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window) {
	const urlBase64ToUint8Array = (base64String) => {
		const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
		const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
		const rawData = window.atob(base64);
		return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)));
	};

	const syncPushButton = async () => {
		const registration = await navigator.serviceWorker.ready;
		const subscription = await registration.pushManager.getSubscription();
		pushButton.hidden = false;
		pushButton.setAttribute('aria-pressed', String(Boolean(subscription)));
		pushButton.textContent = subscription ? '🔔 اعلان فوری فعال' : '🔔 فعال‌سازی اعلان فوری';
	};

	navigator.serviceWorker.register('/sw.js').catch(() => {});
	syncPushButton().catch(() => {});

	pushButton.addEventListener('click', async () => {
		pushButton.disabled = true;
		try {
			const registration = await navigator.serviceWorker.ready;
			const existing = await registration.pushManager.getSubscription();
			if (existing) {
				await existing.unsubscribe();
				await authFetch('/api/notifications/push/subscriptions', {
					method: 'DELETE',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ endpoint: existing.endpoint }),
				});
			} else {
				const permission = await Notification.requestPermission();
				if (permission !== 'granted') return;
				const keyResponse = await authFetch('/api/notifications/push/public-key');
				const keyResult = await keyResponse.json();
				if (!keyResult.data?.enabled) {
					pushButton.textContent = 'اعلان فوری پیکربندی نشده';
					return;
				}
				const subscription = await registration.pushManager.subscribe({
					userVisibleOnly: true,
					applicationServerKey: urlBase64ToUint8Array(keyResult.data.public_key),
				});
				await authFetch('/api/notifications/push/subscriptions', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify(subscription.toJSON()),
				});
			}
		} catch (_) {
			// Transient failure: keep the previous state, the user can retry.
		} finally {
			pushButton.disabled = false;
			syncPushButton().catch(() => {});
		}
	});
}

// --- Brand Kit: load the identity once, PUT it back on save ---
const brandKitBox = document.querySelector('[data-brand-kit]');
if (brandKitBox) {
	const brandName = brandKitBox.querySelector('[data-brand-name]');
	const brandFont = brandKitBox.querySelector('[data-brand-font]');
	const brandTone = brandKitBox.querySelector('[data-brand-tone]');
	const brandTagline = brandKitBox.querySelector('[data-brand-tagline]');
	const brandColors = [...brandKitBox.querySelectorAll('[data-brand-color]')];
	const brandSave = brandKitBox.querySelector('[data-brand-save]');
	const brandMessage = brandKitBox.querySelector('[data-brand-message]');

	const fillBrandForm = (kit) => {
		if (!kit) return;
		if (brandName && kit.name) brandName.value = kit.name;
		if (brandFont) brandFont.value = kit.font_family || '';
		if (brandTone) brandTone.value = kit.tone || '';
		if (brandTagline) brandTagline.value = kit.tagline || '';
		brandColors.forEach((input) => {
			const value = kit[input.dataset.brandColor];
			if (value) input.value = value;
		});
	};

	authFetch('/api/brand-kit', { headers: { Accept: 'application/json' } })
		.then((response) => (response.ok ? response.json() : null))
		.then((result) => fillBrandForm(result?.data))
		.catch(() => {});

	brandSave?.addEventListener('click', async () => {
		brandSave.disabled = true;
		brandMessage.className = 'form-message';
		brandMessage.textContent = '';
		try {
			const payload = {
				// Omitting an empty name keeps the stored one (partial PUT).
				name: brandName?.value.trim() || undefined,
				font_family: brandFont?.value.trim() || null,
				tone: brandTone?.value.trim() || null,
				tagline: brandTagline?.value.trim() || null,
			};
			brandColors.forEach((input) => { payload[input.dataset.brandColor] = input.value; });
			const response = await authFetch('/api/brand-kit', {
				method: 'PUT',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
				body: JSON.stringify(payload),
			});
			const result = await response.json();
			if (!response.ok) throw new Error(result.error?.message || 'ذخیرهٔ کیت برند انجام نشد.');
			brandMessage.className = 'form-message success-message';
			brandMessage.textContent = '✓ کیت برند ذخیره شد و در تولیدهای بعدی اعمال می‌شود.';
		} catch (error) {
			brandMessage.className = 'form-message error-message';
			brandMessage.textContent = error.message;
		} finally {
			brandSave.disabled = false;
		}
	});
}

// --- Seasonal studio theme: permanent picker with a persistent choice ---
const seasonStage = document.querySelector('[data-canvas-stage]');
const seasonStatus = document.querySelector('[data-season-status]');
const seasonPicker = document.querySelector('[data-season-picker]');
const seasonGrid = document.querySelector('[data-season-picker-grid]');
const seasonBadge = document.querySelector('[data-season-badge]');
const seasonSwatches = document.querySelector('[data-season-swatches]');
const seasonStateText = document.querySelector('[data-season-status-text]');
const seasonChange = document.querySelector('[data-season-change]');
const seasonToggle = document.querySelector('[data-season-toggle]');
const seasonClose = document.querySelector('[data-season-picker-close]');
const campaignBadge = document.querySelector('[data-campaign-badge]');
// Shared with the builder below: opt-in campaign pack state. The pack only
// applies when a theme is active AND the user flipped the chip on.
let campaignEnabled = (() => {
	try { return localStorage.getItem('hale-campaign-pack') === 'on'; } catch (_) { return false; }
})();
let campaignPack = null;
// Pinned theme key handed to the generation API (null = follow the server),
// so the pack the prompt inspector shows is the pack the prompt really gets.
let seasonThemeKey = null;
let seasonAutoTheme = null;
let seasonThemes = [];
// SEASON_THEME=off deployment kill switch: hides the picker entirely.
let seasonEnabled = true;
// auto | off | theme key. Persisted so refresh and navigation never lose it.
let seasonChoice = (() => {
	try { return localStorage.getItem('hale-season-theme') || 'auto'; } catch (_) { return 'auto'; }
})();
let refreshPromptInspector = () => {};

const writeSeasonChoice = (choice) => {
	seasonChoice = choice;
	try { localStorage.setItem('hale-season-theme', choice); } catch (_) {}
};

const renderCampaignChip = () => {
	if (!campaignBadge) return;
	if (!campaignPack) {
		campaignBadge.hidden = true;
		return;
	}
	campaignBadge.hidden = false;
	campaignBadge.setAttribute('aria-pressed', String(campaignEnabled));
	campaignBadge.textContent = campaignEnabled ? `✅ ${campaignPack.label}` : `🔥 ${campaignPack.label}`;
	campaignBadge.title = campaignEnabled
		? 'غیرفعال‌کردن پکیج کمپینی؛ پرامپت بدون استایل کمپینی ساخته می‌شود'
		: 'افزودن استایل کمپینی فصلی به پرامپت تولید';
};

const seasonTile = (key) => {
	if (!seasonGrid) return null;
	return Array.from(seasonGrid.querySelectorAll('[data-season-choice]'))
		.find((tile) => tile.dataset.seasonChoice === key) || null;
};

// Resolve what the studio shows: 'off' hides everything, a pinned key wins
// over the calendar, 'auto' follows the server-resolved (date-based) theme.
const resolveSeasonTheme = () => {
	if (!seasonEnabled || seasonChoice === 'off') return null;

	if (seasonChoice !== 'auto') {
		const pinned = seasonThemes.find((theme) => theme.key === seasonChoice);
		if (pinned) return pinned;

		// Catalogue not fetched yet: the server-rendered tile still carries
		// the label, palette and decor needed to decorate the stage.
		const tile = seasonTile(seasonChoice);
		if (tile && tile.dataset.palette) {
			const label = (tile.querySelector('.season-tile-name')?.textContent || seasonChoice).trim().split(/\s+/);
			return {
				key: seasonChoice,
				emoji: label[0] || '',
				name: label.slice(1).join(' ') || seasonChoice,
				decor: tile.dataset.decor || '',
				palette: tile.dataset.palette.split(','),
			};
		}
		// Unknown key (theme removed from the config): fall back to auto.
		writeSeasonChoice('auto');
	}

	return seasonAutoTheme;
};

const applySeasonTheme = () => {
	const theme = resolveSeasonTheme();
	seasonThemeKey = theme && seasonChoice !== 'auto' ? theme.key : null;

	if (seasonStage) {
		if (theme) {
			seasonStage.dataset.seasonTheme = theme.key;
			seasonStage.dataset.seasonDecor = theme.decor;
			const [bg, accent, glow] = Array.isArray(theme.palette) ? theme.palette : [];
			if (bg) seasonStage.style.setProperty('--season-bg', bg);
			if (accent) seasonStage.style.setProperty('--season-accent', accent);
			if (glow) seasonStage.style.setProperty('--season-glow', glow);
		} else {
			seasonStage.removeAttribute('data-season-theme');
			seasonStage.removeAttribute('data-season-decor');
			['--season-bg', '--season-accent', '--season-glow'].forEach((prop) => seasonStage.style.removeProperty(prop));
		}
	}

	if (seasonStatus) {
		seasonStatus.hidden = !seasonEnabled;
		if (seasonBadge) {
			seasonBadge.hidden = !theme;
			seasonBadge.textContent = theme ? `${theme.emoji} تم ${theme.name}` : '';
			seasonBadge.title = theme ? 'کلیک برای باز کردن پنل انتخاب تم' : '';
		}
		if (seasonSwatches) {
			seasonSwatches.hidden = !theme;
			seasonSwatches.innerHTML = theme
				? (Array.isArray(theme.palette) ? theme.palette : []).map((color) => `<i style="background: ${color};"></i>`).join('')
				: '';
		}
		if (seasonStateText) {
			seasonStateText.hidden = Boolean(theme);
			seasonStateText.textContent = 'تم فصلی فعال نیست';
		}
		if (seasonToggle) {
			seasonToggle.textContent = theme ? 'خاموش' : 'روشن';
			seasonToggle.setAttribute('aria-pressed', String(Boolean(theme)));
		}
	}

	if (seasonGrid) {
		seasonGrid.querySelectorAll('[data-season-choice]').forEach((tile) => {
			tile.classList.toggle('is-active', tile.dataset.seasonChoice === seasonChoice);
		});
	}

	// The campaign pack always tracks the resolved theme: turning a theme off
	// drops the chip, re-enabling it restores the saved campaign preference.
	campaignPack = theme && theme.prompt_pack
		? {
			key: theme.key,
			label: theme.campaign_label || `کمپین ${theme.name}`,
			pack: theme.prompt_pack,
		}
		: null;
	renderCampaignChip();

	refreshPromptInspector();
};

if (seasonStatus || seasonStage) {
	const setPickerOpen = (open) => {
		if (seasonPicker) seasonPicker.hidden = !open;
	};

	seasonChange?.addEventListener('click', () => setPickerOpen(Boolean(seasonPicker?.hidden)));
	seasonBadge?.addEventListener('click', () => setPickerOpen(true));
	seasonClose?.addEventListener('click', () => setPickerOpen(false));

	seasonToggle?.addEventListener('click', () => {
		writeSeasonChoice(seasonChoice === 'off' ? 'auto' : 'off');
		applySeasonTheme();
	});

	seasonGrid?.addEventListener('click', (event) => {
		const tile = event.target.closest('[data-season-choice]');
		if (!tile) return;
		writeSeasonChoice(tile.dataset.seasonChoice);
		applySeasonTheme();
		setPickerOpen(false);
	});

	campaignBadge?.addEventListener('click', () => {
		if (!campaignPack) return;
		campaignEnabled = !campaignEnabled;
		try { localStorage.setItem('hale-campaign-pack', campaignEnabled ? 'on' : 'off'); } catch (_) {}
		renderCampaignChip();
		refreshPromptInspector();
	});

	// Paint immediately from the server-rendered tiles, then refine once the
	// catalogue (auto theme + enabled flag) arrives.
	applySeasonTheme();

	authFetch('/api/creative/theme', { headers: { Accept: 'application/json' } })
		.then((response) => (response.ok ? response.json() : null))
		.then((result) => {
			const data = result?.data;
			if (!data) return;
			seasonEnabled = data.enabled !== false;
			seasonAutoTheme = data.theme || null;
			seasonThemes = Array.isArray(data.themes) ? data.themes : [];
			applySeasonTheme();
		})
		.catch(() => {});
}

const builderForm = document.querySelector('[data-builder-form]');
if (builderForm) {
	const labels = { introduction: 'معرفی محصول', sales: 'افزایش فروش', branding: 'برندینگ', promotion: 'تخفیف', launch: 'محصول جدید', engagement: 'جذب مخاطب', luxury: 'لوکس', minimal: 'مینیمال', cinematic: 'سینمایی', natural: 'طبیعی', colorful: 'رنگارنگ', dark: 'تیره', professional: 'حرفه‌ای', fashion: 'فشن', instagram_post: 'پست ۱:۱', instagram_story: 'استوری', instagram_reel: 'Reel', tiktok: 'TikTok', studio: 'استودیو', urban: 'شهری', nature: 'طبیعت', home: 'خانه و دکور', abstract: 'انتزاعی و مدرن' };
	const select = document.querySelector('[data-product-select]');
	const message = document.querySelector('[data-builder-message]');
	const formatBox = document.querySelector('[data-formats]');
	const durationField = document.querySelector('[data-duration-field]');
	const duration = document.querySelector('[data-duration]');
	const estimate = document.querySelector('[data-credit-estimate]');

	// Live Studio Preview Canvas elements
	const canvasStage = document.querySelector('[data-canvas-stage]');
	const canvasAtmosphere = document.querySelector('[data-canvas-atmosphere]');
	const canvasStyleBadge = document.querySelector('[data-canvas-style-badge]');
	const canvasFormatChip = document.querySelector('[data-canvas-format-chip]');
	const canvasProductImg = document.querySelector('[data-canvas-product-img]');
	const canvasPlaceholder = document.querySelector('[data-canvas-placeholder]');
	const canvasPlaceholderTitle = document.querySelector('[data-canvas-placeholder-title]');
	const canvasGround = document.querySelector('[data-canvas-ground]');
	const copyPromptBtn = document.querySelector('[data-copy-prompt]');
	const inspectorCode = document.querySelector('[data-inspector-code]');
	const chipProduct = document.querySelector('[data-chip-product]');
	const chipGoal = document.querySelector('[data-chip-goal]');
	const chipStyle = document.querySelector('[data-chip-style]');
	const chipEnv = document.querySelector('[data-chip-env]');
	const chipFormat = document.querySelector('[data-chip-format]');
	const chipSurface = document.querySelector('[data-chip-surface]');
	const chipProps = document.querySelector('[data-chip-props]');
	const chipCamera = document.querySelector('[data-chip-camera]');
	const chipLighting = document.querySelector('[data-chip-lighting]');
	const productsMap = new Map();
	const cachedProductBlobUrls = new Map();

	let surfacesData = [];
	let propsData = [];
	let cameraAnglesData = [];
	let lightingSetupsData = [];

	const aspectRatios = {
		instagram_post: '1:1',
		instagram_story: '9:16',
		instagram_reel: '9:16',
		tiktok: '9:16',
	};

	const updateProductArtwork = async (productId) => {
		if (!productId || !productsMap.has(String(productId))) {
			if (canvasProductImg) {
				canvasProductImg.style.display = 'none';
				canvasProductImg.src = '';
			}
			if (canvasPlaceholder) {
				canvasPlaceholder.style.display = 'flex';
				if (canvasPlaceholderTitle) canvasPlaceholderTitle.textContent = 'محصول را انتخاب کن';
			}
			return;
		}

		const product = productsMap.get(String(productId));
		const primaryAsset = product.assets?.[0];

		if (primaryAsset) {
			try {
				let blobUrl = cachedProductBlobUrls.get(primaryAsset.id);
				if (!blobUrl) {
					const response = await authFetch(`/api/products/${product.id}/assets/${primaryAsset.id}/download`, {
						headers: { Accept: 'image/*' }
					});
					if (response.ok) {
						const blob = await response.blob();
						blobUrl = URL.createObjectURL(blob);
						cachedProductBlobUrls.set(primaryAsset.id, blobUrl);
					}
				}

				if (blobUrl && canvasProductImg) {
					canvasProductImg.src = blobUrl;
					canvasProductImg.alt = product.name;
					canvasProductImg.style.display = 'block';
					canvasProductImg.style.opacity = '1';
					if (canvasPlaceholder) canvasPlaceholder.style.display = 'none';
					return;
				}
			} catch (_) {}
		}

		if (canvasProductImg) {
			canvasProductImg.style.display = 'none';
			canvasProductImg.src = '';
		}
		if (canvasPlaceholder) {
			canvasPlaceholder.style.display = 'flex';
			if (canvasPlaceholderTitle) canvasPlaceholderTitle.textContent = product.name;
		}
	};

	const updateCanvasState = () => {
		const format = formatBox?.querySelector('input[name="format"]:checked')?.value || 'instagram_post';
		const isVertical = ['instagram_story', 'instagram_reel', 'tiktok'].includes(format);
		const style = builderForm.querySelector('input[name="style"]:checked')?.value || 'luxury';
		const env = document.querySelector('[data-environment]')?.value || 'studio';
		const goal = builderForm.querySelector('input[name="goal"]:checked')?.value || 'sales';
		const surface = builderForm.querySelector('input[name="surface"]:checked')?.value || 'default';
		const props = builderForm.querySelector('input[name="props"]:checked')?.value || 'none';
		const cameraAngle = builderForm.querySelector('input[name="camera_angle"]:checked')?.value || 'eye_level';
		const lighting = builderForm.querySelector('input[name="lighting_setup"]:checked')?.value || 'softbox';

		const selectedProductId = select.value;
		const product = productsMap.get(String(selectedProductId));
		const customPromptInput = document.querySelector('[data-custom-prompt]');
		const customPromptText = (customPromptInput?.value || '').trim();

		if (canvasStage) {
			canvasStage.className = `studio-stage ${isVertical ? 'ratio-9-16' : 'ratio-1-1'} light-${lighting} angle-${cameraAngle}`;
		}

		if (canvasGround) {
			canvasGround.className = `stage-ground pedestal-${surface}`;
		}

		if (canvasFormatChip) {
			canvasFormatChip.textContent = isVertical ? '۹:۱۶ · عمودی (استوری / ریلز)' : '۱:۱ · مربعی (پست)';
		}

		if (canvasAtmosphere) {
			canvasAtmosphere.className = `stage-atmosphere style-${style}`;
		}

		if (canvasStyleBadge) {
			const styleLabel = labels[style] || style;
			const envLabel = labels[env] || env;
			canvasStyleBadge.textContent = `سبک: ${styleLabel} · محیط: ${envLabel}`;
		}

		const surfaceObj = surfacesData.find((s) => s.key === surface);
		const propsObj = propsData.find((p) => p.key === props);
		const cameraObj = cameraAnglesData.find((c) => c.key === cameraAngle);
		const lightingObj = lightingSetupsData.find((l) => l.key === lighting);

		if (chipProduct) chipProduct.textContent = `محصول: ${product ? product.name : 'انتخاب نشده'}`;
		if (chipGoal) chipGoal.textContent = `هدف: ${labels[goal] || goal}`;
		if (chipStyle) chipStyle.textContent = `سبک: ${labels[style] || style}`;
		if (chipEnv) chipEnv.textContent = `محیط: ${labels[env] || env}`;
		if (chipSurface) chipSurface.textContent = `پایه: ${surfaceObj?.label || 'استودیویی'}`;
		if (chipProps) chipProps.textContent = `اکسسوری: ${propsObj?.label || 'ساده'}`;
		if (chipCamera) chipCamera.textContent = `دوربین: ${cameraObj?.label || 'روبرو'}`;
		if (chipLighting) chipLighting.textContent = `نور: ${lightingObj?.label || 'سافت‌باکس'}`;
		if (chipFormat) chipFormat.textContent = `فرمت: ${aspectRatios[format] || '۱:۱'}`;

		if (inspectorCode) {
			if (!product) {
				inspectorCode.textContent = 'محصول مورد نظر را برای مشاهده پرامپت تولیدی انتخاب کنید...';
				return;
			}

			const productName = product.name;
			const formatRatio = aspectRatios[format] || '1:1';
			const isVideo = ['instagram_reel', 'tiktok'].includes(format);

			const capitalize = (str) => (str ? str.charAt(0).toUpperCase() + str.slice(1) : '');
			const sceneParts = [];
			if (surfaceObj?.prompt) sceneParts.push(capitalize(surfaceObj.prompt) + '.');
			if (propsObj?.prompt) sceneParts.push(capitalize(propsObj.prompt) + '.');
			if (lightingObj?.prompt) sceneParts.push(capitalize(lightingObj.prompt) + '.');
			const sceneClause = sceneParts.length ? ' ' + sceneParts.join(' ') : '';
			// Mirrors CreativeEngine::cameraClause() - the directive is
			// front-loaded right after the opening sentence.
			const cameraClause = cameraObj?.prompt ? ` Camera angle (locked): ${cameraObj.prompt}.` : '';

			let cleanCustom = customPromptText
				.replace(/<[^>]*>/g, '')
				.replace(/[\x00-\x1F\x7F]/g, '')
				.replace(/\s+/g, ' ')
				.trim()
				.replace(/[. ]+$/, '');

			let customPart = cleanCustom ? ` Custom scene details: <mark>${cleanCustom}</mark>.` : '';
			// Mirrors CreativeEngine::cameraMotion(): angle-aware movement with
			// the generic pan as the fallback when no angle is configured.
			const motion = cameraObj?.motion || 'smooth cinematic camera pan';
			let videoPart = isVideo ? ` Dynamic motion: ${motion}, fluid atmospheric movement, premium brand reel aesthetic, 4K render.` : '';
			const campaignPart = campaignEnabled && campaignPack ? ` Campaign mood: ${String(campaignPack.pack).replace(/[. ]+$/, '')}.` : '';

			const assembledPrompt = `Create a professional commercial advertising visual for ${productName}.${cameraClause} Objective: ${goal}. Aesthetic style: ${style}. Environment: ${env}. Composition: ${formatRatio} ratio (${format}).${sceneClause}${customPart}${campaignPart} High-end commercial production, photorealistic, cinematic lighting, ultra-sharp detail, preserve original product design and packaging, no distracting watermarks, no unwanted text.${videoPart}`;

			inspectorCode.innerHTML = assembledPrompt;
		}
	};

	if (copyPromptBtn && inspectorCode) {
		copyPromptBtn.addEventListener('click', async () => {
			const text = inspectorCode.innerText || inspectorCode.textContent;
			if (!text || text.includes('انتخاب کنید')) return;
			try {
				await navigator.clipboard.writeText(text);
				const prev = copyPromptBtn.innerHTML;
				copyPromptBtn.innerHTML = '✓ کپی شد!';
				copyPromptBtn.style.color = '#10B981';
				copyPromptBtn.style.borderColor = '#10B981';
				setTimeout(() => {
					copyPromptBtn.innerHTML = prev;
					copyPromptBtn.style.color = '';
					copyPromptBtn.style.borderColor = '';
				}, 2000);
			} catch (_) {}
		});
	}

	const sceneToggle = document.querySelector('[data-scene-toggle]');
	const sceneBody = document.querySelector('[data-scene-controls-body]');
	const sceneToggleIcon = document.querySelector('[data-scene-toggle-icon]');
	if (sceneToggle && sceneBody) {
		sceneToggle.addEventListener('click', () => {
			const isHidden = sceneBody.style.display === 'none';
			sceneBody.style.display = isHidden ? 'flex' : 'none';
			if (sceneToggleIcon) sceneToggleIcon.textContent = isHidden ? '▲' : '▼';
			sceneToggle.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
		});
	}

	const updateEstimate = async () => {
		const format = formatBox.querySelector('input[name="format"]:checked')?.value;
		if (!format) return;
		const type = ['instagram_reel', 'tiktok'].includes(format) ? 'video' : 'image';
		const quality = document.querySelector('[data-quality]')?.value || 'standard';
		const response = await authFetch('/api/credits/estimate', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ type, video_duration_seconds: type === 'video' ? Number(duration.value) : null, quality }) });
		const result = await response.json();
		if (!response.ok) throw new Error(result.error?.message || 'برآورد اعتبار انجام نشد.');
		if (result.data.sufficient) {
			estimate.textContent = `هزینه: ${result.data.cost} Credit | موجودی: ${result.data.balance} Credit`;
		} else {
			estimate.innerHTML = `اعتبار کافی نیست (${result.data.balance} از ${result.data.cost} Credit) | <a href="/pricing" style="color: var(--primary); font-weight: 700;">خرید اعتبار</a>`;
		}
		estimate.dataset.insufficient = result.data.sufficient ? 'false' : 'true';
	};
	const renderChoices = (target, values, name, withType = false, isObject = false) => {
		if (!target) return;
		target.innerHTML = values.map((item) => {
			const key = isObject ? item.key : (withType ? item.key : item);
			const label = isObject ? `${item.icon ? `${item.icon} ` : ''}${item.label}` : (labels[key] || key);
			const extra = withType ? `<small>${item.aspect_ratio}</small>` : '';
			return `<label class="choice"><input type="radio" name="${name}" value="${key}" required><span>${label}${extra}</span></label>`;
		}).join('');
	};
	Promise.all([
		authFetch('/api/products?per_page=50', { headers: { Accept: 'application/json' } }).then((response) => response.json()),
		authFetch('/api/creative/options', { headers: { Accept: 'application/json' } }).then((response) => response.json()),
	]).then(([products, options]) => {
		(products.data?.data || []).forEach((product) => {
			productsMap.set(String(product.id), product);
			select.insertAdjacentHTML('beforeend', `<option value="${product.id}">${product.name}</option>`);
		});
		renderChoices(document.querySelector('[data-goals]'), options.data.goals, 'goal');
		renderChoices(document.querySelector('[data-styles]'), options.data.styles, 'style');
		renderChoices(formatBox, options.data.formats, 'format', true);
		document.querySelector('[data-environment]').innerHTML = options.data.environments.map((item) => `<option value="${item}">${labels[item] || item}</option>`).join('');
		duration.innerHTML = options.data.video_durations.map((item) => `<option value="${item}">${item} ثانیه</option>`).join('');

		surfacesData = options.data.surfaces || [];
		propsData = options.data.props || [];
		cameraAnglesData = options.data.camera_angles || [];
		lightingSetupsData = options.data.lighting_setups || [];

		renderChoices(document.querySelector('[data-surfaces]'), surfacesData, 'surface', false, true);
		renderChoices(document.querySelector('[data-props]'), propsData, 'props', false, true);
		renderChoices(document.querySelector('[data-camera-angles]'), cameraAnglesData, 'camera_angle', false, true);
		renderChoices(document.querySelector('[data-lighting-setups]'), lightingSetupsData, 'lighting_setup', false, true);

		// Initialize default radio selections if needed
		const defaultGoal = builderForm.querySelector('input[name="goal"]');
		if (defaultGoal) defaultGoal.checked = true;
		const defaultStyle = builderForm.querySelector('input[name="style"][value="luxury"]') || builderForm.querySelector('input[name="style"]');
		if (defaultStyle) defaultStyle.checked = true;
		const defaultFormat = builderForm.querySelector('input[name="format"][value="instagram_post"]') || builderForm.querySelector('input[name="format"]');
		if (defaultFormat) defaultFormat.checked = true;

		const defaultSurface = builderForm.querySelector('input[name="surface"][value="default"]') || builderForm.querySelector('input[name="surface"]');
		if (defaultSurface) defaultSurface.checked = true;
		const defaultProps = builderForm.querySelector('input[name="props"][value="none"]') || builderForm.querySelector('input[name="props"]');
		if (defaultProps) defaultProps.checked = true;
		const defaultCamera = builderForm.querySelector('input[name="camera_angle"][value="eye_level"]') || builderForm.querySelector('input[name="camera_angle"]');
		if (defaultCamera) defaultCamera.checked = true;
		const defaultLighting = builderForm.querySelector('input[name="lighting_setup"][value="softbox"]') || builderForm.querySelector('input[name="lighting_setup"]');
		if (defaultLighting) defaultLighting.checked = true;

		updateCanvasState();
	}).catch(() => { message.textContent = 'دریافت گزینه‌ها انجام نشد. دوباره تلاش کن.'; });

	// The campaign chip (topbar) re-runs this so the inspector text follows.
	refreshPromptInspector = updateCanvasState;

	select.addEventListener('change', () => {
		updateProductArtwork(select.value);
		updateCanvasState();
	});
	document.querySelector('[data-goals]')?.addEventListener('change', updateCanvasState);
	document.querySelector('[data-styles]')?.addEventListener('change', updateCanvasState);
	document.querySelector('[data-environment]')?.addEventListener('change', updateCanvasState);
	document.querySelector('[data-surfaces]')?.addEventListener('change', updateCanvasState);
	document.querySelector('[data-props]')?.addEventListener('change', updateCanvasState);
	document.querySelector('[data-camera-angles]')?.addEventListener('change', updateCanvasState);
	document.querySelector('[data-lighting-setups]')?.addEventListener('change', updateCanvasState);

	const autoBestBtn = document.querySelector('[data-auto-best]');
	if (autoBestBtn) {
		autoBestBtn.addEventListener('click', async () => {
			if (!select.value) {
				message.textContent = 'ابتدا محصول مورد نظرت را انتخاب کن.';
				return;
			}
			autoBestBtn.disabled = true;
			autoBestBtn.textContent = 'در حال انتخاب بهترین ترکیب...';
			try {
				const response = await authFetch('/api/creative/preview', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ product_id: Number(select.value) })
				});
				const result = await response.json();
				if (!response.ok) throw new Error(result.error?.message || 'دریافت پیشنهاد انجام نشد.');
				const data = result.data;
				const setRadio = (name, val) => {
					const el = builderForm.querySelector(`input[name="${name}"][value="${val}"]`);
					if (el) el.checked = true;
				};
				setRadio('goal', data.goal);
				setRadio('style', data.style);
				setRadio('format', data.format);
				const envSelect = document.querySelector('[data-environment]');
				if (envSelect && data.environment) envSelect.value = data.environment;
				durationField.hidden = !['instagram_reel', 'tiktok'].includes(data.format);
				if (data.video_duration_seconds) duration.value = data.video_duration_seconds;
				message.className = 'form-message success-message';
				message.textContent = `✨ پیشنهاد خودکار: سبک ${labels[data.style] || data.style} (${labels[data.format] || data.format})`;
				await updateEstimate().catch(() => {});
				updateProductArtwork(select.value);
				updateCanvasState();
			} catch (err) {
				message.className = 'form-message error-message';
				message.textContent = err.message;
			} finally {
				autoBestBtn.disabled = false;
				autoBestBtn.textContent = '✨ خودت بهترینش رو بساز';
			}
		});
	}
	const customPromptInput = document.querySelector('[data-custom-prompt]');
	const customPromptCounter = document.querySelector('[data-custom-prompt-counter]');
	if (customPromptInput && customPromptCounter) {
		const updateCounter = () => {
			const len = customPromptInput.value.length;
			customPromptCounter.textContent = `${len.toLocaleString('fa-IR')} / ۱۰۰۰`;
		};
		customPromptInput.addEventListener('input', () => {
			updateCounter();
			updateCanvasState();
		});
		updateCounter();
	}
	formatBox.addEventListener('change', (event) => {
		durationField.hidden = !['instagram_reel', 'tiktok'].includes(event.target.value);
		updateEstimate().catch(() => {});
		updateCanvasState();
	});
	duration.addEventListener('change', () => updateEstimate().catch(() => {}));
	document.querySelector('[data-quality]')?.addEventListener('change', () => updateEstimate().catch(() => {}));

	// --- Studio templates: save the current preset, apply saved presets ---
	const templateChips = document.querySelector('[data-template-chips]');
	const templateSaveBtn = document.querySelector('[data-template-save]');
	const templateSaveRow = document.querySelector('[data-template-save-row]');
	const templateNameInput = document.querySelector('[data-template-name]');
	const templateConfirm = document.querySelector('[data-template-confirm]');
	const templateCancel = document.querySelector('[data-template-cancel]');
	const templateMessage = document.querySelector('[data-template-message]');
	const TEMPLATE_KEYS = ['goal', 'style', 'format', 'environment', 'surface', 'props', 'camera_angle', 'lighting_setup', 'video_duration_seconds', 'custom_prompt'];
	let templatesCache = [];

	const renderTemplates = () => {
		if (!templateChips) return;
		templateChips.innerHTML = templatesCache.length
			? templatesCache.map((template) => `<button class="template-chip" type="button" data-template-apply="${template.id}" title="اعمال این قالب روی فرم">${escapeHtml(template.name)}</button><button class="template-chip-delete" type="button" data-template-remove="${template.id}" aria-label="حذف قالب" title="حذف قالب">×</button>`).join('')
			: '<small data-template-empty style="color: var(--text-muted); font-size: 12px;">هنوز قالبی نداری؛ ترکیب دلخواهت را بساز و ذخیره کن تا بعداً با یک کلیک اعمال شود.</small>';
	};

	const applyTemplate = (template) => {
		const settings = template.settings || {};
		const setRadio = (name, value) => {
			const radio = builderForm.querySelector(`input[name="${name}"][value="${value}"]`);
			if (radio) {
				radio.checked = true;
				// Bubbles to the choice-grid / format listeners so the canvas,
				// duration field and credit estimate all refresh.
				radio.dispatchEvent(new Event('change', { bubbles: true }));
			}
		};
		['goal', 'style', 'format', 'surface', 'props', 'camera_angle', 'lighting_setup'].forEach((key) => {
			if (settings[key] !== undefined && settings[key] !== null) setRadio(key, String(settings[key]));
		});
		const envSelect = document.querySelector('[data-environment]');
		if (settings.environment && envSelect) {
			envSelect.value = settings.environment;
			envSelect.dispatchEvent(new Event('change', { bubbles: true }));
		}
		if (settings.video_duration_seconds && duration) {
			duration.value = String(settings.video_duration_seconds);
			duration.dispatchEvent(new Event('change', { bubbles: true }));
		}
		const customPromptInput = document.querySelector('[data-custom-prompt]');
		if (customPromptInput && settings.custom_prompt !== undefined && settings.custom_prompt !== null) {
			customPromptInput.value = settings.custom_prompt;
			customPromptInput.dispatchEvent(new Event('input', { bubbles: true }));
		}
	};

	templateChips?.addEventListener('click', async (event) => {
		const removeBtn = event.target.closest('[data-template-remove]');
		if (removeBtn) {
			try {
				const response = await authFetch(`/api/templates/${removeBtn.dataset.templateRemove}`, { method: 'DELETE', headers: { Accept: 'application/json' } });
				if (!response.ok) throw new Error('حذف قالب انجام نشد.');
				templatesCache = templatesCache.filter((template) => String(template.id) !== removeBtn.dataset.templateRemove);
				renderTemplates();
				templateMessage.className = 'form-message';
				templateMessage.textContent = 'قالب حذف شد.';
			} catch (error) {
				templateMessage.className = 'form-message error-message';
				templateMessage.textContent = error.message;
			}
			return;
		}
		const applyBtn = event.target.closest('[data-template-apply]');
		const template = applyBtn ? templatesCache.find((item) => String(item.id) === applyBtn.dataset.templateApply) : null;
		if (template) {
			applyTemplate(template);
			templateMessage.className = 'form-message success-message';
			templateMessage.textContent = `قالب «${template.name}» روی فرم اعمال شد.`;
		}
	});

	templateSaveBtn?.addEventListener('click', () => {
		if (templateSaveRow) templateSaveRow.hidden = false;
		templateNameInput?.focus();
	});
	templateCancel?.addEventListener('click', () => {
		if (templateSaveRow) templateSaveRow.hidden = true;
		if (templateNameInput) templateNameInput.value = '';
		if (templateMessage) templateMessage.textContent = '';
	});
	templateConfirm?.addEventListener('click', async () => {
		const name = templateNameInput?.value.trim();
		if (!name) {
			templateMessage.className = 'form-message error-message';
			templateMessage.textContent = 'برای قالب یک نام بنویس.';
			return;
		}
		const values = Object.fromEntries(new FormData(builderForm));
		const isVideo = ['instagram_reel', 'tiktok'].includes(values.format);
		const settings = {};
		TEMPLATE_KEYS.forEach((key) => {
			let value = values[key];
			if (value === undefined || value === null || value === '') return;
			if (key === 'video_duration_seconds') {
				if (!isVideo) return;
				value = Number(value);
			}
			settings[key] = value;
		});
		// Templates never bind to a specific product.
		delete settings.product_id;
		templateConfirm.disabled = true;
		try {
			const response = await authFetch('/api/templates', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
				body: JSON.stringify({ name, settings }),
			});
			const result = await response.json();
			if (!response.ok) throw new Error(result.error?.message || 'ذخیرهٔ قالب انجام نشد.');
			templatesCache = [result.data, ...templatesCache];
			renderTemplates();
			if (templateSaveRow) templateSaveRow.hidden = true;
			if (templateNameInput) templateNameInput.value = '';
			templateMessage.className = 'form-message success-message';
			templateMessage.textContent = `✓ قالب «${name}» ذخیره شد.`;
		} catch (error) {
			templateMessage.className = 'form-message error-message';
			templateMessage.textContent = error.message;
		} finally {
			templateConfirm.disabled = false;
		}
	});

	authFetch('/api/templates?per_page=50', { headers: { Accept: 'application/json' } })
		.then((response) => (response.ok ? response.json() : null))
		.then((result) => {
			templatesCache = result?.data?.data || [];
			renderTemplates();
		})
		.catch(() => {});

	builderForm.addEventListener('submit', async (event) => {
		event.preventDefault();
		const values = Object.fromEntries(new FormData(builderForm));
		const button = document.querySelector('[data-generate]');
		button.disabled = true;
		message.textContent = 'در حال آماده‌سازی...';
		const isVideo = ['instagram_reel', 'tiktok'].includes(values.format);
		const rawCustomPrompt = values.custom_prompt ? values.custom_prompt.trim() : null;
		const payload = {
			...values,
			surface: values.surface || null,
			props: values.props || null,
			camera_angle: values.camera_angle || null,
			lighting_setup: values.lighting_setup || null,
			custom_prompt: rawCustomPrompt || null,
			video_duration_seconds: isVideo && values.video_duration_seconds ? Number(values.video_duration_seconds) : null,
			campaign: Boolean(campaignEnabled && campaignPack),
			// Pinned seasonal theme, so the stored prompt matches the pack the
			// prompt inspector displayed; null lets the server pick by date.
			season_theme: seasonThemeKey
		};
		try {
			const response = await authFetch('/api/generations', {
				method: 'POST',
				headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
				body: JSON.stringify(payload)
			});
			const result = await response.json();
			if (!response.ok) {
				if (response.status === 402) {
					message.innerHTML = `${result.error?.message || 'اعتبار کافی نیست.'} <a href="/pricing">مشاهده پلن‌ها</a>`;
					return;
				}
				let errorMsg = result.error?.message || result.message;
				if (result.errors) {
					const firstKey = Object.keys(result.errors)[0];
					if (firstKey && Array.isArray(result.errors[firstKey]) && result.errors[firstKey][0]) {
						errorMsg = result.errors[firstKey][0];
					}
				}
				throw new Error(errorMsg || 'ساخت محتوا انجام نشد.');
			}
			window.location.href = `/generations/${result.data.id}`;
		} catch (error) {
			message.className = 'form-message error-message';
			message.textContent = error.message;
		} finally {
			button.disabled = false;
		}
	});
}

const generationPage = document.querySelector('[data-generation-id]');
if (generationPage) {
	const generationId = generationPage.dataset.generationId;
	const title = document.querySelector('[data-generation-title]');
	const copy = document.querySelector('[data-generation-copy]');
	const status = document.querySelector('[data-generation-status]');
	const progress = document.querySelector('[data-generation-progress]');
	const frame = document.querySelector('[data-result-frame]');
	const actions = document.querySelector('[data-result-actions]');
	const message = document.querySelector('[data-generation-message]');
	const creditInfo = document.querySelector('[data-generation-credit]');
	const retryButton = document.querySelector('[data-retry]');
	const regenerateButton = document.querySelector('[data-regenerate]');
	const captionButton = document.querySelector('[data-caption]');
	const toolsButton = document.querySelector('[data-tools]');
	const toolsPanel = document.querySelector('[data-tools-panel]');
	const captionPanel = document.querySelector('[data-caption-panel]');
	const captionLanguage = document.querySelector('[data-caption-language]');
	const captionTone = document.querySelector('[data-caption-tone]');
	const captionText = document.querySelector('[data-caption-text]');
	const captionTags = document.querySelector('[data-caption-tags]');
	const captionMeta = document.querySelector('[data-caption-meta]');
	const statusLabels = { queued: 'در صف پردازش...', processing: 'در حال ساخت...', completed: 'خروجی آماده است.', failed: 'ساخت محتوا ناموفق بود.', cancelled: 'تولید لغو شد.' };
	let outputUrl;
	let pollTimeoutId = null;
	let isFinished = false;

	const loadOutput = async (variant) => {
		const response = await authFetch(`/api/generations/${generationId}/download${variant ? `?variant=${variant}` : ''}`, { headers: { Accept: 'application/octet-stream' } });
		if (!response.ok) throw new Error('دریافت خروجی ممکن نیست.');
		if (outputUrl) URL.revokeObjectURL(outputUrl);
		const blob = await response.blob();
		outputUrl = URL.createObjectURL(blob);
		// The served mime decides the filename: web previews download as
		// webp, fallbacks and videos keep their real format.
		return { url: outputUrl, mime: blob.type };
	};

	let pollAttempt = 0;
	const scheduleNextPoll = () => {
		if (pollTimeoutId) clearTimeout(pollTimeoutId);
		if (!isFinished && document.visibilityState === 'visible') {
			pollAttempt++;
			const delay = Math.min(15000, Math.round(2500 * Math.pow(1.5, Math.min(pollAttempt - 1, 4))));
			pollTimeoutId = setTimeout(poll, delay);
		}
	};

	const poll = async () => {
		if (isFinished) return;
		const response = await authFetch(`/api/generations/${generationId}`, { headers: { Accept: 'application/json' } });
		const result = await response.json();
		if (!response.ok) throw new Error(result.error?.message || 'دریافت وضعیت ممکن نیست.');
		const generation = result.data;
		creditInfo.textContent = generation.status === 'completed'
			? `اعتبار مصرف‌شده: ${generation.credits_charged} Credit`
			: generation.credits_reserved > 0
				? `اعتبار رزروشده: ${generation.credits_reserved} Credit`
				: 'اعتبار هنوز رزرو نشده است';
		status.textContent = statusLabels[generation.status] || generation.status;
		progress.style.width = generation.status === 'completed' ? '100%' : generation.status === 'processing' ? '65%' : (generation.status === 'failed' || generation.status === 'cancelled') ? '0%' : '25%';
		if (generation.status === 'completed') {
			isFinished = true;
			if (pollTimeoutId) clearTimeout(pollTimeoutId);
			title.innerHTML = 'محتوا<br><em>آماده است.</em>';
			copy.textContent = 'حالا می‌توانی خروجی را دانلود کنی، بازخورد بدهی یا یک نسخه تازه بسازی.';
			const media = generation.output_media;
			const isVideo = Boolean(media?.mime?.startsWith('video'));
			// Images preview through the compressed web variant; videos and
			// variant-less fallbacks stream the original bytes.
			const output = await loadOutput(isVideo ? null : 'web');
			frame.innerHTML = isVideo ? `<video controls src="${output.url}"></video>` : `<img alt="خروجی تولیدشده" src="${output.url}">`;
			actions.hidden = false;
			const downloadLink = document.querySelector('[data-download]');
			downloadLink.href = output.url;
			const extension = output.mime === 'image/webp' ? 'webp' : output.mime === 'image/jpeg' ? 'jpg' : output.mime === 'video/mp4' ? 'mp4' : 'png';
			downloadLink.download = `generation-${generationId}.${extension}`;
			retryButton.hidden = true;
			regenerateButton.hidden = false;
			if (captionButton) captionButton.hidden = false;
			if (toolsButton) toolsButton.hidden = false;
			return;
		}
		if (generation.status === 'failed' || generation.status === 'cancelled') {
			isFinished = true;
			if (pollTimeoutId) clearTimeout(pollTimeoutId);
			const cancelled = generation.status === 'cancelled';
			title.innerHTML = cancelled ? 'ساخت محتوا<br><em>لغو شد.</em>' : 'ساخت محتوا<br><em>متوقف شد.</em>';
			message.textContent = generation.error_message || (cancelled ? 'این تولید لغو شد و اعتبار رزروشده برگشت داده شد.' : 'دوباره تلاش کن.');
			actions.hidden = false;
			retryButton.hidden = cancelled;
			regenerateButton.hidden = true;
			if (captionButton) captionButton.hidden = true;
			if (toolsButton) toolsButton.hidden = true;
			if (cancelled) document.querySelector('[data-download]').hidden = true;
			return;
		}
		scheduleNextPoll();
	};

	poll().catch((error) => { message.textContent = error.message; });

	document.addEventListener('visibilitychange', () => {
		if (isFinished) return;
		if (document.visibilityState === 'visible') {
			if (pollTimeoutId) clearTimeout(pollTimeoutId);
			poll().catch((error) => { message.textContent = error.message; });
		} else {
			if (pollTimeoutId) clearTimeout(pollTimeoutId);
		}
	});

	window.addEventListener('beforeunload', () => {
		if (pollTimeoutId) clearTimeout(pollTimeoutId);
		if (outputUrl) URL.revokeObjectURL(outputUrl);
	});
	document.querySelector('[data-feedback="positive"]')?.addEventListener('click', () => sendFeedback('positive'));
	document.querySelector('[data-feedback="negative"]')?.addEventListener('click', () => sendFeedback('negative'));
	async function sendFeedback(feedback) { await authFetch(`/api/generations/${generationId}/feedback`, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ feedback }) }); message.textContent = 'بازخوردت ثبت شد، ممنون.'; }
	document.querySelector('[data-regenerate]')?.addEventListener('click', async () => {
		const response = await authFetch(`/api/generations/${generationId}/regenerate`, { method: 'POST', headers: { Accept: 'application/json' } });
		const result = await response.json();
		if (response.ok) {
			window.location.href = `/generations/${result.data.id}`;
		} else if (response.status === 402) {
			message.innerHTML = `${result.error?.message || 'اعتبار کافی نیست.'} <a href="/pricing">مشاهده پلن‌ها</a>`;
		} else {
			message.textContent = result.error?.message || 'تولید مجدد انجام نشد.';
		}
	});

	const generateCaption = async (refresh) => {
		if (captionButton) {
			captionButton.disabled = true;
			captionButton.textContent = 'در حال ساخت...';
		}
		try {
			const response = await authFetch(`/api/generations/${generationId}/caption`, {
				method: 'POST',
				headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
				body: JSON.stringify({ language: captionLanguage?.value || 'fa', tone: captionTone?.value || 'friendly', refresh: Boolean(refresh) })
			});
			const result = await response.json();
			if (!response.ok) {
				if (response.status === 402) {
					message.innerHTML = `${result.error?.message || 'اعتبار کافی نیست.'} <a href="/pricing">خرید اعتبار</a>`;
				} else {
					message.textContent = result.error?.message || 'کپشن ساخته نشد.';
				}
				return;
			}
			if (captionText) captionText.textContent = result.data.caption;
			if (captionTags) captionTags.textContent = (result.data.hashtags || []).join(' ');
			if (captionMeta) {
				captionMeta.textContent = result.data.cached
					? 'کپشن ذخیره‌شدهٔ قبلی برگردانده شد.'
					: `هزینه: ${result.data.cost} Credit | موجودی: ${result.data.balance} Credit${result.data.source === 'ai' ? ' | ساخته‌شده با هوش مصنوعی' : ''}`;
			}
			if (captionPanel) captionPanel.hidden = false;
			message.textContent = '';
		} catch (error) {
			message.textContent = error.message;
		} finally {
			if (captionButton) {
				captionButton.disabled = false;
				captionButton.textContent = '✨ تولید کپشن';
			}
		}
	};
	captionButton?.addEventListener('click', () => generateCaption(false));
	document.querySelector('[data-caption-generate]')?.addEventListener('click', () => generateCaption(true));
	document.querySelector('[data-caption-copy]')?.addEventListener('click', async () => {
		const payload = `${captionText?.textContent || ''}\n\n${captionTags?.textContent || ''}`.trim();
		if (!payload) return;
		try {
			await navigator.clipboard.writeText(payload);
			message.textContent = 'کپشن کپی شد.';
		} catch {
			message.textContent = 'کپی در دسترس نیست؛ متن را دستی انتخاب کن.';
		}
	});
	captionLanguage?.addEventListener('change', () => { if (captionPanel && !captionPanel.hidden) generateCaption(false); });
	captionTone?.addEventListener('change', () => { if (captionPanel && !captionPanel.hidden) generateCaption(false); });
	toolsButton?.addEventListener('click', () => { if (toolsPanel) toolsPanel.hidden = !toolsPanel.hidden; });
	mountImageTools(toolsPanel, { sourceType: 'generation', getSourceId: () => Number(generationId) });
	retryButton?.addEventListener('click', async () => {
		retryButton.disabled = true;
		const response = await authFetch(`/api/generations/${generationId}/retry`, { method: 'POST', headers: { Accept: 'application/json' } });
		const result = await response.json();
		if (response.ok) {
			window.location.reload();
		} else if (response.status === 402) {
			message.innerHTML = `${result.error?.message || 'اعتبار کافی نیست.'} <a href="/pricing">مشاهده پلن‌ها</a>`;
			retryButton.disabled = false;
		} else {
			message.textContent = result.error?.message || 'تلاش مجدد انجام نشد.';
			retryButton.disabled = false;
		}
	});
}

// ---- PWA custom install prompt (beforeinstallprompt) ----
const installButton = document.querySelector('[data-install-pwa]');
if (installButton) {
	let deferredInstallPrompt = null;
	window.addEventListener('beforeinstallprompt', (event) => {
		event.preventDefault();
		deferredInstallPrompt = event;
		installButton.hidden = false;
	});
	installButton.addEventListener('click', async () => {
		if (!deferredInstallPrompt) return;
		installButton.disabled = true;
		try {
			deferredInstallPrompt.prompt();
			await deferredInstallPrompt.userChoice;
		} finally {
			deferredInstallPrompt = null;
			installButton.hidden = true;
			installButton.disabled = false;
		}
	});
	window.addEventListener('appinstalled', () => {
		installButton.hidden = true;
		deferredInstallPrompt = null;
	});
}

