<?php
/**
 * RFQ-Core Web Installation Wizard
 * After install: DELETE this entire folder (public/install) for security.
 */
session_start();
require __DIR__ . '/Installer.php';

$installer = new Installer(dirname(__DIR__, 2));
$step = isset($_GET['step']) ? (int) $_GET['step'] : 1;
$step = max(1, min(5, $step));

// Block if already installed (unless ?force=1 for recovery)
if ($installer->isInstalled() && empty($_GET['force'])) {
    $step = 5;
    $already = true;
} else {
    $already = false;
}

$flash = ['type' => null, 'message' => null];
$dbTest = null;
$installResults = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'requirements' && $step === 1) {
        $checks = $installer->checkRequirements();
        if ($installer->allRequirementsOk($checks)) {
            header('Location: ?step=2');
            exit;
        }
        $flash = ['type' => 'error', 'message' => 'برخی نیازمندی‌ها برقرار نیستند. موارد قرمز را برطرف کنید.'];
    }

    if ($action === 'database' && $step === 2) {
        $data = [
            'app_name' => trim($_POST['app_name'] ?? 'RFQ-Core'),
            'app_url' => trim($_POST['app_url'] ?? ''),
            'db_host' => trim($_POST['db_host'] ?? '127.0.0.1'),
            'db_port' => trim($_POST['db_port'] ?? '3306'),
            'db_database' => trim($_POST['db_database'] ?? ''),
            'db_username' => trim($_POST['db_username'] ?? ''),
            'db_password' => (string) ($_POST['db_password'] ?? ''),
        ];
        $_SESSION['install_form'] = $data;
        $dbTest = $installer->testDatabase([
            'host' => $data['db_host'],
            'port' => $data['db_port'],
            'database' => $data['db_database'],
            'username' => $data['db_username'],
            'password' => $data['db_password'],
        ]);
        if ($dbTest['ok']) {
            $env = $installer->writeEnv($data);
            if ($env['ok']) {
                header('Location: ?step=3');
                exit;
            }
            $flash = ['type' => 'error', 'message' => $env['message']];
        } else {
            $flash = ['type' => 'error', 'message' => $dbTest['message']];
        }
    }

    if ($action === 'install' && $step === 3) {
        $checks = $installer->checkRequirements();
        if (!$installer->allRequirementsOk($checks)) {
            $flash = ['type' => 'error', 'message' => 'نیازمندی‌ها دیگر برقرار نیستند. به مرحله ۱ برگردید.'];
        } else {
            $installResults = $installer->runArtisanInstall();
            $allOk = true;
            foreach ($installResults as $r) {
                if (!$r['ok'] && $r['id'] !== 'storage' && $r['id'] !== 'optimize') {
                    // storage:link may fail if link exists
                    if ($r['id'] === 'migrate' || $r['id'] === 'seed') {
                        $allOk = false;
                    }
                }
                if ($r['id'] === 'migrate' && !$r['ok']) {
                    $allOk = false;
                }
                if ($r['id'] === 'seed' && !$r['ok']) {
                    $allOk = false;
                }
            }
            // Soft-ok storage link
            foreach ($installResults as &$r) {
                if ($r['id'] === 'storage' && !$r['ok'] && str_contains($r['output'], 'already exists')) {
                    $r['ok'] = true;
                }
            }
            unset($r);

            $criticalOk = true;
            foreach ($installResults as $r) {
                if (in_array($r['id'], ['migrate', 'seed'], true) && !$r['ok']) {
                    $criticalOk = false;
                }
            }

            if ($criticalOk) {
                $installer->writeLock();
                $_SESSION['install_done'] = true;
                header('Location: ?step=4');
                exit;
            }
            $flash = ['type' => 'error', 'message' => 'نصب کامل نشد. خروجی دستورات را بررسی کنید.'];
        }
    }

    if ($action === 'finish' && $step === 4) {
        header('Location: ?step=5');
        exit;
    }
}

$form = $_SESSION['install_form'] ?? [
    'app_name' => 'RFQ-Core',
    'app_url' => ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1'),
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_database' => 'rfq_core',
    'db_username' => 'root',
    'db_password' => '',
];

$checks = $installer->checkRequirements();
$reqOk = $installer->allRequirementsOk($checks);

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ویزارد نصب RFQ-Core</title>
    <style>
        :root { --bg:#0f172a; --card:#1e293b; --ok:#16a34a; --bad:#dc2626; --warn:#ca8a04; --acc:#3b82f6; --text:#e2e8f0; --muted:#94a3b8; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: Tahoma, 'Segoe UI', sans-serif; background: linear-gradient(160deg,#0f172a,#1e3a5f); color: var(--text); min-height:100vh; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 24px 16px 48px; }
        h1 { font-size: 1.35rem; margin: 0 0 8px; }
        .sub { color: var(--muted); font-size: .9rem; margin-bottom: 24px; }
        .steps { display:flex; gap:6px; margin-bottom: 24px; flex-wrap: wrap; }
        .steps span { flex:1; min-width: 60px; text-align:center; padding:8px 4px; border-radius:8px; background:#334155; font-size:12px; color:var(--muted); }
        .steps span.on { background: var(--acc); color:#fff; font-weight:bold; }
        .steps span.done { background:#14532d; color:#bbf7d0; }
        .card { background: var(--card); border-radius: 12px; padding: 20px; box-shadow: 0 8px 30px rgba(0,0,0,.35); }
        .check { display:flex; gap:12px; align-items:flex-start; padding:12px 0; border-bottom:1px solid #334155; }
        .check:last-child { border-bottom:0; }
        .badge { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
        .badge.ok { background: var(--ok); }
        .badge.bad { background: var(--bad); }
        .label { font-weight:600; font-size:14px; }
        .detail { font-size:12px; color: var(--muted); margin-top:4px; }
        label { display:block; font-size:13px; margin:12px 0 4px; color: var(--muted); }
        input { width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff; font-size:14px; }
        input:focus { outline:2px solid var(--acc); border-color:transparent; }
        .row { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
        @media(max-width:560px){ .row { grid-template-columns:1fr; } }
        .btn { display:inline-block; margin-top:16px; padding:12px 20px; border:0; border-radius:8px; background:var(--acc); color:#fff; font-size:14px; cursor:pointer; font-family:inherit; }
        .btn:disabled { opacity:.5; cursor:not-allowed; }
        .btn.secondary { background:#475569; }
        .btn.danger { background: var(--bad); }
        .alert { padding:12px 14px; border-radius:8px; margin-bottom:16px; font-size:13px; }
        .alert.error { background:#450a0a; border:1px solid #991b1b; }
        .alert.ok { background:#052e16; border:1px solid #166534; }
        .alert.warn { background:#422006; border:1px solid #a16207; }
        pre { background:#0f172a; padding:10px; border-radius:8px; overflow:auto; font-size:11px; max-height:160px; direction:ltr; text-align:left; }
        .ok-text { color: #4ade80; }
        .bad-text { color: #f87171; }
        ul.tips { font-size:13px; color:var(--muted); line-height:1.7; }
        code { background:#0f172a; padding:2px 6px; border-radius:4px; font-size:12px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>ویزارد نصب سیستم RFQ-Core</h1>
    <p class="sub">نصب گام‌به‌گام — پس از اتمام، پوشه <code>public/install</code> را از سرور حذف کنید.</p>

    <div class="steps">
        <?php
        $labels = [1=>'نیازمندی‌ها', 2=>'دیتابیس', 3=>'نصب', 4=>'نتیجه', 5=>'پایان'];
        foreach ($labels as $n => $lab) {
            $cls = $n === $step ? 'on' : ($n < $step ? 'done' : '');
            echo '<span class="'.h($cls).'">'.h($n.'. '.$lab).'</span>';
        }
        ?>
    </div>

    <?php if ($flash['message']): ?>
        <div class="alert <?= $flash['type'] === 'error' ? 'error' : 'ok' ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">بررسی نیازمندی‌های سرور</h2>
        <?php foreach ($checks as $c): ?>
            <div class="check">
                <div class="badge <?= $c['ok'] ? 'ok' : 'bad' ?>"><?= $c['ok'] ? '✓' : '!' ?></div>
                <div>
                    <div class="label"><?= h($c['label']) ?></div>
                    <div class="detail"><?= h($c['detail']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php foreach ($installer->warnings as $w): ?>
            <div class="alert warn"><?= h($w) ?></div>
        <?php endforeach; ?>

        <?php if (!$reqOk): ?>
            <div class="alert error">تا وقتی موارد قرمز برطرف نشوند نمی‌توانید ادامه دهید. معمولاً مشکل از PHP، افزونه، یا نبود <code>composer install</code> است.</div>
            <ul class="tips">
                <li>در ریشه پروژه: <code>composer install</code></li>
                <li>مجوز: <code>chmod -R 775 storage bootstrap/cache</code></li>
                <li>فعال‌سازی <code>pdo_mysql</code> در PHP</li>
            </ul>
            <form method="get"><button class="btn secondary" type="submit">بررسی دوباره</button></form>
        <?php else: ?>
            <div class="alert ok">همه بررسی‌های اجباری موفق بودند.</div>
            <form method="post">
                <input type="hidden" name="action" value="requirements">
                <button class="btn" type="submit">ادامه → تنظیم دیتابیس</button>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($step === 2): ?>
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">اطلاعات برنامه و دیتابیس</h2>
        <form method="post">
            <input type="hidden" name="action" value="database">
            <label>نام برنامه</label>
            <input name="app_name" value="<?= h($form['app_name']) ?>" required>
            <label>آدرس سایت (APP_URL)</label>
            <input name="app_url" value="<?= h($form['app_url']) ?>" required placeholder="https://example.com">
            <div class="row">
                <div>
                    <label>هاست دیتابیس</label>
                    <input name="db_host" value="<?= h($form['db_host']) ?>" required>
                </div>
                <div>
                    <label>پورت</label>
                    <input name="db_port" value="<?= h($form['db_port']) ?>" required>
                </div>
            </div>
            <label>نام دیتابیس</label>
            <input name="db_database" value="<?= h($form['db_database']) ?>" required>
            <label>نام کاربری MySQL</label>
            <input name="db_username" value="<?= h($form['db_username']) ?>" required>
            <label>رمز عبور MySQL</label>
            <input type="password" name="db_password" value="<?= h($form['db_password']) ?>" autocomplete="new-password">
            <p class="detail" style="margin-top:12px">اگر دیتابیس وجود نداشته باشد و کاربر مجوز داشته باشد، تلاش می‌شود ساخته شود.</p>
            <button class="btn" type="submit">تست اتصال و ذخیره .env</button>
            <a class="btn secondary" href="?step=1" style="margin-right:8px;text-decoration:none">بازگشت</a>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($step === 3): ?>
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">اجرای نصب</h2>
        <p class="detail">با تأیید، دستورات زیر اجرا می‌شوند:</p>
        <ul class="tips">
            <li><code>php artisan key:generate</code></li>
            <li><code>php artisan migrate --force</code></li>
            <li><code>php artisan db:seed --force</code></li>
            <li><code>php artisan storage:link</code></li>
        </ul>
        <div class="alert warn">این مرحله جداول را می‌سازد و کاربر ادمین پیش‌فرض را seed می‌کند. اگر قبلاً دیتابیس پر بوده، ممکن است خطا بدهد.</div>

        <?php if ($installResults): ?>
            <?php foreach ($installResults as $r): ?>
                <div class="check">
                    <div class="badge <?= $r['ok'] ? 'ok' : 'bad' ?>"><?= $r['ok'] ? '✓' : '!' ?></div>
                    <div style="flex:1">
                        <div class="label"><?= h($r['id']) ?></div>
                        <pre><?= h($r['output'] ?: '(بدون خروجی)') ?></pre>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="post" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='در حال نصب...';">
            <input type="hidden" name="action" value="install">
            <button class="btn" type="submit">شروع نصب</button>
            <a class="btn secondary" href="?step=2" style="margin-right:8px;text-decoration:none">بازگشت</a>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($step === 4): ?>
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem" class="ok-text">نصب با موفقیت انجام شد</h2>
        <p>سیستم RFQ-Core روی این سرور نصب و پیکربندی اولیه شد.</p>
        <div class="alert ok">
            <strong>ورود اولیه ادمین</strong><br>
            ایمیل: <code>admin@example.com</code><br>
            رمز: <code>password</code><br>
            بلافاصله پس از ورود رمز را عوض کنید.
        </div>
        <div class="alert warn">
            <strong>امنیت:</strong> پوشه <code>public/install</code> را از سرور حذف کنید تا ویزارد در دسترس عموم نباشد.
        </div>
        <ul class="tips">
            <li>حذف از SSH: <code>rm -rf public/install</code></li>
            <li>یا از پنل هاست پوشه <code>install</code> داخل <code>public</code> را پاک کنید</li>
            <li>در production مقدار <code>APP_DEBUG=false</code> را در .env بگذارید</li>
        </ul>
        <form method="post">
            <input type="hidden" name="action" value="finish">
            <button class="btn" type="submit">مرحله پایانی</button>
        </form>
        <p style="margin-top:16px"><a class="btn secondary" style="text-decoration:none" href="../">ورود به برنامه</a></p>
    </div>
    <?php endif; ?>

    <?php if ($step === 5): ?>
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">پایان ویزارد</h2>
        <?php if ($already || $installer->isInstalled()): ?>
            <div class="alert ok">قفل نصب موجود است — سیستم قبلاً نصب شده است.</div>
        <?php endif; ?>
        <p>اگر هنوز پوشه ویزارد را حذف نکرده‌اید، همین حالا حذف کنید.</p>
        <p><a class="btn" style="text-decoration:none" href="/">صفحه اصلی برنامه</a></p>
        <p class="detail" style="margin-top:20px">برای نصب مجدد اضطراری (فقط در محیط امن): <code>?step=1&force=1</code></p>
    </div>
    <?php endif; ?>

    <p class="sub" style="margin-top:24px;text-align:center">RFQ-Core Installer · پس از نصب این پوشه را حذف کنید</p>
</div>
</body>
</html>
