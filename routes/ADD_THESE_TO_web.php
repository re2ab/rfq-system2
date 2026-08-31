<?php
/**
 * این دو Route را داخل گروه settings (جایی که Route::get('/backup', ...) هست)
 * اضافه کنید — معمولاً prefix: settings و middleware: auth + can:settings.manage
 *
 * اگر خطای Route [settings.wipe.section] not defined می‌گیرید، یعنی این دو خط روی سرور نیست.
 */

        Route::post('/backup/factory-reset', [\App\Http\Controllers\SettingsController::class, 'factoryReset'])
            ->name('settings.factory.reset');
        Route::post('/backup/wipe-section', [\App\Http\Controllers\SettingsController::class, 'wipeSection'])
            ->name('settings.wipe.section');
