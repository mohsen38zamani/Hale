import './bootstrap';

const modal = document.querySelector('[data-auth-modal]');
const form = document.querySelector('[data-auth-form]');
const message = document.querySelector('[data-form-message]');
const nameField = document.querySelector('[data-name-field]');
const confirmField = document.querySelector('[data-confirm-field]');
const submit = document.querySelector('[data-submit-auth]');
let authMode = 'login';

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

document.querySelectorAll('[data-open-auth]').forEach((button) => button.addEventListener('click', () => setAuthMode(button.dataset.openAuth)));
document.querySelectorAll('[data-close-auth]').forEach((button) => button.addEventListener('click', () => modal?.setAttribute('hidden', '')));
document.querySelectorAll('[data-auth-tab]').forEach((button) => button.addEventListener('click', () => setAuthMode(button.dataset.authTab)));

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
		const response = await fetch(`/api/auth/${authMode}`, {
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
		localStorage.setItem('hale_token', result.data.token);
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

const token = localStorage.getItem('hale_token');
const credit = document.querySelector('[data-credit]');
const welcome = document.querySelector('[data-welcome]');

if (credit || welcome) {
	if (!token) window.location.href = '/';
	fetch('/api/user/profile', { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } })
		.then(async (response) => {
			if (!response.ok) throw new Error('unauthenticated');
			return response.json();
		})
		.then((result) => {
			welcome.textContent = `${result.data.name}، آماده‌ای یک خروجی تازه بسازی؟`;
			if (credit) credit.textContent = result.data.credits_balance ?? '۰';
		})
		.catch(() => {
			localStorage.removeItem('hale_token');
			window.location.href = '/';
		});
}

document.querySelector('[data-logout]')?.addEventListener('click', async () => {
	await fetch('/api/auth/logout', { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
	localStorage.removeItem('hale_token');
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

const loadProducts = async () => {
	if (!productGrid || !token) return;
	const query = new URLSearchParams({ per_page: '12', page: String(productPageNumber) });
	if (productSearch?.value.trim()) query.set('search', productSearch.value.trim());
	const response = await fetch(`/api/products?${query}`, { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
	if (!response.ok) return;
	const result = await response.json();
	const products = result.data?.data || [];
	const pagination = result.data || {};
	productPagination.hidden = (pagination.last_page || 1) <= 1;
	productPage.textContent = `${pagination.current_page || 1} / ${pagination.last_page || 1}`;
	productPrev.disabled = (pagination.current_page || 1) <= 1;
	productNext.disabled = (pagination.current_page || 1) >= (pagination.last_page || 1);
	productGrid.innerHTML = products.length ? products.map((product) => { const primary = product.assets?.[0]; return `<article class="product-tile"><div class="product-tile-art">${primary ? `<img data-product-asset="${primary.id}" alt="${product.name}">` : '<b>H</b>'}</div><strong>${product.name}</strong><small>${product.description || 'آماده برای ساخت محتوا'}</small><div class="product-tile-actions"><button class="small-button" data-edit-product="${product.id}">ویرایش</button><button class="small-button" data-delete-product="${product.id}">حذف</button></div></article>`; }).join('') : '<p class="empty-state">محصولی با این مشخصات پیدا نشد.</p>';
	await Promise.all(products.filter((product) => product.assets?.[0]).map(async (product) => { const asset = product.assets[0]; const response = await fetch(`/api/products/${product.id}/assets/${asset.id}/download`, { headers: { Accept: 'image/*', Authorization: `Bearer ${token}` } }); if (!response.ok) return; const image = document.querySelector(`[data-product-asset="${asset.id}"]`); if (image) image.src = URL.createObjectURL(await response.blob()); }));
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
		const product = (await fetch(`/api/products/${editButton.dataset.editProduct}`, { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } })).json();
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
		const response = await fetch(`/api/products/${deleteButton.dataset.deleteProduct}`, { method: 'DELETE', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
		if (response.ok) await loadProducts(); else productMessage.textContent = 'حذف محصول انجام نشد.';
	}
});
productForm?.addEventListener('submit', async (event) => {
	event.preventDefault();
	const values = new FormData(productForm);
	const button = productForm.querySelector('button[type="submit"]');
	button.disabled = true;
	productMessage.textContent = editingProductId ? 'در حال ذخیره...' : 'در حال آپلود...';
	try {
		const productResponse = await fetch(editingProductId ? `/api/products/${editingProductId}` : '/api/products', { method: editingProductId ? 'PUT' : 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}` }, body: JSON.stringify({ name: values.get('name'), description: values.get('description') }) });
		const productResult = await productResponse.json();
		if (!productResponse.ok) throw new Error(productResult.error?.message || 'ذخیره محصول انجام نشد.');
		const upload = new FormData();
		if (values.get('image')?.size) {
			upload.append('image', values.get('image'));
			const assetResponse = await fetch(`/api/products/${productResult.data.id}/assets`, { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` }, body: upload });
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
loadProducts();

const generationList = document.querySelector('[data-generation-list]');
if (generationList && token) {
	fetch('/api/generations?per_page=6', { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } })
		.then((response) => response.json())
		.then((result) => {
			const generations = result.data?.data || [];
			generationList.innerHTML = generations.length ? generations.map((generation) => `<a class="generation-row" href="/dashboard"><span class="generation-icon ${generation.status}">${generation.type === 'video' ? '▶' : '✦'}</span><strong>${generation.creative_project?.product?.name || 'محصول'}</strong><span>${generation.status === 'completed' ? 'آماده' : generation.status === 'failed' ? 'ناموفق' : 'در حال ساخت'}</span><small>${generation.created_at ? new Date(generation.created_at).toLocaleDateString('fa-IR') : ''}</small></a>`).join('') : '<p class="empty-state">هنوز محتوایی نساخته‌ای.</p>';
		})
		.catch(() => { generationList.innerHTML = '<p class="empty-state">تاریخچه فعلاً در دسترس نیست.</p>'; });
}

const notificationList = document.querySelector('[data-notification-list]');
if (notificationList && token) {
	const loadNotifications = async () => {
		const response = await fetch('/api/notifications?per_page=8', { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
		if (!response.ok) throw new Error('دریافت اعلان‌ها انجام نشد.');
		const result = await response.json();
		const notifications = result.data?.items || [];
		notificationList.innerHTML = notifications.length ? notifications.map((notification) => `<button class="notification-item ${notification.read_at ? '' : 'unread'}" data-notification-id="${notification.id}"><strong>${notification.data?.message || 'اعلان جدید'}</strong><small>${notification.created_at ? new Date(notification.created_at).toLocaleDateString('fa-IR') : ''}</small></button>`).join('') : '<p class="empty-state">اعلان جدیدی نداری.</p>';
	};
	loadNotifications().catch(() => { notificationList.innerHTML = '<p class="empty-state">اعلان‌ها فعلاً در دسترس نیستند.</p>'; });
	notificationList.addEventListener('click', async (event) => { const item = event.target.closest('[data-notification-id]'); if (!item || !item.classList.contains('unread')) return; const response = await fetch(`/api/notifications/${item.dataset.notificationId}/read`, { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } }); if (response.ok) item.classList.remove('unread'); });
	document.querySelector('[data-read-all-notifications]')?.addEventListener('click', async () => { const response = await fetch('/api/notifications/read-all', { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } }); if (response.ok) notificationList.querySelectorAll('.unread').forEach((item) => item.classList.remove('unread')); });
}

const builderForm = document.querySelector('[data-builder-form]');
if (builderForm) {
	const labels = { introduction: 'معرفی محصول', sales: 'افزایش فروش', branding: 'برندینگ', promotion: 'تخفیف', launch: 'محصول جدید', engagement: 'جذب مخاطب', luxury: 'لوکس', minimal: 'مینیمال', cinematic: 'سینمایی', natural: 'طبیعی', colorful: 'رنگارنگ', dark: 'تیره', professional: 'حرفه‌ای', fashion: 'فشن', instagram_post: 'پست ۱:۱', instagram_story: 'استوری', instagram_reel: 'Reel', tiktok: 'TikTok' };
	const select = document.querySelector('[data-product-select]');
	const message = document.querySelector('[data-builder-message]');
	const formatBox = document.querySelector('[data-formats]');
	const durationField = document.querySelector('[data-duration-field]');
	const duration = document.querySelector('[data-duration]');
	const estimate = document.querySelector('[data-credit-estimate]');
	const updateEstimate = async () => {
		const format = formatBox.querySelector('input[name="format"]:checked')?.value;
		if (!format) return;
		const type = ['instagram_reel', 'tiktok'].includes(format) ? 'video' : 'image';
		const response = await fetch('/api/credits/estimate', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}` }, body: JSON.stringify({ type, video_duration_seconds: type === 'video' ? Number(duration.value) : null }) });
		const result = await response.json();
		if (!response.ok) throw new Error(result.error?.message || 'برآورد اعتبار انجام نشد.');
		estimate.textContent = result.data.sufficient ? `هزینه: ${result.data.cost} Credit | موجودی: ${result.data.balance} Credit` : `اعتبار کافی نیست (${result.data.balance} از ${result.data.cost} Credit) | خرید اعتبار`;
		estimate.dataset.insufficient = result.data.sufficient ? 'false' : 'true';
	};
	const renderChoices = (target, values, name, withType = false) => { target.innerHTML = values.map((item) => { const key = withType ? item.key : item; return `<label class="choice"><input type="radio" name="${name}" value="${key}" required><span>${labels[key] || key}${withType ? `<small>${item.aspect_ratio}</small>` : ''}</span></label>`; }).join(''); };
	Promise.all([
		fetch('/api/products?per_page=50', { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } }).then((response) => response.json()),
		fetch('/api/creative/options', { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } }).then((response) => response.json()),
	]).then(([products, options]) => {
		(products.data?.data || []).forEach((product) => { select.insertAdjacentHTML('beforeend', `<option value="${product.id}">${product.name}</option>`); });
		renderChoices(document.querySelector('[data-goals]'), options.data.goals, 'goal');
		renderChoices(document.querySelector('[data-styles]'), options.data.styles, 'style');
		renderChoices(formatBox, options.data.formats, 'format', true);
		document.querySelector('[data-environment]').innerHTML = options.data.environments.map((item) => `<option value="${item}">${labels[item] || item}</option>`).join('');
		duration.innerHTML = options.data.video_durations.map((item) => `<option value="${item}">${item} ثانیه</option>`).join('');
	}).catch(() => { message.textContent = 'دریافت گزینه‌ها انجام نشد. دوباره تلاش کن.'; });
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
				const response = await fetch('/api/creative/preview', {
					method: 'POST',
					headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
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
			} catch (err) {
				message.className = 'form-message error-message';
				message.textContent = err.message;
			} finally {
				autoBestBtn.disabled = false;
				autoBestBtn.textContent = '✨ خودت بهترینش رو بساز';
			}
		});
	}
	formatBox.addEventListener('change', (event) => { durationField.hidden = !['instagram_reel', 'tiktok'].includes(event.target.value); updateEstimate().catch(() => {}); });
	duration.addEventListener('change', () => updateEstimate().catch(() => {}));
	builderForm.addEventListener('submit', async (event) => {
		event.preventDefault();
		const values = Object.fromEntries(new FormData(builderForm));
		const button = document.querySelector('[data-generate]');
		button.disabled = true;
		message.textContent = 'در حال آماده‌سازی...';
		const isVideo = ['instagram_reel', 'tiktok'].includes(values.format);
		const payload = {
			...values,
			video_duration_seconds: isVideo && values.video_duration_seconds ? Number(values.video_duration_seconds) : null
		};
		try {
			const response = await fetch('/api/generations', {
				method: 'POST',
				headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
				body: JSON.stringify(payload)
			});
			const result = await response.json();
			if (!response.ok) {
				if (response.status === 402) {
					message.innerHTML = `${result.error?.message || 'اعتبار کافی نیست.'} <a href="/pricing">مشاهده پلن‌ها</a>`;
					return;
				}
				throw new Error(result.error?.message || 'ساخت محتوا انجام نشد.');
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

// ---- Landing hero 3D tilt (pointer + device orientation, desktop only) ----
if (document.body.classList.contains('landing-page') && matchMedia('(hover: hover)').matches) {
	const card = document.querySelector('[data-tilt]');
	if (card) {
		let raf = 0;
		const apply = (rx, ry) => { card.style.transform = `rotateX(${rx}deg) rotateY(${ry}deg)`; };
		const move = (px, py) => {
			cancelAnimationFrame(raf);
			raf = requestAnimationFrame(() => {
				const ry = (px - 0.5) * 24;
				const rx = (0.5 - py) * 16;
				apply(rx, ry);
				card.style.setProperty('--glow-x', `${px * 100}%`);
				card.style.setProperty('--glow-y', `${py * 100}%`);
			});
		};
		const onPointer = (event) => {
			const rect = card.getBoundingClientRect();
			move((event.clientX - rect.left) / rect.width, (event.clientY - rect.top) / rect.height);
		};
		window.addEventListener('pointermove', (event) => {
			if (event.pointerType === 'touch') return;
			onPointer(event);
		});
		window.addEventListener('deviceorientation', (event) => {
			if (event.beta === null || event.gamma === null) return;
			move(Math.min(1, Math.max(0, 0.5 + event.gamma / 60)), Math.min(1, Math.max(0, 0.5 + (event.beta - 45) / 60)));
		});
		window.addEventListener('pointerleave', () => apply(0, 0));
	}
}

const generationPage = document.querySelector('[data-generation-id]');
if (generationPage && !token) {
	window.location.href = '/';
} else if (generationPage && token) {
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
	const statusLabels = { queued: 'در صف پردازش...', processing: 'در حال ساخت...', completed: 'خروجی آماده است.', failed: 'ساخت محتوا ناموفق بود.' };
	let outputUrl;
	let pollTimeoutId = null;
	let isFinished = false;

	const loadOutput = async () => {
		const response = await fetch(`/api/generations/${generationId}/download`, { headers: { Accept: 'application/octet-stream', Authorization: `Bearer ${token}` } });
		if (!response.ok) throw new Error('دریافت خروجی ممکن نیست.');
		if (outputUrl) URL.revokeObjectURL(outputUrl);
		outputUrl = URL.createObjectURL(await response.blob());
		return outputUrl;
	};

	const scheduleNextPoll = () => {
		if (pollTimeoutId) clearTimeout(pollTimeoutId);
		if (!isFinished && document.visibilityState === 'visible') {
			pollTimeoutId = setTimeout(poll, 2500);
		}
	};

	const poll = async () => {
		if (isFinished) return;
		const response = await fetch(`/api/generations/${generationId}`, { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
		const result = await response.json();
		if (!response.ok) throw new Error(result.error?.message || 'دریافت وضعیت ممکن نیست.');
		const generation = result.data;
		creditInfo.textContent = generation.status === 'completed'
			? `اعتبار مصرف‌شده: ${generation.credits_charged} Credit`
			: generation.credits_reserved > 0
				? `اعتبار رزروشده: ${generation.credits_reserved} Credit`
				: 'اعتبار هنوز رزرو نشده است';
		status.textContent = statusLabels[generation.status] || generation.status;
		progress.style.width = generation.status === 'completed' ? '100%' : generation.status === 'processing' ? '65%' : generation.status === 'failed' ? '0%' : '25%';
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
		if (generation.status === 'failed') {
			isFinished = true;
			if (pollTimeoutId) clearTimeout(pollTimeoutId);
			title.innerHTML = 'ساخت محتوا<br><em>متوقف شد.</em>';
			message.textContent = generation.error_message || 'دوباره تلاش کن.';
			actions.hidden = false;
			retryButton.hidden = false;
			regenerateButton.hidden = true;
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
	async function sendFeedback(feedback) { await fetch(`/api/generations/${generationId}/feedback`, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}` }, body: JSON.stringify({ feedback }) }); message.textContent = 'بازخوردت ثبت شد، ممنون.'; }
	document.querySelector('[data-regenerate]')?.addEventListener('click', async () => {
		const response = await fetch(`/api/generations/${generationId}/regenerate`, { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
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
		const response = await fetch(`/api/generations/${generationId}/retry`, { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
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
