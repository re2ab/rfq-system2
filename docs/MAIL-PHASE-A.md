# فاز A — کلاینت ایمیل یکپارچه (زیرساخت)

نسخه: 19.9.0  
تاریخ: 2026-08-31

## هدف
پایه‌ی داده و همگام‌سازی برای کلاینت ایمیل شبیه Gmail داخل RFQ، بدون شکستن `/mailbox` فعلی.

## چه چیزی اضافه شد

### جداول جدید
| جدول | نقش |
|------|-----|
| `mail_accounts` | اکانت‌های SMTP/IMAP (چندتایی، مشترک یا شخصی) |
| `mail_account_user` | تخصیص دسترسی کاربر توسط ادمین |
| `mail_folders` | فولدرهای کشف‌شده از IMAP |
| `mail_messages` | پیام‌های همگام‌شده محلی + ستون‌های case/contact برای فازهای بعد |
| `mail_message_attachments` | متادیتای پیوست (دانلود فایل در فازهای بعد) |

### سرویس‌ها
- `App\Services\Mail\MailAccountService` — CRUD اکانت + دسترسی
- `App\Services\Mail\MailSyncService` — کشف فولدر + sync پیام (ext-imap)

### دستور
```bash
php artisan migrate
php artisan mail:sync
php artisan mail:sync --account=1
```

زمان‌بندی: هر ۱۰ دقیقه در `routes/console.php`

### UI ادمین
- مسیر: `/mail/accounts` (نیاز به `settings.manage`)
- کارت در تنظیمات: «اکانت‌های ایمیل یکپارچه»
- تست IMAP و Sync دستی از همان صفحه

### ماژول
کلید `unified_mail` در جدول `modules` (پیش‌فرض فعال)

## مهاجرت داده
اگر `user_mail_accounts` پر باشد، هنگام migrate به `mail_accounts` + pivot کپی می‌شود (بدون حذف دادهٔ قدیم).

## خارج از این فاز
- Inbox سه‌ستونه (فاز B)
- Compose / Draft / امضا (فاز C)
- لینک پرونده و تایم‌لاین (فاز D)

## نصب
1. بک‌آپ پروژه و دیتابیس
2. اعمال فایل‌های این پچ
3. `composer dump-autoload` (در صورت نیاز)
4. `php artisan migrate --force`
5. `php artisan route:clear && php artisan view:clear && php artisan config:clear`
6. از تنظیمات → اکانت ایمیل یکپارچه، اکانت بسازید و Sync بزنید

## نکات
- پیام از میل‌سرور حذف نمی‌شود.
- Host خالی روی اکانت = استفاده از تنظیمات شرکت در امنیت/ایمیل.
- نیاز به `ext-imap` روی PHP.
