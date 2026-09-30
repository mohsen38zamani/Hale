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
	if (submit) submit.innerHTML = mode === 'register' ? 'ساخت حساب رایگان <span>←</span>' : 'ورود به Hale <span>←</span>';
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

const loadProducts = async () => {
	if (!productGrid) return;
	productGrid.innerHTML = renderProductSkeletons(4);
	const query = new URLSearchParams({ per_page: '12', page: String(productPageNumber) });
	if (productSearch?.value.trim()) query.set('search', productSearch.value.trim());
	try {
		const response = await authFetch(`/api/products?${query}`, { headers: { Accept: 'application/json' } });
		if (!response.ok) {
			productGrid.innerHTML = '<p class="empty-state">خطا در دریافت لیست محصولات.</p>';
			return;
		}
		const result = await response.json();
		const products = result.data?.data || [];
		const pagination = result.data || {};
		productPagination.hidden = (pagination.last_page || 1) <= 1;
		productPage.textContent = `${pagination.current_page || 1} / ${pagination.last_page || 1}`;
		productPrev.disabled = (pagination.current_page || 1) <= 1;
		productNext.disabled = (pagination.current_page || 1) >= (pagination.last_page || 1);
		productGrid.innerHTML = products.length ? products.map((product) => {
			const primary = product.assets?.[0];
			return `<article class="product-tile"><div class="product-tile-art ${primary ? 'skeleton-shimmer is-loading' : ''}">${primary ? `<img data-product-asset="${primary.id}" alt="${product.name}" style="opacity: 0;">` : '<b>H</b>'}</div><strong>${product.name}</strong><small>${product.description || 'آماده برای ساخت محتوا'}</small><div class="product-tile-actions"><button class="small-button" data-edit-product="${product.id}">ویرایش</button><button class="small-button" data-delete-product="${product.id}">حذف</button></div></article>`;
		}).join('') : '<p class="empty-state">محصولی با این مشخصات پیدا نشد.</p>';

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
productSearch?.addEventListener('input', () => loadProducts());
productPrev?.addEventListener('click', () => { if (productPageNumber > 1) { productPageNumber -= 1; loadProducts(); } });
productNext?.addEventListener('click', () => { productPageNumber += 1; loadProducts(); });
productGrid?.addEventListener('click', async (event) => {
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
if (generationList) {
	authFetch('/api/generations?per_page=6', { headers: { Accept: 'application/json' } })
		.then((response) => response.json())
		.then((result) => {
			const generations = result.data?.data || [];
			generationList.innerHTML = generations.length ? generations.map((generation) => `<a class="generation-row" href="/dashboard"><span class="generation-icon ${generation.status}">${generation.type === 'video' ? '▶' : '✦'}</span><strong>${generation.creative_project?.product?.name || 'محصول'}</strong><span>${generation.status === 'completed' ? 'آماده' : generation.status === 'failed' ? 'ناموفق' : generation.status === 'cancelled' ? 'لغو شد' : 'در حال ساخت'}</span><small>${generation.created_at ? new Date(generation.created_at).toLocaleDateString('fa-IR') : ''}</small></a>`).join('') : '<p class="empty-state">هنوز محتوایی نساخته‌ای.</p>';
		})
		.catch(() => { generationList.innerHTML = '<p class="empty-state">تاریخچه فعلاً در دسترس نیست.</p>'; });
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
			canvasStage.className = `studio-stage ${isVertical ? 'ratio-9-16' : 'ratio-1-1'} light-${lighting}`;
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
			if (cameraObj?.prompt) sceneParts.push(capitalize(cameraObj.prompt) + '.');
			if (lightingObj?.prompt) sceneParts.push(capitalize(lightingObj.prompt) + '.');
			const sceneClause = sceneParts.length ? ' ' + sceneParts.join(' ') : '';

			let cleanCustom = customPromptText
				.replace(/<[^>]*>/g, '')
				.replace(/[\x00-\x1F\x7F]/g, '')
				.replace(/\s+/g, ' ')
				.trim()
				.replace(/[. ]+$/, '');

			let customPart = cleanCustom ? ` Custom scene details: <mark>${cleanCustom}</mark>.` : '';
			let videoPart = isVideo ? ' Dynamic motion: smooth cinematic camera pan, fluid atmospheric movement, premium brand reel aesthetic, 4K render.' : '';

			const assembledPrompt = `Create a professional commercial advertising visual for ${productName}. Objective: ${goal}. Aesthetic style: ${style}. Environment: ${env}. Composition: ${formatRatio} ratio (${format}).${sceneClause}${customPart} High-end commercial production, photorealistic, cinematic lighting, ultra-sharp detail, preserve original product design and packaging, no distracting watermarks, no unwanted text.${videoPart}`;

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
		const response = await authFetch('/api/credits/estimate', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ type, video_duration_seconds: type === 'video' ? Number(duration.value) : null }) });
		const result = await response.json();
		if (!response.ok) throw new Error(result.error?.message || 'برآورد اعتبار انجام نشد.');
		estimate.textContent = result.data.sufficient ? `هزینه: ${result.data.cost} Credit | موجودی: ${result.data.balance} Credit` : `اعتبار کافی نیست (${result.data.balance} از ${result.data.cost} Credit) | خرید اعتبار`;
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
			video_duration_seconds: isVideo && values.video_duration_seconds ? Number(values.video_duration_seconds) : null
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
	const statusLabels = { queued: 'در صف پردازش...', processing: 'در حال ساخت...', completed: 'خروجی آماده است.', failed: 'ساخت محتوا ناموفق بود.', cancelled: 'تولید لغو شد.' };
	let outputUrl;
	let pollTimeoutId = null;
	let isFinished = false;

	const loadOutput = async () => {
		const response = await authFetch(`/api/generations/${generationId}/download`, { headers: { Accept: 'application/octet-stream' } });
		if (!response.ok) throw new Error('دریافت خروجی ممکن نیست.');
		if (outputUrl) URL.revokeObjectURL(outputUrl);
		outputUrl = URL.createObjectURL(await response.blob());
		return outputUrl;
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
			const output = await loadOutput();
			frame.innerHTML = media?.mime?.startsWith('video') ? `<video controls src="${output}"></video>` : `<img alt="خروجی تولیدشده" src="${output}">`;
			actions.hidden = false;
			document.querySelector('[data-download]').href = output;
			document.querySelector('[data-download]').download = `generation-${generationId}.${media?.mime === 'video/mp4' ? 'mp4' : 'png'}`;
			retryButton.hidden = true;
			regenerateButton.hidden = false;
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

