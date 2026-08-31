<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use App\Models\AppSetting;

class BackupService
{
    /** Logical sections → tables (order matters for restore of relations) */
    public function sections(): array
    {
        return [
            'settings' => [
                'label' => 'تنظیمات، ماژول‌ها، قالب‌ها، شماره‌گذاری',
                'tables' => ['app_settings', 'modules', 'templates', 'template_versions', 'number_sequences', 'custom_field_definitions'],
            ],
            'users' => [
                'label' => 'کاربران و نقش‌ها',
                'tables' => ['users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions'],
            ],
            'organizations' => [
                'label' => 'سازمان‌ها',
                'tables' => ['organizations'],
            ],
            'contacts' => [
                'label' => 'مخاطبان و یادداشت محرمانه',
                'tables' => ['contacts', 'contact_confidential_notes', 'custom_field_values'],
            ],
            'cases' => [
                'label' => 'پرونده‌ها، تاریخچه وضعیت، فعالیت‌ها',
                'tables' => ['cases', 'case_status_histories', 'case_activities', 'activity_reactions', 'activity_mentions', 'case_user'],
            ],
            'tasks' => [
                'label' => 'وظایف',
                'tables' => ['tasks', 'task_checklist_items', 'task_user'],
            ],
            'finance' => [
                'label' => 'مطالبات و پرداخت‌ها',
                'tables' => ['receivables', 'payments', 'deliveries'],
            ],
            'documents' => [
                'label' => 'اسناد و ردیف‌های سند',
                'tables' => ['documents', 'document_revisions', 'document_lines'],
            ],
            'attachments' => [
                'label' => 'فایل‌های پیوست (متادیتا)',
                'tables' => ['attachments'],
            ],
            'emails' => [
                'label' => 'ایمیل‌ها',
                'tables' => ['emails'],
            ],
            'misc' => [
                'label' => 'اعلان‌ها، تگ‌ها، ویوهای ذخیره‌شده',
                'tables' => ['app_notifications', 'tags', 'taggables', 'saved_views', 'pipeline_stages', 'pipeline_transitions'],
            ],
        ];
    }

    public function fullTables(): array
    {
        $all = [];
        foreach ($this->sections() as $sec) {
            foreach ($sec['tables'] as $t) {
                $all[] = $t;
            }
        }
        $all[] = 'backup_jobs';
        return array_values(array_unique($all));
    }

    public function export(bool $encrypt = true, string $type = 'manual', ?int $userId = null, ?array $sectionKeys = null): array
    {
        $sections = $this->sections();
        if ($sectionKeys === null || $sectionKeys === ['full']) {
            $keys = array_keys($sections);
            $scope = 'full';
        } else {
            $keys = array_values(array_intersect(array_keys($sections), $sectionKeys));
            $scope = implode(',', $keys);
        }

        $payload = [
            'format' => 'rfq-backup-v3',
            'scope' => $scope,
            'sections' => $keys,
            'exported_at' => now()->toIso8601String(),
            'app' => 'RFQ-Core',
            'tables' => [],
        ];

        $tableSet = [];
        foreach ($keys as $k) {
            foreach ($sections[$k]['tables'] as $table) {
                $tableSet[$table] = true;
            }
        }

        foreach (array_keys($tableSet) as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $payload['tables'][$table] = DB::table($table)->get()->map(fn ($r) => (array) $r)->all();
        }

        // optional: list attachment file paths for separate archive note
        $payload['attachment_files_note'] = 'فایل‌های فیزیکی storage/app/public را جداگانه همگام کنید؛ این بک‌آپ متادیتای attachments را دارد.';

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $suffix = $scope === 'full' ? 'full' : 'partial-'.preg_replace('/[^a-z0-9,_\-]+/i', '', $scope);
        $filename = 'rfq-'.$suffix.'-'.date('Ymd-His').($encrypt ? '.enc' : '.json');
        $relative = 'backups/'.$filename;

        $stored = $encrypt ? Crypt::encryptString($json) : $json;
        Storage::disk('local')->put($relative, $stored);
        $fullPath = storage_path('app/'.$relative);
        $size = @filesize($fullPath) ?: strlen($stored);

        $this->logJob($filename, $relative, $type, $encrypt, $size, $userId, $scope);

        // retention
        $this->pruneOldBackups();

        // cloud push if configured
        try {
            app(CloudBackupService::class)->pushIfEnabled($fullPath, $filename);
        } catch (\Throwable $e) {
            // non-fatal
        }

        return [
            'filename' => $filename,
            'path' => $relative,
            'full_path' => $fullPath,
            'size' => $size,
            'scope' => $scope,
        ];
    }

    public function restore(string $absolutePath, string $mode = 'full', ?int $keepUserId = null, ?array $onlySections = null): array
    {
        $raw = file_get_contents($absolutePath);
        if ($raw === false) {
            return ['ok' => false, 'message' => 'خواندن فایل ممکن نیست.'];
        }

        $json = $raw;
        try {
            $json = Crypt::decryptString($raw);
        } catch (\Throwable $e) {
        }

        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['tables'])) {
            return ['ok' => false, 'message' => 'فرمت پشتیبان نامعتبر است.'];
        }

        $sections = $this->sections();
        if ($onlySections) {
            $allowedTables = [];
            foreach ($onlySections as $sk) {
                if (isset($sections[$sk])) {
                    foreach ($sections[$sk]['tables'] as $t) {
                        $allowedTables[$t] = true;
                    }
                }
            }
            $tables = array_values(array_intersect(array_keys($data['tables']), array_keys($allowedTables)));
            $truncateList = array_keys($allowedTables);
        } elseif ($mode === 'settings_only') {
            $tables = array_values(array_intersect(array_keys($data['tables']), $sections['settings']['tables']));
            $truncateList = $sections['settings']['tables'];
        } else {
            $tables = array_keys($data['tables']);
            $truncateList = $this->fullTables();
        }

        $restored = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }

            foreach ($truncateList as $t) {
                if (!Schema::hasTable($t) || $t === 'backup_jobs') {
                    continue;
                }
                if ($t === 'users' && $keepUserId) {
                    DB::table('users')->where('id', '!=', $keepUserId)->delete();
                } else {
                    try {
                        DB::table($t)->truncate();
                    } catch (\Throwable $e) {
                        DB::table($t)->delete();
                    }
                }
            }

            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }

            foreach ($tables as $table) {
                if (!Schema::hasTable($table) || empty($data['tables'][$table]) || $table === 'backup_jobs') {
                    continue;
                }
                foreach ($data['tables'][$table] as $row) {
                    if ($table === 'users' && $keepUserId && isset($row['id']) && (int) $row['id'] === (int) $keepUserId) {
                        continue;
                    }
                    try {
                        DB::table($table)->insert($row);
                        $restored++;
                    } catch (\Throwable $e) {
                        $copy = $row;
                        unset($copy['id']);
                        try {
                            DB::table($table)->insert($copy);
                            $restored++;
                        } catch (\Throwable $e2) {
                            $errors[] = $table.': '.$e2->getMessage();
                        }
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return ['ok' => false, 'message' => $e->getMessage(), 'errors' => $errors];
        }

        return [
            'ok' => true,
            'message' => "بازیابی انجام شد. ردیف‌های درج‌شده تقریبی: {$restored}",
            'errors' => array_slice($errors, 0, 30),
        ];
    }


    /**
     * Full archive: encrypted/plain JSON payload + files under storage/app/public (attachments).
     */
    public function exportZip(bool $encrypt = true, string $type = 'manual', ?int $userId = null, ?array $sectionKeys = null, bool $includeFiles = true): array
    {
        $base = $this->export($encrypt, $type, $userId, $sectionKeys);
        if (!$includeFiles) {
            return $base;
        }

        if (!class_exists(\ZipArchive::class)) {
            return $base + [
                'zip_skipped' => true,
                'zip_error' => 'ext-zip not installed',
                'message' => 'بک‌آپ داده ذخیره شد. برای ZIP+فایل‌ها افزونه php-zip لازم است.',
            ];
        }

        $zipName = preg_replace('/\.(enc|json)$/', '', $base['filename']).'-with-files.zip';
        $zipRel = 'backups/'.$zipName;
        $zipFull = storage_path('app/'.$zipRel);

        if (!class_exists(\ZipArchive::class)) {
            return ['ok' => false, 'message' => 'افزونه php-zip روی سرور نصب نیست. بک‌آپ JSON همچنان قابل استفاده است.'];
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipFull, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return $base + ['zip_error' => 'cannot create zip', 'zip_skipped' => true];
        }

        $zip->addFile($base['full_path'], 'data/'.$base['filename']);
        $zip->setArchiveComment('RFQ-Core backup archive v3');

        $publicRoot = storage_path('app/public');
        if (is_dir($publicRoot)) {
            $this->addDirToZip($zip, $publicRoot, 'files/public');
        }
        $localRoot = storage_path('app');
        foreach (['attachments', 'documents', 'uploads'] as $subdir) {
            $d = $localRoot.'/'.$subdir;
            if (is_dir($d)) {
                $this->addDirToZip($zip, $d, 'files/'.$subdir);
            }
        }

        $zip->close();
        $size = @filesize($zipFull) ?: 0;
        $this->logJob($zipName, $zipRel, $type.'-zip', $encrypt, $size, $userId, ($base['scope'] ?? 'full').'+files');

        try {
            app(CloudBackupService::class)->pushIfEnabled($zipFull, $zipName);
        } catch (\Throwable $e) {
        }

        return [
            'filename' => $zipName,
            'path' => $zipRel,
            'full_path' => $zipFull,
            'size' => $size,
            'scope' => ($base['scope'] ?? 'full').'+files',
            'data_file' => $base['filename'],
        ];
    }

    protected function addDirToZip(\ZipArchive $zip, string $dir, string $prefix): void
    {
        $dir = rtrim($dir, '/');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            $local = $prefix.'/'.ltrim(str_replace($dir, '', $path), '/');
            if ($file->isDir()) {
                $zip->addEmptyDir($local);
            } else {
                $zip->addFile($path, $local);
            }
        }
    }

    /**
     * Restore from .zip (data/* + files/*) or legacy .json/.enc
     */
    public function restoreAny(string $absolutePath, string $mode = 'full', ?int $keepUserId = null, ?array $onlySections = null, bool $restoreFiles = true): array
    {
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if ($ext === 'zip') {
            $tmp = storage_path('app/backups/restore-unpack-'.uniqid());
            @mkdir($tmp, 0750, true);
            if (!class_exists(\ZipArchive::class)) {
                return ['ok' => false, 'message' => 'افزونه php-zip روی سرور نصب نیست. بک‌آپ JSON همچنان قابل استفاده است.'];
            }
            $zip = new \ZipArchive();
            if ($zip->open($absolutePath) !== true) {
                return ['ok' => false, 'message' => 'باز کردن zip ممکن نیست'];
            }
            $zip->extractTo($tmp);
            $zip->close();

            $dataFile = null;
            foreach (['data', ''] as $sub) {
                $search = $sub ? $tmp.'/'.$sub : $tmp;
                if (!is_dir($search)) continue;
                foreach (scandir($search) as $f) {
                    if (preg_match('/\.(json|enc|bak)$/i', $f)) {
                        $dataFile = $search.'/'.$f;
                        break 2;
                    }
                }
            }
            // recursive find
            if (!$dataFile) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tmp));
                foreach ($it as $file) {
                    if ($file->isFile() && preg_match('/\.(json|enc|bak)$/i', $file->getFilename())) {
                        $dataFile = $file->getPathname();
                        break;
                    }
                }
            }
            if (!$dataFile) {
                return ['ok' => false, 'message' => 'داخل zip فایل داده پیدا نشد'];
            }
            $result = $this->restore($dataFile, $mode === 'sections' ? 'full' : $mode, $keepUserId, $onlySections);

            if ($restoreFiles && is_dir($tmp.'/files')) {
                $copied = $this->copyTree($tmp.'/files/public', storage_path('app/public'));
                foreach (['attachments', 'documents', 'uploads'] as $subdir) {
                    if (is_dir($tmp.'/files/'.$subdir)) {
                        $copied += $this->copyTree($tmp.'/files/'.$subdir, storage_path('app/'.$subdir));
                    }
                }
                $result['files_copied'] = $copied;
                $result['message'] = ($result['message'] ?? '')." | فایل‌های پیوست کپی‌شده: {$copied}";
            }

            // cleanup
            $this->rrmdir($tmp);
            return $result;
        }

        return $this->restore($absolutePath, $mode === 'sections' ? 'full' : $mode, $keepUserId, $onlySections);
    }

    protected function copyTree(string $src, string $dst): int
    {
        if (!is_dir($src)) {
            return 0;
        }
        @mkdir($dst, 0750, true);
        $count = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $target = $dst.'/'.substr($item->getPathname(), strlen($src) + 1);
            if ($item->isDir()) {
                @mkdir($target, 0750, true);
            } else {
                @mkdir(dirname($target), 0750, true);
                if (@copy($item->getPathname(), $target)) {
                    $count++;
                }
            }
        }
        return $count;
    }

    protected function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }


    /**
     * ریست کارخانه‌ای: حذف داده‌های عملیاتی؛ نگه‌داشتن کاربر ادمین فعلی + جداول نقش/مجوز.
     * جداول تنظیمات پایه (modules, industries در صورت نیاز seed مجدد) حفظ می‌شوند مگر $wipeSettings=true.
     */
    public function factoryReset(int $keepUserId, bool $wipeSettings = false): array
    {
        $driver = \Illuminate\Support\Facades\DB::getDriverName();
        $errors = [];

        // جداولی که هرگز truncate کامل نمی‌شوند
        $never = ['migrations', 'backup_jobs'];

        // نقش و مجوز Spatie را نگه می‌داریم
        $preserve = [
            'roles', 'permissions', 'role_has_permissions', 'model_has_roles', 'model_has_permissions',
            'users', // handled specially
        ];

        $sections = $this->sections();
        $tables = [];
        foreach ($sections as $key => $sec) {
            if (!$wipeSettings && $key === 'settings') {
                continue;
            }
            foreach ($sec['tables'] as $tbl) {
                $tables[] = $tbl;
            }
        }
        // جداول عملیاتی اضافه که ممکن است در sections نباشند
        foreach ([
            'cases','case_activities','case_activity_reactions','case_attachments','case_assignees',
            'case_payments','case_tag','contacts','contact_confidential_notes','contact_tag',
            'organizations','organization_tag','tasks','task_assignees','documents','document_lines',
            'emails','email_attachments','notifications','receivables','receivable_payments',
            'deliveries','custom_field_values','custom_reports','saved_views','tags',
            'pipeline_stages','pipeline_transitions','number_sequences','templates','template_versions',
            'modules','industries','automations','automation_logs','password_reset_tokens','sessions',
            'personal_access_tokens','failed_jobs','jobs','job_batches','cache','cache_locks',
        ] as $extra) {
            $tables[] = $extra;
        }
        $tables = array_values(array_unique($tables));

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();
            if ($driver === 'mysql') {
                \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }

            foreach ($tables as $t) {
                if (in_array($t, $never, true) || in_array($t, $preserve, true)) {
                    continue;
                }
                if (!\Illuminate\Support\Facades\Schema::hasTable($t)) {
                    continue;
                }
                try {
                    \Illuminate\Support\Facades\DB::table($t)->truncate();
                } catch (\Throwable $e) {
                    try {
                        \Illuminate\Support\Facades\DB::table($t)->delete();
                    } catch (\Throwable $e2) {
                        $errors[] = $t.': '.$e2->getMessage();
                    }
                }
            }

            // کاربران: فقط keepUserId بماند
            if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
                \Illuminate\Support\Facades\DB::table('users')->where('id', '!=', $keepUserId)->delete();
            }
            // model_has_roles: نقش‌های کاربران حذف‌شده پاک
            if (\Illuminate\Support\Facades\Schema::hasTable('model_has_roles')) {
                \Illuminate\Support\Facades\DB::table('model_has_roles')
                    ->where('model_type', 'like', '%User%')
                    ->where('model_id', '!=', $keepUserId)
                    ->delete();
            }

            if ($driver === 'mysql') {
                \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
            \Illuminate\Support\Facades\DB::commit();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return ['ok' => false, 'message' => $e->getMessage(), 'errors' => $errors];
        }

        return [
            'ok' => true,
            'message' => 'سیستم به حالت اولیه برگشت. فقط حساب ادمین فعلی باقی ماند.',
            'errors' => $errors,
        ];
    }

    /**
     * حذف کامل یک بخش منطقی (مثلاً contacts)
     */
    public function wipeSection(string $sectionKey): array
    {
        $sections = $this->sections();
        if (!isset($sections[$sectionKey])) {
            return ['ok' => false, 'message' => 'بخش نامعتبر است.'];
        }
        $driver = \Illuminate\Support\Facades\DB::getDriverName();
        $errors = [];
        $tables = array_reverse($sections[$sectionKey]['tables']); // children first ideally
        try {
            \Illuminate\Support\Facades\DB::beginTransaction();
            if ($driver === 'mysql') {
                \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }
            foreach ($tables as $t) {
                if (!\Illuminate\Support\Facades\Schema::hasTable($t)) continue;
                try {
                    \Illuminate\Support\Facades\DB::table($t)->truncate();
                } catch (\Throwable $e) {
                    try { \Illuminate\Support\Facades\DB::table($t)->delete(); }
                    catch (\Throwable $e2) { $errors[] = $t.': '.$e2->getMessage(); }
                }
            }
            // pivot tables for contacts/orgs
            if ($sectionKey === 'contacts') {
                foreach (['contact_tag', 'contact_confidential_notes'] as $p) {
                    if (\Illuminate\Support\Facades\Schema::hasTable($p)) {
                        try { \Illuminate\Support\Facades\DB::table($p)->delete(); } catch (\Throwable $e) {}
                    }
                }
                // nullify contact_id on cases if exists
                if (\Illuminate\Support\Facades\Schema::hasTable('cases') && \Illuminate\Support\Facades\Schema::hasColumn('cases', 'contact_id')) {
                    \Illuminate\Support\Facades\DB::table('cases')->update(['contact_id' => null]);
                }
            }
            if ($driver === 'mysql') {
                \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
            \Illuminate\Support\Facades\DB::commit();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return ['ok' => false, 'message' => $e->getMessage(), 'errors' => $errors];
        }
        return ['ok' => true, 'message' => 'بخش «' . ($sections[$sectionKey]['label'] ?? $sectionKey) . '» پاک شد.', 'errors' => $errors];
    }

    public function pruneOldBackups(): int
    {
        $days = (int) AppSetting::get('backup_retention_days', '14');
        if ($days < 1) {
            return 0;
        }
        $cutoff = now()->subDays($days);
        $deleted = 0;
        $files = Storage::disk('local')->files('backups');
        foreach ($files as $file) {
            $last = Storage::disk('local')->lastModified($file);
            if ($last && $last < $cutoff->timestamp) {
                Storage::disk('local')->delete($file);
                $deleted++;
                if (Schema::hasTable('backup_jobs')) {
                    DB::table('backup_jobs')->where('path', $file)->orWhere('filename', basename($file))->delete();
                }
            }
        }
        return $deleted;
    }

    protected function logJob(string $filename, string $relative, string $type, bool $encrypt, int $size, ?int $userId, string $scope): void
    {
        if (!Schema::hasTable('backup_jobs')) {
            return;
        }
        $row = [
            'filename' => $filename,
            'path' => $relative,
            'type' => $type,
            'encrypted' => $encrypt,
            'size_bytes' => $size,
            'status' => 'done',
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('backup_jobs', 'scope')) {
            $row['scope'] = $scope;
        }
        DB::table('backup_jobs')->insert($row);
    }

    public function scheduleEnabled(): bool
    {
        return AppSetting::get('backup_schedule_enabled', '0') === '1';
    }

    public function scheduleFrequency(): string
    {
        return AppSetting::get('backup_schedule_frequency', 'daily');
    }
}
