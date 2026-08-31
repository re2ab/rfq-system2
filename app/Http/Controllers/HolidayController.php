<?php
namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class HolidayController extends Controller
{
    /**
     * آدرس مخزن عمومی داده‌ی تعطیلات رسمی تقویم شمسی (هر سال یک فایل
     * JSON جدا، استخراج‌شده از time.ir): https://github.com/hasan-ahani/shamsi-holidays
     */
    private const GITHUB_REPO_RAW_BASE = 'https://raw.githubusercontent.com/hasan-ahani/shamsi-holidays/main/holidays';
    public function index()
    {
        $holidays = Holiday::query()->orderByDesc('recurring_yearly')->orderBy('jalali_date')->get();
        try {
            $currentJalaliYear = \Morilog\Jalali\Jalalian::forge('now')->getYear();
        } catch (\Throwable $e) {
            $currentJalaliYear = 1404;
        }
        return view('settings.holidays', compact('holidays', 'currentJalaliYear'));
    }

    public function store(Request $request)
    {
        // ورودی jdp می‌تواند با ارقام فارسی و/یا خط تیره باشد؛ قبل از
        // اعتبارسنجی به ارقام لاتین + اسلش نرمال می‌کنیم (همان الگوی
        // parse_due_date در app/helpers.php).
        $request->merge([
            'jalali_date' => str_replace('-', '/', en_num((string) $request->input('jalali_date'))),
        ]);

        $data = $request->validate([
            'jalali_date' => ['required', 'string', 'regex:/^1[0-9]{3}\/[0-9]{1,2}\/[0-9]{1,2}$/'],
            'title' => 'nullable|string|max:150',
            'recurring_yearly' => 'nullable|boolean',
        ], [
            'jalali_date.regex' => 'تاریخ باید به‌صورت شمسی و با فرمت ۱۴۰۳/۰۱/۰۱ وارد شود.',
        ]);

        [$y, $m, $d] = array_map('intval', explode('/', $data['jalali_date']));
        $normalized = sprintf('%04d-%02d-%02d', $y, $m, $d);

        Holiday::updateOrCreate(
            ['jalali_date' => $normalized],
            [
                'title' => $data['title'] ?? null,
                'recurring_yearly' => (bool) ($data['recurring_yearly'] ?? false),
            ]
        );

        return back()->with('success', 'تعطیلی ثبت شد.');
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();
        return back()->with('success', 'تعطیلی حذف شد.');
    }

    /**
     * دریافت خودکار فهرست تعطیلات رسمی یک سال شمسی خاص از مخزن عمومی
     * hasan-ahani/shamsi-holidays (هر سال یک فایل JSON مجزا، مثل
     * holidays/1404.json) و ثبت/به‌روزرسانی آن‌ها در جدول holidays.
     * چون داده‌ی هر سال جداگانه است (نه یک فرمول تکرارشونده)، همیشه با
     * recurring_yearly=false ذخیره می‌شود — برای سال بعد دوباره از همین
     * فرم با سال جدید Sync می‌شود.
     */
    public function syncFromGithub(Request $request)
    {
        $data = $request->validate([
            'sync_year' => ['required', 'integer', 'min:1390', 'max:1450'],
        ], [], ['sync_year' => 'سال شمسی']);

        $year = (int) $data['sync_year'];

        try {
            $response = Http::timeout(15)->get(self::GITHUB_REPO_RAW_BASE . "/{$year}.json");

            if (!$response->ok()) {
                return back()->withErrors([
                    'sync' => "داده‌ی سال {$year} در مخزن پیدا نشد (کد پاسخ سرور: {$response->status()}). ممکن است هنوز برای این سال منتشر نشده باشد.",
                ]);
            }

            $days = $response->json();
            if (!is_array($days)) {
                return back()->withErrors(['sync' => 'پاسخ دریافتی از مخزن قابل‌خواندن نبود.']);
            }

            $count = 0;
            foreach ($days as $day) {
                if (empty($day['is_holiday']) || empty($day['date'])) {
                    continue;
                }
                $title = collect($day['events'] ?? [])
                    ->filter(fn ($e) => !empty($e['is_holiday']) && !empty($e['description']))
                    ->pluck('description')
                    ->implode('، ');

                Holiday::updateOrCreate(
                    ['jalali_date' => $day['date']],
                    ['title' => $title ?: null, 'recurring_yearly' => false]
                );
                $count++;
            }

            return back()->with('success', "{$count} روز تعطیل رسمی سال {$year} از مخزن دریافت و ثبت/به‌روزرسانی شد.");
        } catch (\Throwable $e) {
            return back()->withErrors(['sync' => 'خطا در ارتباط با مخزن: ' . $e->getMessage()]);
        }
    }
}
