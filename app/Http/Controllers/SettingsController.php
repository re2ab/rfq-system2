<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SettingsController extends Controller
{
    public function index()
    {
        return redirect()->route('settings.appearance');
    }

    public function modules()
    {
        $modules = DB::table('modules')->orderBy('id')->get();
        return view('settings.modules', compact('modules'));
    }

    public function toggleModule(Request $request, $id)
    {
        $module = DB::table('modules')->where('id', $id)->first();
        if (!$module || $module->is_core) {
            return back()->withErrors(['module' => 'ماژول هسته قابل غیرفعال‌سازی نیست.']);
        }

        \App\Support\ModuleGate::forget();
        DB::table('modules')->where('id', $id)->update([
            'is_enabled' => !$module->is_enabled,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'وضعیت ماژول تغییر کرد.');
    }

    public function users(Request $request)
    {
        $users = User::with('roles')->orderBy('name')->get();
        $roles = Role::orderBy('name')->get();
        $rolesList = $roles->pluck('name')->all();
        $editUser = null;
        if ($request->filled('edit')) {
            $editUser = User::with('roles')->find($request->get('edit'));
        }
        return view('settings.users', compact('users', 'roles', 'rolesList', 'editUser'));
    }

    public function storeUser(Request $request)
    {
        // Only super admin can create managers; managers can create experts
        $actor = auth()->user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|string|exists:roles,name',
        ]);

        $role = $data['role'];

        // Hierarchy rules
        if (in_array($role, ['admin', 'technical_manager', 'financial_manager']) && !$actor->hasRole('admin')) {
            return back()->withErrors(['role' => 'فقط ادمین کل می‌تواند مدیر ایجاد کند.']);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return back()->with('success', 'کاربر ایجاد شد.');
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:6',
            'role' => 'nullable|string|exists:roles,name',
        ]);
        $user->name = $data['name'];
        $user->email = $data['email'];
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();
        if (!empty($data['role']) && method_exists($user, 'syncRoles')) {
            $user->syncRoles([$data['role']]);
        }
        return redirect()->route('settings.users')->with('success', 'کاربر به‌روزرسانی شد.');
    }

    public function destroyUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'نمی‌توانید خودتان را حذف کنید.']);
        }
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            $adminCount = User::role('admin')->count();
            if ($adminCount <= 1) {
                return back()->withErrors(['user' => 'حداقل یک ادمین باید در سیستم بماند.']);
            }
        }
        $user->delete();
        return back()->with('success', 'کاربر حذف شد.');
    }

    public function templates()
    {
        // این صفحه از این پس فقط قالب ایمیل را مدیریت می‌کند — قالب‌های سند
        // (technical_proposal/financial_proposal/invoice) قدیمی هستند و از این
        // بخش حذف شده‌اند (خودشان در دیتابیس دست‌نخورده می‌مانند، فقط دیگر از
        // این UI قابل مدیریت نیستند).
        $templates = DB::table('templates')->where('type', 'email')->orderByDesc('is_default')->orderBy('name')->get();
        return view('settings.templates', compact('templates'));
    }

    public function storeTemplate(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'header' => 'nullable|string',
            'body' => 'nullable|string',
            'footer' => 'nullable|string',
            'account_type' => 'nullable|in:internal,external',
            'is_default' => 'boolean',
        ]);
        $data['type'] = 'email';

        DB::table('templates')->insert(array_merge($data, [
            'is_default' => $request->boolean('is_default'),
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return back()->with('success', 'قالب ذخیره شد.');
    }

    public function appearance()
    {
        $timezones = [
            'Asia/Tehran' => 'تهران (ایران) — Asia/Tehran',
            'Asia/Dubai' => 'دبی — Asia/Dubai',
            'Asia/Kabul' => 'کابل — Asia/Kabul',
            'Asia/Baghdad' => 'بغداد — Asia/Baghdad',
            'Europe/Istanbul' => 'استانبول — Europe/Istanbul',
            'Europe/London' => 'لندن — Europe/London',
            'Europe/Berlin' => 'برلین — Europe/Berlin',
            'UTC' => 'UTC',
            'America/New_York' => 'نیویورک — America/New_York',
        ];
        return view('settings.appearance', [
            'company_name' => \App\Models\AppSetting::get('company_name', 'شرکت'),
            'company_logo' => \App\Models\AppSetting::get('company_logo', ''),
            'system_subtitle' => \App\Models\AppSetting::get('system_subtitle', 'سیستم مدیریت درخواست خرید'),
            'theme' => \App\Models\AppSetting::get('theme', 'light'),
            'primary_color' => \App\Models\AppSetting::get('primary_color', '#0f766e'),
            'app_timezone' => \App\Models\AppSetting::get('app_timezone', config('app.timezone', 'Asia/Tehran')),
            'timezones' => $timezones,
        ]);
    }

    public function saveAppearance(\Illuminate\Http\Request $request)
    {
        $allowedTz = [
            'Asia/Tehran','Asia/Dubai','Asia/Kabul','Asia/Baghdad',
            'Europe/Istanbul','Europe/London','Europe/Berlin','UTC','America/New_York',
        ];
        $data = $request->validate([
            'theme' => 'required|in:light,dark',
            'primary_color' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:150',
            'app_timezone' => 'nullable|string|max:64',
        ]);
        \App\Models\AppSetting::set('theme', $data['theme']);
        \App\Models\AppSetting::set('primary_color', $data['primary_color'] ?? '#0f766e');
        \App\Models\AppSetting::set('company_name', $request->input('company_name', \App\Models\AppSetting::get('company_name', 'شرکت')));
        if ($request->has('system_subtitle')) {
            \App\Models\AppSetting::set('system_subtitle', $request->input('system_subtitle'));
        }
        if ($request->hasFile('company_logo')) {
            $path = $request->file('company_logo')->store('branding', 'public');
            \App\Models\AppSetting::set('company_logo', $path);
        }
        $tz = $data['app_timezone'] ?? 'Asia/Tehran';
        if (in_array($tz, $allowedTz, true) || in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            \App\Models\AppSetting::set('app_timezone', $tz);
            config(['app.timezone' => $tz]);
            date_default_timezone_set($tz);
        }
        return back()->with('success', 'ظاهر و منطقه زمانی ذخیره شد.');
    }

    public function previewTemplate(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'header' => 'nullable|string',
            'body' => 'nullable|string',
            'footer' => 'nullable|string',
            'case_id' => 'nullable|exists:cases,id',
        ]);
        $case = null;
        if (!empty($data['case_id'])) {
            $case = \App\Models\CaseModel::with(['customer', 'expert'])->find($data['case_id']);
        }
        $vars = [
            'case_number' => $case?->case_number ?? 'CASE-000001',
            'title' => $case?->title ?? 'Sample Title',
            'customer_name' => $case?->customer?->name ?? 'Customer Co',
            'expert_name' => $case?->expert?->name ?? 'Expert',
            'currency' => $case?->currency ?? 'EUR',
            'incoterm' => $case?->incoterm ?? 'CPT',
            'company_name' => \App\Models\AppSetting::get('company_name', 'Company'),
            'today' => now()->format('Y-m-d'),
        ];
        $renderer = app(\App\Services\TemplateRenderService::class);
        $html = app(\App\Services\PdfTemplateService::class)->toHtml(
            $data['header'] ?? '',
            $data['body'] ?? '',
            $data['footer'] ?? '',
            $vars
        );
        return response($html);
    }

    public function backupIndex()
    {
        $jobs = \Illuminate\Support\Facades\Schema::hasTable('backup_jobs')
            ? \Illuminate\Support\Facades\DB::table('backup_jobs')->orderByDesc('id')->limit(50)->get()
            : collect();
        $svc = app(\App\Services\BackupService::class);
        return view('settings.backup', [
            'jobs' => $jobs,
            'sections' => $svc->sections(),
            'schedule_enabled' => \App\Models\AppSetting::get('backup_schedule_enabled', '0'),
            'schedule_frequency' => \App\Models\AppSetting::get('backup_schedule_frequency', 'daily'),
            'encrypt_default' => \App\Models\AppSetting::get('backup_encrypt', '1'),
            'retention_days' => \App\Models\AppSetting::get('backup_retention_days', '14'),
            'cloud_driver' => \App\Models\AppSetting::get('backup_cloud_driver', 'none'),
            'cloud_path' => \App\Models\AppSetting::get('backup_cloud_path', ''),
            'cloud_webhook_url' => \App\Models\AppSetting::get('backup_cloud_webhook_url', ''),
            'gdrive_folder' => \App\Models\AppSetting::get('backup_gdrive_folder_id', ''),
            'box_folder' => \App\Models\AppSetting::get('backup_box_folder_id', '0'),
            'has_gdrive_token' => (bool) \App\Models\AppSetting::get('backup_gdrive_access_token', ''),
            'has_gdrive_refresh' => (bool) \App\Models\AppSetting::get('backup_gdrive_refresh_token', ''),
            'has_box_token' => (bool) \App\Models\AppSetting::get('backup_box_access_token', ''),
            'gdrive_client_id' => \App\Models\AppSetting::get('backup_gdrive_client_id', ''),
            // M9ب: کلید Google API جدا از OAuth client — فقط برای بارگذاری ویجت
            // Picker (انتخاب فایل از Drive برای Import سند) لازم است.
            'gdrive_api_key' => \App\Models\AppSetting::get('backup_gdrive_api_key', ''),
        ]);
    }

    public function backupRun(\Illuminate\Http\Request $request)
    {
        $encrypt = $request->boolean('encrypt', true);
        $sections = $request->input('sections', ['full']);
        if (!is_array($sections)) {
            $sections = ['full'];
        }
        $svc = app(\App\Services\BackupService::class);
        $keys = in_array('full', $sections, true) ? null : $sections;
        $includeFiles = $request->boolean('include_files', true);
        $result = $includeFiles
            ? $svc->exportZip($encrypt, 'manual', auth()->id(), $keys, true)
            : $svc->export($encrypt, 'manual', auth()->id(), $keys);
        return response()->download($result['full_path'], $result['filename']);
    }

    public function backupScheduleSave(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'frequency' => 'required|in:daily,weekly',
            'retention_days' => 'nullable|integer|min:1|max:365',
        ]);
        \App\Models\AppSetting::set('backup_schedule_enabled', $request->boolean('enabled') ? '1' : '0');
        \App\Models\AppSetting::set('backup_schedule_frequency', $data['frequency']);
        \App\Models\AppSetting::set('backup_encrypt', $request->boolean('encrypt') ? '1' : '0');
        if (isset($data['retention_days'])) {
            \App\Models\AppSetting::set('backup_retention_days', (string) $data['retention_days']);
        }
        return back()->with('success', 'زمان‌بندی و نگهداری پشتیبان ذخیره شد.');
    }

    public function backupCloudSave(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'driver' => 'required|in:none,local_mirror,webhook,google_drive,box',
            'gdrive_client_id' => 'nullable|string|max:300',
            'gdrive_client_secret' => 'nullable|string|max:300',
            'cloud_path' => 'nullable|string|max:500',
            'webhook_url' => 'nullable|url|max:500',
            'webhook_token' => 'nullable|string|max:500',
            'gdrive_token' => 'nullable|string|max:2000',
            'gdrive_folder' => 'nullable|string|max:200',
            'gdrive_api_key' => 'nullable|string|max:300',
            'box_token' => 'nullable|string|max:2000',
            'box_folder' => 'nullable|string|max:200',
        ]);
        \App\Models\AppSetting::set('backup_cloud_driver', $data['driver']);
        if (array_key_exists('cloud_path', $data)) {
            \App\Models\AppSetting::set('backup_cloud_path', $data['cloud_path'] ?? '');
        }
        if (!empty($data['webhook_url'])) {
            \App\Models\AppSetting::set('backup_cloud_webhook_url', $data['webhook_url']);
        }
        if (!empty($data['webhook_token'])) {
            \App\Models\AppSetting::set('backup_cloud_webhook_token', $data['webhook_token']);
        }
        if (!empty($data['gdrive_token'])) {
            \App\Models\AppSetting::set('backup_gdrive_access_token', $data['gdrive_token']);
        }
        if (!empty($data['gdrive_client_id'])) {
            \App\Models\AppSetting::set('backup_gdrive_client_id', $data['gdrive_client_id']);
        }
        if (!empty($data['gdrive_client_secret'])) {
            \App\Models\AppSetting::set('backup_gdrive_client_secret', $data['gdrive_client_secret']);
        }
        if (isset($data['gdrive_folder'])) {
            \App\Models\AppSetting::set('backup_gdrive_folder_id', $data['gdrive_folder'] ?? '');
        }
        if (!empty($data['gdrive_api_key'])) {
            \App\Models\AppSetting::set('backup_gdrive_api_key', $data['gdrive_api_key']);
        }
        if (!empty($data['box_token'])) {
            \App\Models\AppSetting::set('backup_box_access_token', $data['box_token']);
        }
        if (isset($data['box_folder'])) {
            \App\Models\AppSetting::set('backup_box_folder_id', $data['box_folder'] ?? '0');
        }
        return back()->with('success', 'تنظیمات ذخیره ابری بک‌آپ ذخیره شد.');
    }

    public function backupRestore(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:512000',
            'mode' => 'required|in:settings_only,full,sections',
            'restore_sections' => 'nullable|array',
        ]);
        $path = $request->file('file')->storeAs('backups/restore', 'upload-'.time().'.bak', 'local');
        $full = storage_path('app/'.$path);
        $svc = app(\App\Services\BackupService::class);
        $only = $request->input('mode') === 'sections' ? ($request->input('restore_sections') ?: []) : null;
        $result = $svc->restoreAny(
            $full,
            $request->input('mode'),
            auth()->id(),
            $only,
            $request->boolean('restore_files', true)
        );
        if (!$result['ok']) {
            return back()->withErrors(['file' => $result['message']]);
        }
        $msg = $result['message'];
        if (!empty($result['errors'])) {
            $msg .= ' | هشدارها: '.count($result['errors']);
        }
        return back()->with('success', $msg);
    }

    public function dataHubImportContacts(\Illuminate\Http\Request $request)
    {
        return app(\App\Http\Controllers\ContactController::class)->import($request);
    }

    public function dataHubImportTrello(\Illuminate\Http\Request $request)
    {
        $request->validate(['file' => 'required|file|max:51200']);
        $json = file_get_contents($request->file('file')->getRealPath());
        $result = app(\App\Services\TrelloImportService::class)->importFromJson($json, auth()->id());
        if (!$result['ok']) {
            return back()->withErrors(['file' => $result['message']]);
        }
        return back()->with('success', $result['message']);
    }

    /** M9پ: Import گروهی اسناد قدیمی (Migration) — CSV با ستون‌های شماره پرونده/نوع سند/شماره سند. */
    public function dataHubImportDocuments(\Illuminate\Http\Request $request)
    {
        $request->validate(['file' => 'required|file|max:51200']);
        $raw = file_get_contents($request->file('file')->getRealPath());
        if ($raw === false || $raw === '') {
            return back()->withErrors(['file' => 'خواندن فایل ممکن نیست یا فایل خالی است.']);
        }
        $result = app(\App\Services\Documents\DocumentLegacyImportService::class)->importFromCsv($raw, auth()->id());
        if (!$result['ok']) {
            return back()->withErrors(['file' => $result['message']]);
        }
        return back()->with('success', $result['message']);
    }

    public function templateEdit($id)
    {
        $template = \Illuminate\Support\Facades\DB::table('templates')->where('id', $id)->first();
        // این صفحه از این پس فقط قالب ایمیل را مدیریت می‌کند.
        abort_unless($template && $template->type === 'email', 404);
        $versions = \Illuminate\Support\Facades\Schema::hasTable('template_versions')
            ? \Illuminate\Support\Facades\DB::table('template_versions')->where('template_id', $id)->orderByDesc('version_number')->get()
            : collect();
        $placeholders = \App\Services\PlaceholderLibrary::all();
        return view('settings.template_edit', compact('template', 'versions', 'placeholders'));
    }

    public function templateUpdate(\Illuminate\Http\Request $request, $id)
    {
        $template = \Illuminate\Support\Facades\DB::table('templates')->where('id', $id)->first();
        abort_unless($template && $template->type === 'email', 404);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'header' => 'nullable|string',
            'body' => 'nullable|string',
            'footer' => 'nullable|string',
            'is_default' => 'boolean',
            'change_note' => 'nullable|string|max:500',
        ]);
        $newVersion = ((int)($template->version ?? 1)) + 1;
        if (\Illuminate\Support\Facades\Schema::hasTable('template_versions')) {
            \Illuminate\Support\Facades\DB::table('template_versions')->insert([
                'template_id' => $id,
                'version_number' => (int)($template->version ?? 1),
                'name' => $template->name,
                'header' => $template->header,
                'body' => $template->body,
                'footer' => $template->footer,
                'created_by' => auth()->id(),
                'change_note' => $data['change_note'] ?? 'auto snapshot before update',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        if ($request->boolean('is_default')) {
            \Illuminate\Support\Facades\DB::table('templates')->where('type', $template->type)->update(['is_default' => 0]);
        }
        \Illuminate\Support\Facades\DB::table('templates')->where('id', $id)->update([
            'name' => $data['name'],
            'header' => $data['header'] ?? '',
            'body' => $data['body'] ?? '',
            'footer' => $data['footer'] ?? '',
            'is_default' => $request->boolean('is_default'),
            'version' => $newVersion,
            'updated_at' => now(),
        ]);
        return back()->with('success', 'قالب ذخیره و نسخه جدید ثبت شد (v'.$newVersion.').');
    }

    public function templateRestoreVersion($templateId, $versionId)
    {
        $ver = \Illuminate\Support\Facades\DB::table('template_versions')->where('id', $versionId)->where('template_id', $templateId)->first();
        abort_unless($ver, 404);
        $template = \Illuminate\Support\Facades\DB::table('templates')->where('id', $templateId)->first();
        $newVersion = ((int)($template->version ?? 1)) + 1;
        \Illuminate\Support\Facades\DB::table('template_versions')->insert([
            'template_id' => $templateId,
            'version_number' => (int)($template->version ?? 1),
            'name' => $template->name,
            'header' => $template->header,
            'body' => $template->body,
            'footer' => $template->footer,
            'created_by' => auth()->id(),
            'change_note' => 'snapshot before restore version '.$ver->version_number,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('templates')->where('id', $templateId)->update([
            'header' => $ver->header,
            'body' => $ver->body,
            'footer' => $ver->footer,
            'name' => $ver->name ?: $template->name,
            'version' => $newVersion,
            'updated_at' => now(),
        ]);
        return back()->with('success', 'نسخه '.$ver->version_number.' بازیابی شد.');
    }

    public function numbering()
    {
        $labels = \App\Services\NumberGeneratorService::typeLabels();
        $defaults = \App\Services\NumberGeneratorService::DEFAULTS;
        $rows = [];
        foreach ($labels as $type => $label) {
            $row = DB::table('number_sequences')->where('type', $type)->first();
            $def = $defaults[$type] ?? ['prefix' => strtoupper($type), 'pad' => 6];
            if (!$row) {
                DB::table('number_sequences')->insert([
                    'type' => $type,
                    'prefix' => $def['prefix'],
                    'pad_length' => $def['pad'],
                    'start_number' => 1,
                    'last_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $row = DB::table('number_sequences')->where('type', $type)->first();
            }
            $prefix = $row->prefix ?: $def['prefix'];
            $pad = (int) ($row->pad_length ?? $def['pad']);
            $last = (int) ($row->last_number ?? 0);
            $start = (int) ($row->start_number ?? 1);
            $gen = app(\App\Services\NumberGeneratorService::class);
            $rows[] = (object) [
                'type' => $type,
                'label' => $label,
                'prefix' => $prefix,
                'pad_length' => $pad,
                'start_number' => $start,
                'last_number' => $last,
                'preview_next' => $gen->preview($prefix, $pad, max($last + 1, $start)),
                'preview_last' => $last > 0 ? $gen->preview($prefix, $pad, $last) : '—',
            ];
        }
        return view('settings.numbering', compact('rows'));
    }

    public function saveNumbering(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'sequences' => 'required|array',
            'sequences.*.type' => 'required|string|in:case,technical_proposal,financial_proposal,invoice',
            'sequences.*.prefix' => 'required|string|max:20',
            'sequences.*.pad_length' => 'required|integer|min:1|max:12',
            'sequences.*.start_number' => 'required|integer|min:0',
            'sequences.*.last_number' => 'required|integer|min:0',
        ]);

        foreach ($data['sequences'] as $seq) {
            $type = $seq['type'];
            $prefix = preg_replace('/\s+/', '', $seq['prefix']);
            $prefix = $prefix !== '' ? $prefix : 'DOC';
            DB::table('number_sequences')->updateOrInsert(
                ['type' => $type],
                [
                    'prefix' => $prefix,
                    'pad_length' => (int) $seq['pad_length'],
                    'start_number' => (int) $seq['start_number'],
                    'last_number' => (int) $seq['last_number'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        return back()->with('success', 'تنظیمات شماره‌گذاری ذخیره شد.');
    }

    /**
     * M39 (رفعِ باگ، گزارشِ کاربر: «UNIQUE constraint failed: documents.number_base»):
     * تا قبل از این هاتفیکس، حذفِ یک سند (DocumentController::destroy)
     * وقتی شماره‌اش را برای سندِ بعدی «آزاد» می‌کرد (reclaimIfLast)، خودِ
     * ردیفِ سند — چون Document مدلِ SoftDeletes است — با همان
     * number_base/document_number قدیمی در جدول باقی می‌ماند (فقط
     * deleted_at ست می‌شود). قیدِ UNIQUE هیچ استثنایی برای ردیف‌های
     * soft-deleted قائل نیست؛ پس اولین سندی که همان سریالِ آزادشده را
     * دوباره می‌گرفت، دقیقاً به همین خطا می‌خورد. اصلاحِ اصلی (سمتِ کد) در
     * destroy() اعمال شد — از این پس دیگر تکرار نمی‌شود. این متد فقط
     * داده‌های قدیمیِ از قبل خراب‌شده (پیش از این هاتفیکس) را پاک‌سازی
     * می‌کند: روی هر سندِ soft-deleted ای که هنوز number_base دارد،
     * همان‌طور که خودِ destroy() از این پس انجام می‌دهد، این دو مقدار را
     * آزاد می‌کند — بدونِ اینکه چیزی از سندهای زنده (غیرِ soft-deleted)
     * دست بخورد.
     */
    public function cleanupOrphanedNumbers()
    {
        $cleaned = 0;
        \Illuminate\Support\Facades\DB::transaction(function () use (&$cleaned) {
            \App\Models\Document::onlyTrashed()
                ->whereNotNull('number_base')
                ->each(function (\App\Models\Document $doc) use (&$cleaned) {
                    $doc->forceFill([
                        'number_base' => null,
                        'document_number' => str_starts_with((string) $doc->document_number, 'DELETED-')
                            ? $doc->document_number
                            : 'DELETED-'.$doc->id,
                    ])->save();
                    $cleaned++;
                });
        });

        return back()->with('success', $cleaned > 0
            ? "شماره‌ی {$cleaned} سندِ حذف‌شده‌ی قدیمی آزاد شد — از این پس دیگر با سندهای تازه تداخل نمی‌کنند."
            : 'هیچ سندِ حذف‌شده‌ای با شماره‌ی معلق پیدا نشد — چیزی برای پاک‌سازی نبود.');
    }

    /**
     * ریست کارخانه‌ای — فقط ادمین؛ نیاز به تایید متنی RESET
     */
    public function factoryReset(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'confirm' => 'required|in:RESET',
            'password' => 'required|string',
        ]);
        $user = $request->user();
        if (!$user || !$user->can('settings.manage')) {
            abort(403);
        }
        if (!\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'رمز عبور اشتباه است.']);
        }
        $result = app(\App\Services\BackupService::class)->factoryReset((int) $user->id, $request->boolean('wipe_settings'));
        if (!$result['ok']) {
            return back()->withErrors(['confirm' => $result['message']]);
        }
        return redirect()->route('settings.backup')->with('success', $result['message']);
    }

    /**
     * حذف کامل یک بخش داده (مثلاً مخاطبان)
     */
    public function wipeSection(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'section' => 'required|string',
            'confirm' => 'required|in:DELETE',
            'password' => 'required|string',
        ]);
        $user = $request->user();
        if (!$user || !$user->can('settings.manage')) {
            abort(403);
        }
        if (!\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'رمز عبور اشتباه است.']);
        }
        $allowed = ['contacts', 'organizations', 'cases', 'tasks', 'documents', 'emails'];
        $section = $request->input('section');
        if (!in_array($section, $allowed, true)) {
            return back()->withErrors(['section' => 'بخش مجاز نیست.']);
        }
        $result = app(\App\Services\BackupService::class)->wipeSection($section);
        if (!$result['ok']) {
            return back()->withErrors(['section' => $result['message']]);
        }
        return back()->with('success', $result['message']);
    }

}
