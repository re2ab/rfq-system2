<?php

namespace App\Http\Controllers;

use App\Services\CustomFieldService;

use App\Models\Contact;
use App\Models\ContactConfidentialNote;
use App\Models\Organization;
use App\Models\CaseModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $query = Contact::query()
            ->leftJoin('organizations', 'organizations.id', '=', 'contacts.organization_id')
            ->select('contacts.*', 'organizations.type as org_type', 'organizations.name as org_name')
            ->with('organization');

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('contacts.first_name', 'like', "%{$search}%")
                  ->orWhere('contacts.last_name', 'like', "%{$search}%")
                  ->orWhere('contacts.position', 'like', "%{$search}%")
                  ->orWhere('contacts.email', 'like', "%{$search}%")
                  ->orWhere('contacts.phone', 'like', "%{$search}%")
                  ->orWhere('contacts.phone2', 'like', "%{$search}%")
                  ->orWhere('contacts.mobile', 'like', "%{$search}%")
                  ->orWhere('contacts.fax', 'like', "%{$search}%")
                  ->orWhere('contacts.notes', 'like', "%{$search}%")
                  ->orWhere('organizations.name', 'like', "%{$search}%");
            });
        }
        if ($tagId = $request->get('tag_id')) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
        }

        $query->orderByRaw("CASE WHEN organizations.type IS NULL THEN 1 ELSE 0 END")
              ->orderBy('organizations.type')
              ->orderBy('organizations.name')
              ->orderBy('contacts.first_name');

        $contacts = $query->with('tags')->paginate(20)->withQueryString();
        $tags = \App\Models\Tag::orderBy('name')->get();
        $orgTypeLabels = \App\Models\Organization::TYPES;

        return view('contacts.index', compact('contacts', 'tags', 'orgTypeLabels'));
    }

    public function create(CustomFieldService $cf)
    {
        $organizations = Organization::orderBy('name')->get();
        $customFields = $cf->definitions('contact');
        $customValues = [];
        $customVisible = [];
        return view('contacts.create', compact('organizations', 'customFields', 'customValues', 'customVisible'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'organization_id' => 'nullable|exists:organizations,id',
            'position' => 'nullable|string|max:150',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:30',
            'phone2' => 'nullable|string|max:30',
            'mobile' => 'nullable|string|max:30',
            'fax' => 'nullable|string|max:30',
            'notes' => 'nullable|string',
        ]);

        $contact = Contact::create($data);
        if ($request->filled('tag_ids')) {
            $contact->tags()->sync($request->input('tag_ids', []));
        }
        app(CustomFieldService::class)->save('contact', $contact->id, $request->all());

        return redirect()->route('contacts.index')->with('success', __('app.contact_created'));
    }

    public function edit(Contact $contact, CustomFieldService $cf)
    {
        $contact->load('tags');
        $organizations = Organization::orderBy('name')->get();
        $customFields = $cf->definitions('contact');
        $customValues = $cf->values('contact', $contact->id);
        $customVisible = $cf->visibility('contact', $contact->id);
        return view('contacts.edit', compact('contact', 'organizations', 'customFields', 'customValues', 'customVisible'));
    }

    public function update(Request $request, Contact $contact)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'organization_id' => 'nullable|exists:organizations,id',
            'position' => 'nullable|string|max:150',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:30',
            'phone2' => 'nullable|string|max:30',
            'mobile' => 'nullable|string|max:30',
            'fax' => 'nullable|string|max:30',
            'notes' => 'nullable|string',
        ]);

        $contact->update($data);
        $contact->tags()->sync($request->input('tag_ids', []));
        app(CustomFieldService::class)->save('contact', $contact->id, $request->all());

        return redirect()->route('contacts.card', $contact)->with('success', __('app.contact_updated'));
    }

    public function destroy(Contact $contact)
    {
        if (!Auth::user()->hasRole('admin')) {
            abort(403, 'فقط ادمین می‌تواند مخاطب را حذف کند.');
        }

        $contact->delete();
        return redirect()->route('contacts.index')->with('success', 'مخاطب حذف شد.');
    }

    public function bulkAction(Request $request)
    {
        if (!Auth::user()->hasRole('admin')) {
            abort(403, 'فقط ادمین می‌تواند مخاطب حذف کند.');
        }
        $data = $request->validate([
            'bulk_action' => 'required|in:delete',
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:contacts,id',
        ]);
        if ($data['bulk_action'] === 'delete') {
            Contact::whereIn('id', $data['ids'])->delete();
        }
        return back()->with('success', 'مخاطبان انتخاب‌شده حذف شدند.');
    }

    public function card(Contact $contact)
    {
        $contact->load(['organization', 'confidentialNotes.user']);

        $canSeeConfidential = Auth::user()->can('contact.view_confidential_notes')
            || Auth::user()->hasAnyRole(['admin', 'technical_manager', 'financial_manager']);

        // Related cases via organization or future direct link
        $relatedCases = collect();
        if ($contact->organization_id) {
            $relatedCases = CaseModel::where('customer_organization_id', $contact->organization_id)
                ->whereNotIn('current_status', ['closed', 'lost'])
                ->latest()
                ->limit(10)
                ->get();
        }

        $cf = app(CustomFieldService::class);
        $customFields = $cf->definitions('contact');
        $customValues = $cf->values('contact', $contact->id);
        $customVisible = $cf->visibility('contact', $contact->id);
        return view('contacts.card', compact('contact', 'canSeeConfidential', 'relatedCases', 'customFields', 'customValues', 'customVisible'));
    }

    public function storeConfidentialNote(Request $request, Contact $contact)
    {
        $canManage = Auth::user()->can('contact.manage_confidential_notes')
            || Auth::user()->hasAnyRole(['admin', 'technical_manager', 'financial_manager']);

        if (!$canManage) {
            abort(403, 'دسترسی به یادداشت محرمانه ندارید.');
        }

        $data = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        ContactConfidentialNote::create([
            'contact_id' => $contact->id,
            'user_id' => Auth::id(),
            'body' => $data['body'],
        ]);

        return back()->with('success', 'یادداشت محرمانه ثبت شد.');
    }

    /**
     * دانلود نمونه فایل CSV برای ایمپورت (قابل باز شدن در Excel)
     */
    public function importTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="contacts_import_template.csv"',
        ];
        $callback = function () {
            $out = fopen('php://output', 'w');
            // BOM برای نمایش صحیح فارسی در Excel
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, [
                'نام مسئول', 'سازمان', 'سمت', 'تلفن تماس', 'تلفن تماس ٢',
                'موبایل', 'فکس', 'ایمیل', 'توضیحات',
            ]);
            fputcsv($out, [
                'علی رضایی', 'شرکت نمونه', 'مدیر خرید', '02112345678', '02187654321',
                '09121234567', '02111111111', 'ali@example.com', 'توضیح نمونه',
            ]);
            fclose($out);
        };
        return response()->stream($callback, 200, $headers);
    }

    /**
     * ایمپورت مخاطبان از CSV (خروجی Excel با Save As → CSV UTF-8)
     */

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $raw = file_get_contents($request->file('file')->getRealPath());
        if ($raw === false || $raw === '') {
            return back()->withErrors(['file' => 'خواندن فایل ممکن نیست یا فایل خالی است.']);
        }

        // حذف BOM و تبدیل احتمالی Windows-1256 / UTF-16
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        } elseif (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16');
        } elseif (!mb_check_encoding($raw, 'UTF-8')) {
            $converted = false;
            foreach (['CP1256', 'ISO-8859-6', 'ISO-8859-1'] as $enc) {
                if (!in_array($enc, mb_list_encodings(), true)) {
                    continue;
                }
                $tmp = @mb_convert_encoding($raw, 'UTF-8', $enc);
                if (is_string($tmp) && $tmp !== '' && mb_check_encoding($tmp, 'UTF-8')) {
                    $raw = $tmp;
                    $converted = true;
                    break;
                }
            }
            if (!$converted && function_exists('iconv')) {
                foreach (['CP1256', 'WINDOWS-1256', 'ISO-8859-1'] as $enc) {
                    $tmp = @iconv($enc, 'UTF-8//IGNORE', $raw);
                    if (is_string($tmp) && $tmp !== '') {
                        $raw = $tmp;
                        break;
                    }
                }
            }
        }

        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $lines = array_values(array_filter(explode("\n", $raw), fn ($l) => trim($l) !== ''));
        if (count($lines) < 1) {
            return back()->withErrors(['file' => 'فایل خالی است.']);
        }

        $delimiter = $this->detectDelimiter($lines[0]);
        $headerRow = str_getcsv($lines[0], $delimiter);
        $headers = array_map(fn ($h) => $this->normalizeHeader((string) $h), $headerRow);
        $map = $this->mapImportHeaders($headers);

        if (!isset($map['name'])) {
            $seen = implode(' | ', array_map(fn ($h) => $h === '' ? '(خالی)' : $h, $headers));
            return back()->withErrors([
                'file' => 'ستون نام پیدا نشد. عنوان‌های خوانده‌شده از فایل: [' . $seen . ']. جداکننده تشخیص‌داده‌شده: «' . ($delimiter === "\t" ? 'TAB' : $delimiter) . '». ردیف اول باید عنوان ستون‌ها باشد (مثلاً: نام مسئول).',
            ]);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        for ($r = 1; $r < count($lines); $r++) {
            $row = str_getcsv($lines[$r], $delimiter);
            if ($this->rowEmpty($row)) {
                continue;
            }
            try {
                $get = function (string $key) use ($map, $row) {
                    if (!isset($map[$key])) {
                        return null;
                    }
                    $i = $map[$key];
                    return isset($row[$i]) ? trim((string) $row[$i]) : null;
                };

                $fullName = $get('name') ?: '';
                if ($fullName === '') {
                    $skipped++;
                    continue;
                }
                [$first, $last] = $this->splitName($fullName);

                $orgName = $get('organization');
                $organizationId = null;
                if ($orgName) {
                    $org = Organization::firstOrCreate(
                        ['name' => $orgName],
                        ['type' => 'customer']
                    );
                    $organizationId = $org->id;
                }

                $email = $get('email') ?: null;
                $mobile = $get('mobile') ?: null;
                $phone = $get('phone') ?: null;

                $contact = null;
                if ($email) {
                    $contact = Contact::where('email', $email)->first();
                }
                if (!$contact && $mobile) {
                    $contact = Contact::where('mobile', $mobile)
                        ->where('first_name', $first)
                        ->where('last_name', $last)
                        ->first();
                }

                $payload = [
                    'first_name' => $first,
                    'last_name' => $last,
                    'organization_id' => $organizationId,
                    'position' => $get('position') ?: null,
                    'phone' => $phone,
                    'phone2' => $get('phone2') ?: null,
                    'mobile' => $mobile,
                    'fax' => $get('fax') ?: null,
                    'email' => $email,
                    'notes' => $get('notes') ?: null,
                ];

                if ($contact) {
                    $contact->update($payload);
                    $updated++;
                } else {
                    Contact::create($payload);
                    $created++;
                }
            } catch (\Throwable $e) {
                $errors[] = 'ردیف ' . ($r + 1) . ': ' . $e->getMessage();
                $skipped++;
            }
        }

        $msg = "ایمپورت انجام شد. جدید: {$created} · به‌روزرسانی: {$updated} · رد شده: {$skipped}";
        if ($errors) {
            $msg .= ' | خطاها: ' . implode('؛ ', array_slice($errors, 0, 5));
        }
        return redirect()->route('contacts.index')->with('success', $msg);
    }

    /**
     * خروجی CSV همه مخاطبان (بک‌آپ دفترچه)
     */
    public function export()
    {
        $filename = 'contacts_backup_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, [
                'نام مسئول', 'سازمان', 'سمت', 'تلفن تماس', 'تلفن تماس ٢',
                'موبایل', 'فکس', 'ایمیل', 'توضیحات',
            ]);

            Contact::with('organization')->orderBy('id')->chunk(200, function ($contacts) use ($out) {
                foreach ($contacts as $c) {
                    fputcsv($out, [
                        $c->full_name,
                        $c->organization?->name ?? '',
                        $c->position ?? '',
                        $c->phone ?? '',
                        $c->phone2 ?? '',
                        $c->mobile ?? '',
                        $c->fax ?? '',
                        $c->email ?? '',
                        $c->notes ?? '',
                    ]);
                }
            });
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }


    protected function detectDelimiter(string $line): string
    {
        $candidates = [',' => 0, ';' => 0, "\t" => 0];
        foreach ($candidates as $d => $_) {
            $candidates[$d] = count(str_getcsv($line, $d));
        }
        arsort($candidates);
        $best = array_key_first($candidates);
        return ($candidates[$best] > 1) ? $best : ',';
    }

    protected function normalizeHeader(string $h): string
    {
        $h = str_replace("\xEF\xBB\xBF", '', $h);
        $h = trim($h);
        $h = $h = preg_replace('/[\x{200C}\x{200F}\x{200E}\x{00A0}]/u', '', $h) ?? $h;
        // ی/ک عربی → فارسی
        $h = str_replace(['ي', 'ك'], ['ی', 'ک'], $h);
        // ارقام فارسی/عربی → انگلیسی در عنوان اهمیتی ندارد
        $h = preg_replace('/\s+/u', ' ', $h) ?? $h;
        return mb_strtolower(trim($h));
    }

    protected function mapImportHeaders(array $headers): array
    {
        $aliases = [
            'name' => ['نام مسئول', 'نام و نام خانوادگی', 'نام خانوادگی و نام', 'full_name', 'fullname', 'name', 'contact name', 'نام'],
            'organization' => ['سازمان', 'شرکت', 'نام سازمان', 'نام شرکت', 'organization', 'company', 'account'],
            'position' => ['سمت', 'سمت سازمانی', 'عنوان شغلی', 'position', 'title', 'job title'],
            'phone' => ['تلفن تماس', 'تلفن', 'تلفن ثابت', 'phone', 'tel', 'telephone'],
            'phone2' => ['تلفن تماس ٢', 'تلفن تماس 2', 'تلفن2', 'تلفن ۲', 'phone2', 'tel2'],
            'mobile' => ['موبایل', 'همراه', 'تلفن همراه', 'mobile', 'cellphone', 'cell'],
            'fax' => ['فکس', 'فاکس', 'fax'],
            'email' => ['ایمیل', 'پست الکترونیک', 'email', 'e-mail', 'mail'],
            'notes' => ['توضیحات', 'یادداشت', 'توضیح', 'notes', 'description', 'remark'],
        ];

        $map = [];
        foreach ($headers as $i => $h) {
            $hNorm = $this->normalizeHeader($h);
            foreach ($aliases as $key => $list) {
                if (isset($map[$key])) {
                    continue;
                }
                foreach ($list as $alias) {
                    $a = $this->normalizeHeader($alias);
                    if ($hNorm === $a || str_contains($hNorm, $a) || str_contains($a, $hNorm)) {
                        // برای name از match خیلی کوتاه «نام» فقط اگر برابر یا شروع عنوان باشد
                        if ($key === 'name' && $a === 'نام' && $hNorm !== 'نام' && !str_starts_with($hNorm, 'نام')) {
                            continue;
                        }
                        $map[$key] = $i;
                        break 2;
                    }
                }
            }
        }

        // اگر name پیدا نشد: اولین ستونی که شامل «نام» است و سازمان/شرکت نیست
        if (!isset($map['name'])) {
            foreach ($headers as $i => $h) {
                $hNorm = $this->normalizeHeader($h);
                if (str_contains($hNorm, 'نام') && !str_contains($hNorm, 'سازمان') && !str_contains($hNorm, 'شرکت')) {
                    $map['name'] = $i;
                    break;
                }
            }
        }

        // آخرین راه: ستون اول را نام بگیر
        if (!isset($map['name']) && count($headers) > 0) {
            $map['name'] = 0;
        }

        return $map;
    }

    protected function splitName(string $full): array
    {
        $full = trim(preg_replace('/\s+/u', ' ', $full));
        $parts = explode(' ', $full, 2);
        $first = $parts[0] ?? '—';
        $last = $parts[1] ?? '—';
        if ($last === '') $last = '—';
        return [$first, $last];
    }

    protected function rowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') return false;
        }
        return true;
    }
}
