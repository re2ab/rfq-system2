<?php

use App\Http\Controllers\OnlyOfficeController;
use Illuminate\Support\Facades\Route;

/**
 * M11 — این دو مسیر عمداً این‌جا (نه routes/web.php) هستند: باید بدون
 * سشن/کوکی/CSRF از خودِ سرویس ONLYOFFICE Document Server (سرور به سرور)
 * صدا زده شوند. امنیتشان با Laravel Signed URL تأمین می‌شود
 * (URL::temporarySignedRoute در OnlyOfficeConfigService) — نه احراز هویت
 * معمولی. مسیر editOnline (صفحه‌ی خودِ ادیتور که کاربر لاگین‌کرده باز
 * می‌کند) همچنان در routes/web.php است.
 */
Route::middleware('signed')->get('/onlyoffice/download/{revision}', [OnlyOfficeController::class, 'download'])
    ->name('onlyoffice.download');

Route::middleware('signed')->post('/onlyoffice/callback/{revision}', [OnlyOfficeController::class, 'callback'])
    ->name('onlyoffice.callback');
