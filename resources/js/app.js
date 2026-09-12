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
	nameField.hidden = mode !== 'register';
	confirmField.hidden = mode !== 'register';
	document.querySelector('#auth-title').textContent = mode === 'register' ? 'حساب بساز' : 'خوش آمدی';
	submit.innerHTML = mode === 'register' ? 'ساخت حساب رایگان <span>←</span>' : 'ورود به Hale <span>←</span>';
	document.querySelectorAll('[data-auth-tab]').forEach((tab) => tab.classList.toggle('active', tab.dataset.authTab === mode));
	message.textContent = '';
};

document.querySelectorAll('[data-open-auth]').forEach((button) => button.addEventListener('click', () => setAuthMode(button.dataset.openAuth)));
document.querySelectorAll('[data-close-auth]').forEach((button) => button.addEventListener('click', () => modal.setAttribute('hidden', '')));
document.querySelectorAll('[data-auth-tab]').forEach((button) => button.addEventListener('click', () => setAuthMode(button.dataset.authTab)));

form?.addEventListener('submit', async (event) => {
	event.preventDefault();
	const values = Object.fromEntries(new FormData(form));
	const payload = { password: values.password, device_name: 'web' };
	if (authMode === 'register') {
		payload.name = values.name;
		payload.password_confirmation = values.password_confirmation;
		if (values.identifier.includes('@')) payload.email = values.identifier;
		else payload.phone = values.identifier;
	} else payload.identifier = values.identifier;

	submit.disabled = true;
	message.textContent = 'در حال اتصال...';
	try {
		const response = await fetch(`/api/auth/${authMode}`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(payload) });
		const result = await response.json();
		if (!response.ok) throw new Error(result.error?.message || 'اطلاعات واردشده صحیح نیست.');
		localStorage.setItem('hale_token', result.data.token);
		message.className = 'form-message success-message';
		message.textContent = authMode === 'register' ? 'حساب ساخته شد. در حال ورود...' : 'ورود موفق بود.';
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
let editingProductId = null;

const loadProducts = async () => {
	if (!productGrid || !token) return;
	const query = new URLSearchParams({ per_page: '12' });
	if (productSearch?.value.trim()) query.set('search', productSearch.value.trim());
	const response = await fetch(`/api/products?${query}`, { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
	if (!response.ok) return;
	const result = await response.json();
	const products = result.data?.data || [];
	productGrid.innerHTML = products.length ? products.map((product) => { const primary = product.assets?.[0]; return `<article class="product-tile"><div class="product-tile-art">${primary ? `<img data-product-asset="${primary.id}" alt="${product.name}">` : '<b>H</b>'}</div><strong>${product.name}</strong><small>${product.description || 'آماده برای ساخت محتوا'}</small><div class="product-tile-actions"><button class="small-button" data-edit-product="${product.id}">ویرایش</button><button class="small-button" data-delete-product="${product.id}">حذف</button></div></article>`; }).join('') : '<p class="empty-state">محصولی با این مشخصات پیدا نشد.</p>';
	await Promise.all(products.filter((product) => product.assets?.[0]).map(async (product) => { const asset = product.assets[0]; const response = await fetch(`/api/products/${product.id}/assets/${asset.id}/download`, { headers: { Accept: 'image/*', Authorization: `Bearer ${token}` } }); if (!response.ok) return; const image = document.querySelector(`[data-product-asset="${asset.id}"]`); if (image) image.src = URL.createObjectURL(await response.blob()); }));
};

document.querySelectorAll('[data-open-product]').forEach((button) => button.addEventListener('click', () => { editingProductId = null; productForm?.reset(); productFormTitle.textContent = 'محصول جدید'; productSubmit.innerHTML = 'افزودن محصول <span>←</span>'; productImage.required = true; productModal?.removeAttribute('hidden'); }));
document.querySelectorAll('[data-close-product]').forEach((button) => button.addEventListener('click', () => productModal?.setAttribute('hidden', '')));
productSearch?.addEventListener('input', () => loadProducts());
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
	formatBox.addEventListener('change', (event) => { durationField.hidden = !['instagram_reel', 'tiktok'].includes(event.target.value); updateEstimate().catch(() => {}); });
	duration.addEventListener('change', () => updateEstimate().catch(() => {}));
	builderForm.addEventListener('submit', async (event) => { event.preventDefault(); const values = Object.fromEntries(new FormData(builderForm)); const button = document.querySelector('[data-generate]'); button.disabled = true; message.textContent = 'در حال آماده‌سازی...'; const payload = { ...values, video_duration_seconds: values.video_duration_seconds ? Number(values.video_duration_seconds) : null }; try { const response = await fetch('/api/generations', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}` }, body: JSON.stringify(payload) }); const result = await response.json(); if (!response.ok) { if (response.status === 402) { message.innerHTML = `${result.error?.message || 'اعتبار کافی نیست.'} <a href="/pricing">مشاهده پلن‌ها</a>`; return; } throw new Error(result.error?.message || 'ساخت محتوا انجام نشد.'); } window.location.href = `/generations/${result.data.id}`; } catch (error) { message.className = 'form-message error-message'; message.textContent = error.message; } finally { button.disabled = false; } });
}

const generationPage = document.querySelector('[data-generation-id]');
if (generationPage && token) {
	const generationId = generationPage.dataset.generationId;
	const title = document.querySelector('[data-generation-title]');
	const copy = document.querySelector('[data-generation-copy]');
	const status = document.querySelector('[data-generation-status]');
	const progress = document.querySelector('[data-generation-progress]');
	const frame = document.querySelector('[data-result-frame]');
	const actions = document.querySelector('[data-result-actions]');
	const message = document.querySelector('[data-generation-message]');
	const retryButton = document.querySelector('[data-retry]');
	const regenerateButton = document.querySelector('[data-regenerate]');
	const statusLabels = { queued: 'در صف پردازش...', processing: 'در حال ساخت...', completed: 'خروجی آماده است.', failed: 'ساخت محتوا ناموفق بود.' };
	let outputUrl;
	const loadOutput = async () => {
		const response = await fetch(`/api/generations/${generationId}/download`, { headers: { Accept: 'application/octet-stream', Authorization: `Bearer ${token}` } });
		if (!response.ok) throw new Error('دریافت خروجی ممکن نیست.');
		if (outputUrl) URL.revokeObjectURL(outputUrl);
		outputUrl = URL.createObjectURL(await response.blob());
		return outputUrl;
	};
	const poll = async () => {
		const response = await fetch(`/api/generations/${generationId}`, { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
		const result = await response.json();
		if (!response.ok) throw new Error(result.error?.message || 'دریافت وضعیت ممکن نیست.');
		const generation = result.data;
		status.textContent = statusLabels[generation.status] || generation.status;
		progress.style.width = generation.status === 'completed' ? '100%' : generation.status === 'processing' ? '65%' : generation.status === 'failed' ? '0%' : '25%';
		if (generation.status === 'completed') {
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
		if (generation.status === 'failed') { title.innerHTML = 'ساخت محتوا<br><em>متوقف شد.</em>'; message.textContent = generation.error_message || 'دوباره تلاش کن.'; actions.hidden = false; retryButton.hidden = false; regenerateButton.hidden = true; return; }
		if (document.visibilityState === 'visible') setTimeout(poll, 2500);
	};
	poll().catch((error) => { message.textContent = error.message; });
	document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && !actions.hidden) return; if (document.visibilityState === 'visible') poll().catch((error) => { message.textContent = error.message; }); });
	document.querySelector('[data-feedback="positive"]')?.addEventListener('click', () => sendFeedback('positive'));
	document.querySelector('[data-feedback="negative"]')?.addEventListener('click', () => sendFeedback('negative'));
	async function sendFeedback(feedback) { await fetch(`/api/generations/${generationId}/feedback`, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}` }, body: JSON.stringify({ feedback }) }); message.textContent = 'بازخوردت ثبت شد، ممنون.'; }
	document.querySelector('[data-regenerate]')?.addEventListener('click', async () => { const response = await fetch(`/api/generations/${generationId}/regenerate`, { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } }); const result = await response.json(); if (response.ok) window.location.href = `/generations/${result.data.id}`; else message.textContent = result.error?.message || 'تولید مجدد انجام نشد.'; });
	retryButton?.addEventListener('click', async () => { retryButton.disabled = true; const response = await fetch(`/api/generations/${generationId}/retry`, { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } }); if (response.ok) window.location.reload(); else { const result = await response.json(); message.textContent = result.error?.message || 'تلاش مجدد انجام نشد.'; retryButton.disabled = false; } });
}
