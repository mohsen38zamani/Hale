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

const loadProducts = async () => {
	if (!productGrid || !token) return;
	const response = await fetch('/api/products?per_page=12', { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
	if (!response.ok) return;
	const result = await response.json();
	const products = result.data?.data || [];
	productGrid.innerHTML = products.length ? products.map((product) => `<article class="product-tile"><div class="product-tile-art">${product.assets?.length ? '<span>IMAGE</span>' : '<b>H</b>'}</div><strong>${product.name}</strong><small>${product.description || 'آماده برای ساخت محتوا'}</small></article>`).join('') : '<p class="empty-state">هنوز محصولی نداری. اولین محصولت را اضافه کن.</p>';
};

document.querySelectorAll('[data-open-product]').forEach((button) => button.addEventListener('click', () => productModal?.removeAttribute('hidden')));
document.querySelectorAll('[data-close-product]').forEach((button) => button.addEventListener('click', () => productModal?.setAttribute('hidden', '')));
productForm?.addEventListener('submit', async (event) => {
	event.preventDefault();
	const values = new FormData(productForm);
	const button = productForm.querySelector('button[type="submit"]');
	button.disabled = true;
	productMessage.textContent = 'در حال آپلود...';
	try {
		const productResponse = await fetch('/api/products', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}` }, body: JSON.stringify({ name: values.get('name'), description: values.get('description') }) });
		const productResult = await productResponse.json();
		if (!productResponse.ok) throw new Error(productResult.error?.message || 'ساخت محصول انجام نشد.');
		const upload = new FormData();
		upload.append('image', values.get('image'));
		const assetResponse = await fetch(`/api/products/${productResult.data.id}/assets`, { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` }, body: upload });
		if (!assetResponse.ok) throw new Error('آپلود تصویر انجام نشد.');
		productModal.setAttribute('hidden', '');
		productForm.reset();
		await loadProducts();
	} catch (error) {
		productMessage.className = 'form-message error-message';
		productMessage.textContent = error.message;
	} finally { button.disabled = false; }
});
loadProducts();

const builderForm = document.querySelector('[data-builder-form]');
if (builderForm) {
	const labels = { introduction: 'معرفی محصول', sales: 'افزایش فروش', branding: 'برندینگ', promotion: 'تخفیف', launch: 'محصول جدید', engagement: 'جذب مخاطب', luxury: 'لوکس', minimal: 'مینیمال', cinematic: 'سینمایی', natural: 'طبیعی', colorful: 'رنگارنگ', dark: 'تیره', professional: 'حرفه‌ای', fashion: 'فشن', instagram_post: 'پست ۱:۱', instagram_story: 'استوری', instagram_reel: 'Reel', tiktok: 'TikTok' };
	const select = document.querySelector('[data-product-select]');
	const message = document.querySelector('[data-builder-message]');
	const formatBox = document.querySelector('[data-formats]');
	const durationField = document.querySelector('[data-duration-field]');
	const duration = document.querySelector('[data-duration]');
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
	formatBox.addEventListener('change', (event) => { durationField.hidden = !['instagram_reel', 'tiktok'].includes(event.target.value); });
	builderForm.addEventListener('submit', async (event) => { event.preventDefault(); const values = Object.fromEntries(new FormData(builderForm)); const button = document.querySelector('[data-generate]'); button.disabled = true; message.textContent = 'در حال آماده‌سازی...'; const payload = { ...values, video_duration_seconds: values.video_duration_seconds ? Number(values.video_duration_seconds) : null }; try { const response = await fetch('/api/generations', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}` }, body: JSON.stringify(payload) }); const result = await response.json(); if (!response.ok) throw new Error(result.error?.message || 'ساخت محتوا انجام نشد.'); message.className = 'form-message success-message'; message.textContent = `درخواست ساخت ثبت شد. وضعیت: ${result.data.status}`; setTimeout(() => { window.location.href = '/dashboard'; }, 800); } catch (error) { message.className = 'form-message error-message'; message.textContent = error.message; } finally { button.disabled = false; } });
}
