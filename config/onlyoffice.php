<?php

/**
 * M11 — درگاه ویرایش آنلاین (ONLYOFFICE Document Server). این یک سرویس جدا
 * است که باید خودتان (روی Railway به‌عنوان یک سرویس Docker جدا از ایمیج
 * onlyoffice/documentserver) بالا بیاورید — کد این پروژه فقط با آن صحبت
 * می‌کند. تا وقتی ONLYOFFICE_DS_URL خالی باشد، دکمه‌ی «ویرایش آنلاین» اصلاً
 * نمایش داده نمی‌شود (تخریب‌ناپذیر/optional — دقیقاً مثل درایور PDF).
 */
return [
    // آدرس پایه‌ی سرویس Document Server، بدون اسلش انتهایی — مثلاً
    // https://onlyoffice-production.up.railway.app
    'ds_url' => rtrim(env('ONLYOFFICE_DS_URL', ''), '/'),

    // باید دقیقاً همان مقداری باشد که هنگام بالا آوردن Document Server در
    // JWT_SECRET آن گذاشته‌اید.
    'jwt_secret' => env('ONLYOFFICE_JWT_SECRET', ''),

    // اگر روی Document Server هم JWT_HEADER سفارشی تنظیم کرده‌اید، همان نام
    // را این‌جا هم بگذارید؛ پیش‌فرض رسمی ONLYOFFICE خودِ Authorization است.
    'jwt_header' => env('ONLYOFFICE_JWT_HEADER', 'Authorization'),
];
