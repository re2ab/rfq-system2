<?php
return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            // پیش‌فرض همان storage/app سابق است؛ اگر LOCAL_DISK_ROOT ست شده باشد
            // (مثلاً مسیر یک Volume دائمی روی Railway مثل /data/app) از همان
            // استفاده می‌شود. عمداً env-based است نه LARAVEL_STORAGE_PATH — آن
            // متغیر کل storage_path() (شامل framework/views و بوت زمان build)
            // را عوض می‌کند و چون Volume فقط در runtime وصل است نه در build،
            // باعث شکست artisan package:discover/view compiler هنگام دیپلوی
            // می‌شد. این‌جا فقط دیسک فایل‌های آپلودی جابه‌جا می‌شود.
            'root' => env('LOCAL_DISK_ROOT', storage_path('app')),
            'throw' => false,
        ],
        'public' => [
            'driver' => 'local',
            'root' => env('PUBLIC_DISK_ROOT', storage_path('app/public')),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],
    ],
    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
