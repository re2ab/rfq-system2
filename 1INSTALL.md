# راهنمای نصب و وضعیت فعلی سیستم

## وضعیت فعلی پکیج (نسخه COMPLETE-v4.1)

این پکیج یک **اسکلت پیشرفته و قابل توسعه** است که بخش‌های اصلی منطق کسب‌وکار را پوشش می‌دهد:

### پیاده‌سازی شده:
- Migration کامل تمام جداول اصلی
- Seeder نقش‌ها، مجوزها، ادمین، ماژول‌ها
- NumberGeneratorService (شماره‌گذاری امن)
- VatCalculatorService (محاسبه VAT بر اساس Incoterm)
- CaseStatusService (State Machine + Guardها)
- Models: Case, Contact, Organization, Task, CaseActivity
- Controllers: Case, Kanban, Task, Contact, Settings, Dashboard
- Views: Layout RTL، داشبورد، فهرست پرونده، کانبان، فهرست وظایف
- کنترل دسترسی بر اساس نقش در Routes و Controllers

### هنوز نیاز به تکمیل دارد (برای تست کامل همه امکانات):
- احراز هویت کامل (Breeze یا Fortify + 2FA)
- صفحات ایجاد/ویرایش پرونده، جزئیات پرونده با Timeline کامل
- Drag & Drop واقعی کانبان (نیاز به JS)
- کارت مخاطب Modal کامل
- موتور قالب اسناد با Placeholder
- سیستم ایمیل (IMAP + SMTP)
- گزارش‌های نموداری
- آپلود فایل
- دو زبانه کامل
- و سایر جزئیات UI/UX

## نصب

```bash
composer install
cp .env.example .env
php artisan key:generate
# تنظیم دیتابیس
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

کاربر پیش‌فرض: admin@example.com / password

برای ادامه توسعه، اولویت بدهید کدام ماژول را کامل‌تر می‌خواهید.
