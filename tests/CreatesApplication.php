<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

/**
 * بوت‌استرپ استاندارد اپلیکیشن لاراول برای تست‌های Feature — این فایل و
 * tests/TestCase.php قبلاً در این ریپازیتوری وجود نداشتند (فقط tests/Unit
 * پوشش داده شده بود، چون در sandbox توسعه composer/artisan قابل اجرا نبود).
 * اضافه‌شدن این دو فایل استاندارد لاراول ۱۱ است — بدون آن، tests/Feature
 * اصلاً کشف نمی‌شد.
 */
trait CreatesApplication
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
