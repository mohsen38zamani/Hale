<div dir="rtl">

# مستند عملیاتی و راهنمای استقرار (Production Deployment & Operations Runbook) — Hale

**نوع سند:** Deployment & Operations Runbook  
**نسخه:** 1.0  
**مخاطب:** تیم DevOps، مدیران سیستم (Sysadmins) و توسعه‌دهندگان ارشد  
**پروژه:** استودیو هوشمند تولید محتوای تبلیغاتی Hale  

---

## ۱. مرور کلی معماری و پیش‌نیازهای استقرار

پروژه **Hale** بر پایه معماری ماژولار لاراول (Laravel 12)، پایگاه‌داده MySQL 8.0، کش و صف ریدیس (Redis 7.0) و فضای ذخیره‌سازی شی‌ءگرا (S3 / MinIO / ArvanCloud Object Storage) طراحی شده است.

### نیازمندی‌های سرور (Production Server Requirements)
- **سیستم‌عامل:** Ubuntu 22.04 LTS یا Ubuntu 24.04 LTS
- **پردازنده و رم:** حداقل ۴ هسته CPU و ۸ گیگابایت RAM (برای پاسخگویی سریع به پردازش‌های همزمان صف)
- **محیط اجرایی:**
  - PHP 8.2+ به همراه افزونه‌های: `bcmath, ctype, curl, dom, fileinfo, gd, json, mbstring, openssl, pdo_mysql, redis, tokenizer, xml`
  - Nginx 1.22+
  - MySQL 8.0+
  - Redis 7.0+
  - Supervisor (برای مدیریت و مانیتورینگ پردازش‌های صف)

---

## ۲. تنظیمات متغیرهای محیطی (.env.production)

فایل `.env.production` را با رعایت اصول امنیتی در سرور اصلی پیکربندی کنید:

```ini
APP_NAME=Hale
APP_ENV=production
APP_KEY=base64:... # با اجرای php artisan key:generate ساخته می‌شود
APP_DEBUG=false
APP_URL=https://hale.ai

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

# اتصال به دیتابیس
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hale_production
DB_USERNAME=hale_db_user
DB_PASSWORD=SecureStrongPasswordHere!

# صف و سشن
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=YourRedisStrongAuthToken
REDIS_PORT=6379

# ذخیره‌سازی فایل‌ها و استوریج ابری
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=hale-media-production
AWS_ENDPOINT=https://s3.ir-thr-at1.arvanstorage.ir
AWS_USE_PATH_STYLE_ENDPOINT=true

# درگاه پرداخت زرین‌پال
ZARINPAL_MERCHANT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
ZARINPAL_SANDBOX=false
ZARINPAL_CALLBACK_URL=https://hale.ai/api/payments/zarinpal/callback

# سامانه پیامکی SMS.ir
SMS_IR_API_KEY=your_sms_ir_api_key_production
SMS_IR_LINE_NUMBER=3000xxxx
SMS_IR_VERIFY_TEMPLATE_ID=100000

# ره‌گیری خطاها (Sentry)
SENTRY_LARAVEL_DSN=https://examplePublicKey@o0.ingest.sentry.io/0
```

---

## ۳. مدیریت پردازش‌های پس‌زمینه با Supervisor

برای اجرای پایدار جاب‌های صف (تولید تصویر، ویدیو، ارسال ایمیل و وب‌هوک‌ها)، از سرویس **Supervisor** استفاده می‌شود.

فایل پیکربندی را در مسیر `/etc/supervisor/conf.d/hale-worker.conf` ایجاد کنید:

```ini
[program:hale-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/hale/artisan queue:work redis --queue=generations,notifications,default --sleep=3 --tries=3 --timeout=180 --memory=512 --max-jobs=1000
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/supervisor/hale-worker.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=10
stopwaitsecs=3600
```

پس از ایجاد فایل، سرویس را بارگذاری کنید:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start hale-worker:*
```

> [!NOTE]
> در کدهای برنامه، هوک `Queue::failing` به صورت خودکار هرگونه خطای پایدار در صف را با سطح `critical` در لاگ‌های سیستم ثبت می‌کند تا تیم مهندسی بلافاصله مطلع شود.

---

## ۴. برنامه پشتیبان‌گیری خودکار دیتابیس (توسط مدیر سیستم / DevOps)

مطابق سیاست‌های امنیتی و معماری سیستم، فرآیند تهیه نسخه پشتیبان (Backup) مستقیماً توسط مدیر سیستم و در سطح زیرساخت اجرا می‌شود و به لایه کدهای برنامه وابسته نیست.

### ۴.۱ اسکریپت شل پشتیبان‌گیری: `/usr/local/bin/hale-mysql-backup.sh`

این اسکریپت را با دسترسی روت ایجاد و دسترسی اجرایی دهید:

```bash
sudo tee /usr/local/bin/hale-mysql-backup.sh << 'EOF'
#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# HALE AI STUDIO — Production Database Backup Script
# Run daily via cron by sysadmin
# ==============================================================================

BACKUP_DIR="/var/backups/hale/mysql"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/hale_db_${TIMESTAMP}.sql.gz"
CHECKSUM_FILE="${BACKUP_FILE}.sha256"
RETENTION_DAYS=30

# تنظیم دسترسی خواندن فقط برای root
umask 077

mkdir -p "${BACKUP_DIR}"

echo "[$(date)] شروع تهیه پشتیبان از دیتابیس hale_production..."

# استفاده از فلگ‌های ایمن برای دیتابیس زنده (بدون قفل جدول)
mysqldump \
  --defaults-extra-file=/etc/mysql/backup-credentials.cnf \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --events \
  hale_production | gzip -9 > "${BACKUP_FILE}"

# محاسبه چکسام امنیتی SHA-256
sha256sum "${BACKUP_FILE}" > "${CHECKSUM_FILE}"

echo "[$(date)] پشتیبان با موفقیت ساخته شد: ${BACKUP_FILE}"

# پاک‌سازی فایل‌های پشتیبان قدیمی‌تر از ۳۰ روز
echo "[$(date)] بررسی و پاک‌سازی فایل‌های قدیمی‌تر از ${RETENTION_DAYS} روز..."
find "${BACKUP_DIR}" -type f -name "hale_db_*.sql.gz" -mtime +${RETENTION_DAYS} -delete
find "${BACKUP_DIR}" -type f -name "hale_db_*.sql.gz.sha256" -mtime +${RETENTION_DAYS} -delete

# همگام‌سازی فایل به فضای ذخیره‌سازی خارج از سرور (Offsite Backup)
if command -v aws &> /dev/null; then
    echo "[$(date)] آپلود فایل پشتیبان به فضای ابری سرد (Offsite Storage)..."
    aws s3 cp "${BACKUP_FILE}" s3://hale-cold-backups/mysql/ --endpoint-url https://s3.ir-thr-at1.arvanstorage.ir
fi

echo "[$(date)] فرآیند پشتیبان‌گیری با موفقیت به پایان رسید."
EOF

sudo chmod 700 /usr/local/bin/hale-mysql-backup.sh
```

### ۴.۲ فایل امن اعتبارسنجی دیتابیس: `/etc/mysql/backup-credentials.cnf`

برای امنیت حداکثری و عدم افشای رمز عبور در دستورات `ps`:

```ini
[client]
user = hale_backup_user
password = "StrongSecureBackupPassword"
host = 127.0.0.1
```
دسترسی فایل را محدود کنید:
```bash
sudo chmod 600 /etc/mysql/backup-credentials.cnf
```

### ۴.۳ زمان‌بندی Cron در سیستم‌عامل (`/etc/cron.d/hale-mysql-backup`)

اجرای منظم اسکریپت هر شب رأس ساعت ۰۳:۰۰ بامداد:

```cron
# /etc/cron.d/hale-mysql-backup
0 3 * * * root /usr/local/bin/hale-mysql-backup.sh >> /var/log/hale-backup.log 2>&1
```

---

## ۵. مانور بازیابی و بازگردانی دیتابیس (Disaster Recovery Drill)

در صورت بروز حادثه، برای بازگردانی کامل دیتابیس مراحل زیر به ترتیب اجرا می‌شوند:

1. **بررسی صحت فایل و چکسام:**
   ```bash
   cd /var/backups/hale/mysql
   sha256sum -c hale_db_YYYYMMDD_HHMMSS.sql.gz.sha256
   ```

2. **بردن برنامه به حالت تعمیرات (Maintenance Mode):**
   ```bash
   cd /var/www/hale
   php artisan down --secret="hale-admin-emergency-bypass" --render="errors::503"
   ```

3. **توقف ورکرها جهت جلوگیری از ثبت ناقص تراکنش‌ها:**
   ```bash
   sudo supervisorctl stop hale-worker:*
   ```

4. **ایمپورت فایل پشتیبان:**
   ```bash
   gunzip < /var/backups/hale/mysql/hale_db_YYYYMMDD_HHMMSS.sql.gz | mysql --defaults-extra-file=/etc/mysql/backup-credentials.cnf hale_production
   ```

5. **اجرای مایگریشن‌های احتمالی جدیدتر:**
   ```bash
   php artisan migrate --force
   ```

6. **پاک‌سازی و بازسازی کش سیستم:**
   ```bash
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

7. **راه‌اندازی مجدد ورکرها و خارج کردن برنامه از حالت تعمیرات:**
   ```bash
   sudo supervisorctl start hale-worker:*
   php artisan up
   ```

---

## ۶. مانیتورینگ و بررسی سلامت سرویس (Monitoring & Health Checks)

- **اندپوینت سلامت‌سنجی (Health Check):**
  - آدرس: `https://hale.ai/up`
  - این اندپوینت وضعیت پایگاه‌داده و در دسترس بودن سرور را بازمی‌گرداند و برای ابزارهای مانیتورینگ آپ‌تایم (مانند UptimeRobot، Grafana، Datadog یا هارت‌بیت Cloudflare) تنظیم شده است.
- **بررسی عملکرد کوئری‌ها و پیشگیری از N+1:**
  - کلیه اندپوینت‌های پرتردد (`/api/products`، `/api/generations`، `/api/notifications`) دارای تست‌های ارزیابی عملکرد و Eager-loading برای تضمین کمینه بودن کوئری‌ها هستند.
- **لاگ خطاهای سیستمی:**
  - لاگ‌های اپلیکیشن در مسیر `/var/www/hale/storage/logs/laravel.log`
  - خطاهای صف در لاگ سوپروایزر `/var/log/supervisor/hale-worker.log`
  - تمامی استثناهای رخ‌داده در محیط Production مستقیماً به داشبورد Sentry ارسال می‌شوند.

---

## ۷. فرآیند استقرار پیوسته بدون وقفه (Zero-Downtime Deployment Steps)

هنگام انتشار نسخه جدید در سرور عملیاتی، دستورات زیر اجرا می‌شوند:

```bash
cd /var/www/hale

# ۱. دریافت آخرین تغییرات شاخه اصلی
git fetch origin main
git checkout main
git pull origin main

# ۲. نصب نیازمندی‌ها بدون پکیج‌های توسعه
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# ۳. اجرای مایگریشن‌ها
php artisan migrate --force

# ۴. نوسازی کش‌های بهینه‌سازی
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# ۵. ری‌استارت امن ورکرها پس از تکمیل پردازش‌های جاری
php artisan queue:restart

# ۶. بازخوانی کش OPcache در صورت فعال بودن
# php-fpm reload
sudo systemctl reload php8.2-fpm
```

</div>
