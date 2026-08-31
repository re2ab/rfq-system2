<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // M11: فقط برای دو نقطه‌ی ONLYOFFICE (download/callback) که باید بدون
        // سشن/کوکی و بدون CSRF از سرور Document Server صدا زده شوند — گروه
        // میان‌افزار پیش‌فرض api (بدون VerifyCsrfToken) دقیقاً همین را می‌دهد.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // M11-fix: روی Railway (و هر PaaS مشابه)، SSL جلوی اپ (روی edge/پراکسی)
        // terminate می‌شود و درخواست با HTTP ساده به کانتینر می‌رسد؛ بدون این
        // خط Laravel اسکیم درخواست را «http» تشخیص می‌دهد نه «https» — و چون
        // امضای لینک‌های signed (دانلود/callback ONLYOFFICE، از جمله) شامل کل
        // URL از جمله اسکیم است، این ناهماهنگی باعث می‌شد امضای معتبر همیشه
        // نامعتبر تشخیص داده شود و ONLYOFFICE هنگام callback با 403 مواجه شود.
        // '*' یعنی به هدرهای X-Forwarded-* از هر پراکسی اعتماد کن — امن است
        // چون در این استقرار، Railway تنها نقطه‌ی ورودی به کانتینر است.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'module' => \App\Http\Middleware\EnsureModuleEnabled::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
