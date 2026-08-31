<?php
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::post('/settings/backup/wipe-section', [SettingsController::class, 'wipeSection'])
    ->middleware(['web', 'auth'])->name('settings.wipe.section');
Route::post('/settings/backup/factory-reset', [SettingsController::class, 'factoryReset'])
    ->middleware(['web', 'auth'])->name('settings.factory.reset');
Route::get('/settings/backup/wipe-section', fn () => redirect('/settings/backup')->with('error', 'فقط از فرم POST'))
    ->middleware(['web', 'auth']);
Route::get('/settings/backup/factory-reset', fn () => redirect('/settings/backup')->with('error', 'فقط از فرم POST'))
    ->middleware(['web', 'auth']);
