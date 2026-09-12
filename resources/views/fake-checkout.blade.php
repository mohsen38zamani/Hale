<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>پرداخت آزمایشی | Hale</title>
    @vite(['resources/css/app.css'])
</head>
<body class="dashboard-page">
    <main class="dashboard-main">
        <section class="auth-panel" style="max-width: 34rem; margin: 5rem auto;">
            <p class="eyebrow">FAKE PAYMENT / LOCAL ONLY</p>
            <h1>پرداخت آزمایشی</h1>
            <p class="modal-copy">این صفحه فقط برای محیط local و تست مسیر callback است.</p>
            <form method="post" action="{{ url('/fake-checkout/'.$authority) }}" style="display:flex; gap:1rem; flex-wrap:wrap;">
                @csrf
                <button class="primary-button" name="status" value="paid" type="submit">تأیید پرداخت</button>
                <button class="small-button" name="status" value="failed" type="submit">شکست پرداخت</button>
            </form>
        </section>
    </main>
</body>
</html>
