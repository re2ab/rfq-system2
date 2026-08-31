<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\Template;
use App\Models\TemplateField;
use App\Services\Documents\TemplateService;
use Illuminate\Http\Request;

/**
 * مدیریت قالب‌های Word/Excel واقعی (M2). فهرست/ساخت/نسخه‌بندی/پیش‌فرض —
 * دقیقاً نگاشت جدول API بخش ۹ سند معماری، به‌جز endpoint پیش‌نمایش که به
 * مرحله‌ی بعد (Document Explorer) موکول شده.
 */
class TemplateController extends Controller
{
    public function __construct(protected TemplateService $templates)
    {
    }

    public function index(Request $request)
    {
        $query = Template::with(['documentType', 'currentVersion'])->latest('id');

        if ($typeId = $request->get('document_type_id')) {
            $query->where('document_type_id', $typeId);
        }
        if ($fileType = $request->get('file_type')) {
            $query->where('file_type', $fileType);
        }

        $templates = $query->paginate(20)->withQueryString();
        $documentTypes = DocumentType::active();

        return view('templates.index', compact('templates', 'documentTypes'));
    }

    public function create()
    {
        $documentTypes = DocumentType::active();
        return view('templates.create', compact('documentTypes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'document_type_id' => 'required|exists:document_types,id',
            'code' => 'nullable|string|max:60',
            'file' => 'required|file',
        ]);

        try {
            $template = $this->templates->createFromUpload($data, $request->file('file'), auth()->id());
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        return redirect()->route('templates.show', $template)->with('success', 'قالب با موفقیت وارد شد.');
    }

    public function show(Template $template)
    {
        $template->load(['documentType', 'versions.fields', 'currentVersion.fields']);
        return view('templates.show', compact('template'));
    }

    public function storeVersion(Request $request, Template $template)
    {
        $data = $request->validate(['file' => 'required|file']);

        try {
            $this->templates->addVersion($template, $data['file'], auth()->id());
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('success', 'نسخه‌ی جدید قالب آپلود شد.');
    }

    /**
     * صفحه‌ی تنظیم Binding (بند ۶ سند معماری) — تا این‌جا فقط جای‌نگه‌دارها
     * کشف می‌شدند (TemplateService::discoverFields، source='auto'، binding=null)
     * ولی هیچ فرمی برای وصل‌کردنشان به مسیر داده وجود نداشت؛ بدون binding صریح
     * فقط قالب‌هایی کار می‌کردند که placeholder داخلشان دقیقاً هم‌نام مسیر داده
     * بود (مثلاً {{case.case_number}}). این متد همان صفحه‌ی مفقوده است — روی
     * فیلدهای *نسخه‌ی فعلی* قالب عمل می‌کند (نسخه‌های قدیمی‌تر تغییرناپذیرند).
     */
    public function updateFields(Request $request, Template $template)
    {
        $template->loadMissing('currentVersion');
        $version = $template->currentVersion;
        abort_unless($version, 404, 'این قالب نسخه‌ی فعلی ندارد.');

        $data = $request->validate([
            'fields' => 'required|array',
            'fields.*.id' => 'required|integer',
            'fields.*.label' => 'required|string|max:150',
            'fields.*.source' => 'required|in:auto,manual,line',
            'fields.*.binding' => 'nullable|string|max:255',
            'fields.*.data_type' => 'nullable|string|max:30',
            'fields.*.default_value' => 'nullable|string|max:500',
            'fields.*.is_required' => 'nullable|boolean',
        ]);

        // فقط فیلدهایی که واقعاً به همین نسخه تعلق دارند قابل‌ویرایش‌اند — جلوگیری
        // از دستکاری id فیلد قالب‌های دیگر از طریق فرم.
        $ownFieldIds = $version->fields()->pluck('id')->all();

        foreach ($data['fields'] as $row) {
            if (!in_array((int) $row['id'], $ownFieldIds, true)) {
                continue;
            }
            TemplateField::where('id', $row['id'])->update([
                'label' => $row['label'],
                'source' => $row['source'],
                'binding' => $row['binding'] !== '' ? ($row['binding'] ?? null) : null,
                'data_type' => $row['data_type'] ?: 'text',
                'default_value' => $row['default_value'] !== '' ? ($row['default_value'] ?? null) : null,
                'is_required' => (bool) ($row['is_required'] ?? false),
            ]);
        }

        return back()->with('success', 'اتصال داده‌های جای‌نگه‌دارها ذخیره شد.');
    }

    public function setDefault(Template $template)
    {
        $this->templates->setDefault($template);
        return back()->with('success', 'این قالب پیش‌فرض این نوع سند شد.');
    }

    public function activate(Request $request, Template $template)
    {
        $this->templates->activate($template, $request->boolean('active', true));
        return back()->with('success', 'وضعیت قالب به‌روزرسانی شد.');
    }

    public function destroy(Request $request, Template $template)
    {
        // فقط مدیر سیستم می‌تواند قالبی را که به سندهای واقعی وصل است force حذف
        // کند — کاربران دیگر همچنان با همان خطای محافظتی معمول مواجه می‌شوند.
        $isAdmin = $request->user() && method_exists($request->user(), 'hasRole') && $request->user()->hasRole('admin');
        $force = $isAdmin && $request->boolean('force');

        try {
            $this->templates->delete($template, $force);
        } catch (\Throwable $e) {
            return back()->withErrors(['template' => $e->getMessage()]);
        }

        return redirect()->route('templates.index')->with('success', 'قالب حذف شد.');
    }
}
