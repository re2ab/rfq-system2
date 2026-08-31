<?php
// Run once from project root: php patch-routes.php
$path = __DIR__ . '/routes/web.php';
if (!is_file($path)) {
    fwrite(STDERR, "routes/web.php not found\n");
    exit(1);
}
$src = file_get_contents($path);
if (strpos($src, "settings.wipe.section") !== false) {
    echo "Routes already present.\n";
    exit(0);
}
$snippet = <<<'PHP'

        Route::post('/backup/factory-reset', [SettingsController::class, 'factoryReset'])->name('settings.factory.reset');
        Route::post('/backup/wipe-section', [SettingsController::class, 'wipeSection'])->name('settings.wipe.section');
PHP;
// insert after backup run route if present
$markers = [
    "->name('settings.backup.run');",
    "->name('settings.backup');",
    "Route::get('/backup'",
];
$done = false;
foreach ($markers as $m) {
    $pos = strpos($src, $m);
    if ($pos === false) continue;
    $end = $pos + strlen($m);
    $src = substr($src, 0, $end) . $snippet . substr($src, $end);
    $done = true;
    break;
}
if (!$done) {
    fwrite(STDERR, "Could not find insertion point. Add routes manually from routes/ADD_THESE_TO_web.php\n");
    exit(2);
}
file_put_contents($path, $src);
echo "OK: wipe/factory routes inserted into routes/web.php\n";
