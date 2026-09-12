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
