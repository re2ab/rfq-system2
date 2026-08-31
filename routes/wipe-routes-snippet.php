<?php
// این دو خط را داخل گروه middleware auth + settings ادمین در routes/web.php اضافه کنید
// (کنار بقیه routeهای settings.backup):

Route::post('/settings/backup/factory-reset', [\App\Http\Controllers\SettingsController::class, 'factoryReset'])
    ->name('settings.factory.reset');
Route::post('/settings/backup/wipe-section', [\App\Http\Controllers\SettingsController::class, 'wipeSection'])
    ->name('settings.wipe.section');
