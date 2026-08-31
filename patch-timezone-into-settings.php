<?php
/**
 * فقط متدهای appearance / saveAppearance را برای تایم‌زون وصله می‌کند.
 * کل SettingsController را جایگزین نمی‌کند.
 *
 * اجرا از ریشه پروژه:
 *   php patch-timezone-into-settings.php
 */
$path = __DIR__ . '/app/Http/Controllers/SettingsController.php';
if (!is_file($path)) {
    fwrite(STDERR, "SettingsController.php not found\n");
    exit(1);
}
$src = file_get_contents($path);
$orig = $src;

// Remove accidental zip_skipped garbage inside saveAppearance if present
$src = preg_replace(
    '/if\s*\(\s*!empty\(\$result\[\'zip_skipped\'\]\)\s*\)\s*\{[^}]*\}\s*/',
    '',
    $src
);

if (strpos($src, "app_timezone") !== false && strpos($src, "Asia/Tehran") !== false) {
    echo "Already has app_timezone. Skipping controller inject.\n";
} else {
    // Inject timezone into appearance() return array
    if (strpos($src, "'primary_color' => \\App\\Models\\AppSetting::get('primary_color'") !== false
        || strpos($src, "'primary_color' => \\App\\Models\\AppSetting::get(\"primary_color\"") !== false) {
        $src = preg_replace(
            "/('primary_color'\\s*=>\\s*\\\\App\\\\Models\\\\AppSetting::get\\('primary_color',\\s*'[^']*'\\),)/",
            "$1\n            'app_timezone' => \\App\\Models\\AppSetting::get('app_timezone', config('app.timezone', 'Asia/Tehran')),\n            'timezones' => [\n                'Asia/Tehran' => 'تهران (ایران) — Asia/Tehran',\n                'Asia/Dubai' => 'دبی — Asia/Dubai',\n                'Asia/Kabul' => 'کابل — Asia/Kabul',\n                'Asia/Baghdad' => 'بغداد — Asia/Baghdad',\n                'Europe/Istanbul' => 'استانبول — Europe/Istanbul',\n                'Europe/London' => 'لندن — Europe/London',\n                'Europe/Berlin' => 'برلین — Europe/Berlin',\n                'UTC' => 'UTC',\n                'America/New_York' => 'نیویورک — America/New_York',\n            ],",
            $src,
            1
        );
    } elseif (preg_match("/return view\\('settings\\.appearance'/", $src)) {
        $src = preg_replace(
            "/(return view\\('settings\\.appearance',\\s*\\[)/",
            "$1\n            'app_timezone' => \\App\\Models\\AppSetting::get('app_timezone', config('app.timezone', 'Asia/Tehran')),\n            'timezones' => [\n                'Asia/Tehran' => 'تهران (ایران)',\n                'Asia/Dubai' => 'دبی',\n                'UTC' => 'UTC',\n                'Europe/London' => 'لندن',\n            ],\n",
            $src,
            1
        );
    }

    // Inject save of timezone before "return back()->with('success'" inside saveAppearance
    if (strpos($src, "function saveAppearance") !== false && strpos($src, "set('app_timezone'") === false) {
        $src = preg_replace(
            "/(function saveAppearance\\([^{]+\\{)([\\s\\S]*?)(return back\\(\\)->with\\('success',)/",
            "$1$2\n        \$tz = \$request->input('app_timezone', 'Asia/Tehran');\n        \$allowed = ['Asia/Tehran','Asia/Dubai','Asia/Kabul','Asia/Baghdad','Europe/Istanbul','Europe/London','Europe/Berlin','UTC','America/New_York'];\n        if (in_array(\$tz, \$allowed, true)) {\n            \\App\\Models\\AppSetting::set('app_timezone', \$tz);\n            config(['app.timezone' => \$tz]);\n            @date_default_timezone_set(\$tz);\n        }\n        $3",
            $src,
            1
        );
        // also allow validate field
        $src = preg_replace(
            "/(function saveAppearance\\([\\s\\S]*?\\$request->validate\\(\\[)/",
            "$1\n            'app_timezone' => 'nullable|string|max:64',",
            $src,
            1
        );
    }
    echo "Controller patched surgically.\n";
}

if ($src !== $orig) {
    file_put_contents($path . '.bak-before-tz', $orig);
    file_put_contents($path, $src);
    echo "Backup: SettingsController.php.bak-before-tz\n";
    echo "Written SettingsController.php\n";
} else {
    echo "No controller text changes (or already patched).\n";
}
echo "Done.\n";
