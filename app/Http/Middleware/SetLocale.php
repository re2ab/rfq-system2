<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = config('app.locale', 'fa');

        try {
            if ($request->hasSession()) {
                $fromSession = $request->session()->get('locale');
                if (is_string($fromSession) && $fromSession !== '') {
                    $locale = $fromSession;
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            if (Auth::check()) {
                $user = Auth::user();
                if ($user && isset($user->locale) && is_string($user->locale) && $user->locale !== '') {
                    $locale = $user->locale;
                }
            }
        } catch (\Throwable $e) {
        }

        if (in_array($locale, ['fa', 'en'], true)) {
            App::setLocale($locale);
        }

        // تایم‌زون از تنظیمات سیستم (ظاهر و برند)
        try {
            $tz = \App\Models\AppSetting::get('app_timezone', config('app.timezone', 'Asia/Tehran'));
            if (is_string($tz) && $tz !== '' && in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
                Config::set('app.timezone', $tz);
                date_default_timezone_set($tz);
                Date::useLocale(App::getLocale());
            }
        } catch (\Throwable $e) {
            // DB ممکن است هنوز آماده نباشد
        }

        return $next($request);
    }
}
