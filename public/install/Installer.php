<?php
/**
 * RFQ-Core Installation Wizard — core logic
 * Delete the entire public/install directory after successful setup.
 */

class Installer
{
    public string $basePath;
    public array $errors = [];
    public array $warnings = [];

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?: dirname(__DIR__, 2);
    }

    public function isInstalled(): bool
    {
        $lock = $this->basePath . '/storage/app/install.lock';
        return is_file($lock);
    }

    public function writeLock(): void
    {
        $dir = $this->basePath . '/storage/app';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents(
            $dir . '/install.lock',
            json_encode([
                'installed_at' => date('c'),
                'message' => 'RFQ-Core installed. Delete public/install for security.',
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    /** @return array<int, array{id:string,label:string,ok:bool,detail:string}> */
    public function checkRequirements(): array
    {
        $checks = [];

        $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
        $checks[] = [
            'id' => 'php',
            'label' => 'نسخه PHP (حداقل ۸.۲)',
            'ok' => $phpOk,
            'detail' => 'نسخه فعلی: ' . PHP_VERSION,
        ];

        $extensions = [
            'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'json',
            'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'tokenizer', 'xml', 'zip',
        ];
        $missing = [];
        foreach ($extensions as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        $checks[] = [
            'id' => 'extensions',
            'label' => 'افزونه‌های ضروری PHP',
            'ok' => count($missing) === 0,
            'detail' => count($missing) === 0
                ? 'همه افزونه‌های لازم موجودند'
                : 'کمبود: ' . implode(', ', $missing),
        ];

        if (!extension_loaded('gd') && !extension_loaded('imagick')) {
            $this->warnings[] = 'افزونه gd یا imagick برای پردازش تصویر توصیه می‌شود (فعلاً اجباری نیست).';
        }

        $vendor = is_file($this->basePath . '/vendor/autoload.php');
        $checks[] = [
            'id' => 'vendor',
            'label' => 'وابستگی‌های Composer (پوشه vendor)',
            'ok' => $vendor,
            'detail' => $vendor
                ? 'vendor پیدا شد'
                : 'پوشه vendor نیست. قبل از ادامه در ریشه پروژه اجرا کنید: composer install',
        ];

        $writablePaths = [
            'storage' => $this->basePath . '/storage',
            'bootstrap/cache' => $this->basePath . '/bootstrap/cache',
        ];
        $notWritable = [];
        foreach ($writablePaths as $label => $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            if (!is_dir($path) || !is_writable($path)) {
                $notWritable[] = $label;
            }
        }
        $checks[] = [
            'id' => 'permissions',
            'label' => 'دسترسی نوشتن storage و bootstrap/cache',
            'ok' => count($notWritable) === 0,
            'detail' => count($notWritable) === 0
                ? 'مجوز نوشتن برقرار است'
                : 'بدون دسترسی نوشتن: ' . implode(', ', $notWritable),
        ];

        $envExample = is_file($this->basePath . '/.env.example') || is_file($this->basePath . '/.env');
        $checks[] = [
            'id' => 'env_example',
            'label' => 'فایل محیط (.env یا .env.example)',
            'ok' => $envExample,
            'detail' => $envExample ? 'موجود است' : 'فایل .env.example یافت نشد',
        ];

        $artisan = is_file($this->basePath . '/artisan');
        $checks[] = [
            'id' => 'artisan',
            'label' => 'فایل artisan لاراول',
            'ok' => $artisan,
            'detail' => $artisan ? 'موجود است' : 'artisan یافت نشد — ساختار پروژه ناقص است',
        ];

        $pdo = extension_loaded('pdo_mysql');
        $checks[] = [
            'id' => 'pdo_mysql',
            'label' => 'درایور PDO MySQL',
            'ok' => $pdo,
            'detail' => $pdo ? 'آماده اتصال به MySQL' : 'pdo_mysql فعال نیست',
        ];

        return $checks;
    }

    public function allRequirementsOk(array $checks): bool
    {
        foreach ($checks as $c) {
            if (!$c['ok']) {
                return false;
            }
        }
        return true;
    }

    public function testDatabase(array $db): array
    {
        $host = $db['host'] ?? '127.0.0.1';
        $port = $db['port'] ?? '3306';
        $name = $db['database'] ?? '';
        $user = $db['username'] ?? '';
        $pass = $db['password'] ?? '';

        if ($name === '' || $user === '') {
            return ['ok' => false, 'message' => 'نام دیتابیس و نام کاربری الزامی است.'];
        }

        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$name}`");
            return ['ok' => true, 'message' => "اتصال موفق. دیتابیس «{$name}» آماده است."];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'خطای اتصال: ' . $e->getMessage()];
        }
    }

    public function writeEnv(array $input): array
    {
        $examplePath = $this->basePath . '/.env.example';
        $envPath = $this->basePath . '/.env';

        if (is_file($envPath)) {
            $content = file_get_contents($envPath);
        } elseif (is_file($examplePath)) {
            $content = file_get_contents($examplePath);
        } else {
            $content = "APP_NAME=RFQ-Core\nAPP_ENV=local\nAPP_KEY=\nAPP_DEBUG=true\nAPP_URL=http://localhost\n\nDB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_DATABASE=rfq_core\nDB_USERNAME=root\nDB_PASSWORD=\n";
        }

        $replacements = [
            'APP_NAME' => $input['app_name'] ?? 'RFQ-Core',
            'APP_URL' => rtrim($input['app_url'] ?? 'http://127.0.0.1:8000', '/'),
            'APP_ENV' => 'local',
            'APP_DEBUG' => 'true',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $input['db_host'] ?? '127.0.0.1',
            'DB_PORT' => $input['db_port'] ?? '3306',
            'DB_DATABASE' => $input['db_database'] ?? 'rfq_core',
            'DB_USERNAME' => $input['db_username'] ?? 'root',
            'DB_PASSWORD' => $input['db_password'] ?? '',
        ];

        foreach ($replacements as $key => $value) {
            $value = str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value);
            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", $key . '=' . $this->envValue($value), $content);
            } else {
                $content .= "\n{$key}=" . $this->envValue($value);
            }
        }

        if (@file_put_contents($envPath, $content) === false) {
            return ['ok' => false, 'message' => 'نتوانست فایل .env را بنویسد. مجوز ریشه پروژه را بررسی کنید.'];
        }

        return ['ok' => true, 'message' => 'فایل .env ذخیره شد.'];
    }

    private function envValue(string $value): string
    {
        if ($value === '' || preg_match('/\s|#|"|\'/', $value)) {
            return '"' . addcslashes($value, '"\\') . '"';
        }
        return $value;
    }

    /** Run artisan commands; returns list of step results */
    public function runArtisanInstall(): array
    {
        $results = [];
        $php = $this->findPhpBinary();
        $artisan = escapeshellarg($this->basePath . '/artisan');
        $cwd = $this->basePath;

        $commands = [
            ['key', "{$php} {$artisan} key:generate --force"],
            ['migrate', "{$php} {$artisan} migrate --force"],
            ['seed', "{$php} {$artisan} db:seed --force"],
            ['storage', "{$php} {$artisan} storage:link"],
            ['optimize', "{$php} {$artisan} config:clear"],
        ];

        foreach ($commands as [$id, $cmd]) {
            $out = [];
            $code = 0;
            $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = @proc_open($cmd, $descriptors, $pipes, $cwd, null);
            if (!is_resource($proc)) {
                // fallback
                exec($cmd . ' 2>&1', $out, $code);
                $output = implode("\n", $out);
            } else {
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $code = proc_close($proc);
                $output = trim($stdout . "\n" . $stderr);
            }

            $results[] = [
                'id' => $id,
                'ok' => $code === 0,
                'code' => $code,
                'output' => mb_substr($output, 0, 2000),
            ];

            // key generate failure is soft if key already exists
            if ($id === 'key' && $code !== 0 && is_file($this->basePath . '/.env')) {
                $env = file_get_contents($this->basePath . '/.env');
                if (preg_match('/^APP_KEY=base64:.+/m', $env)) {
                    $results[count($results) - 1]['ok'] = true;
                    $results[count($results) - 1]['output'] .= "\n(کلید از قبل موجود بود)";
                }
            }

            if ($id === 'migrate' && $code !== 0) {
                break; // stop chain
            }
        }

        return $results;
    }

    private function findPhpBinary(): string
    {
        if (defined('PHP_BINARY') && PHP_BINARY) {
            return escapeshellarg(PHP_BINARY);
        }
        return 'php';
    }
}
