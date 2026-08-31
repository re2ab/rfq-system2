# فاز B + C — Inbox و Compose

نسخه: 19.10.0

## فاز B
- Inbox سه‌ستونه (فولدر | لیست | خواندن) شبیه Gmail
- Thread بر اساس `thread_key`
- جستجو روی موضوع / فرستنده / متن
- فیلتر نخوانده و ستاره‌دار
- ستاره، آرشیو (فقط داخل RFQ — بدون حذف از میل‌سرور)
- همگام‌سازی دستی از UI
- مسیر: `/mail/inbox`

## فاز C
- Compose با ویرایشگر HTML ساده (ضخیم / ایتالیک / فهرست / لینک)
- CC / BCC / Reply-To
- پیش‌نویس (`mail_drafts`)
- امضای per-user فارسی و انگلیسی (`/mail/signature`)
- پیوست آپلود + اسناد پرونده
- Reply / Forward با In-Reply-To و References
- ارسال SMTP + ثبت محلی در فولدر Sent
- مسیر: `/mail/compose`

## نصب
```bash
php artisan migrate --force
php artisan route:clear && php artisan view:clear
php artisan mail:sync
```

منوی سایدبار «صندوق ایمیل» به `/mail/inbox` اشاره می‌کند.
صندوق قدیمی `/mailbox` همچنان موجود است.

## پیش‌نیاز
- فاز A نصب شده باشد (جداول mail_accounts و …)
- اکانت تعریف و به کاربر تخصیص داده شده باشد
- `ext-imap` برای sync

## بعدی (فاز D)
- لینک کامل به پرونده از Inbox
- تایم‌لاین ایمیل روی صفحه پرونده
- پیشنهاد تطبیق Contact/Organization
