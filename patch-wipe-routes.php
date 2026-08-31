<?php
// اجرا از ریشه پروژه: php patch-wipe-routes.php
$web = __DIR__ . '/routes/web.php';
$inc = "\nrequire __DIR__.'/rfq_wipe_routes.php';\n";
if (!is_file($web)) {
    fwrite(STDERR, "routes/web.php not found\n");
    exit(1);
}
$src = file_get_contents($web);
if (strpos($src, 'rfq_wipe_routes.php') !== false || strpos($src, 'settings.wipe.section') !== false) {
    // still ensure include for absolute routes outside group
    if (strpos($src, 'rfq_wipe_routes.php') === false) {
        file_put_contents($web, $src . $inc);
        echo "Appended require rfq_wipe_routes.php\n";
    } else {
        echo "Already patched.\n";
    }
    exit(0);
}
file_put_contents($web, $src . $inc);
echo "OK: require routes/rfq_wipe_routes.php appended to web.php\n";
