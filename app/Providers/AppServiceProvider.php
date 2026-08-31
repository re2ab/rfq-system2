<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Route;
use App\Models\Mail\MailAccount;
use App\Models\Mail\MailMessage;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $helpers = app_path('helpers.php');
        if (is_file($helpers)) {
            require_once $helpers;
        }
    }


    public function boot(): void
    {
        $this->ensureStoragePaths();

        Paginator::defaultView('vendor.pagination.rfq');
        Paginator::defaultSimpleView('vendor.pagination.rfq');
        \Illuminate\Support\Facades\Blade::directive('jdate', function ($expression) {
            return "<?php echo jdate($expression); ?>";
        });
        \Illuminate\Support\Facades\Blade::directive('fanum', function ($expression) {
            return "<?php echo fa_num($expression); ?>";
        });
        Schema::defaultStringLength(191);

        // فاز A: bind پارامتر {account} در روت‌های mail/accounts
        Route::bind('account', function ($value) {
            return MailAccount::findOrFail($value);
        });
        Route::bind('message', function ($value) {
            return MailMessage::findOrFail($value);
        });

        $root = config('app.url');
        if ($root && !str_contains((string) $root, 'localhost') && !str_contains((string) $root, '127.0.0.1')) {
            URL::forceRootUrl($root);
            if (str_starts_with((string) $root, 'https://')) {
                URL::forceScheme('https');
            }
        }
    }

    /** مسیرهای ضروری storage برای artisan/view روی PaaS تازه‌ساخته */
    protected function ensureStoragePaths(): void
    {
        foreach ([
            storage_path('framework/views'),
            storage_path('framework/cache'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('logs'),
            storage_path('app/public'),
            base_path('bootstrap/cache'),
        ] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
        // اگر VIEW_COMPILED_PATH خالی است، همان مسیر پیش‌فرض را ست کن
        if (!config('view.compiled')) {
            config(['view.compiled' => storage_path('framework/views')]);
        }
    }
}
