<?php
namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Morilog\Jalali\Jalalian;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $jNow = Jalalian::now();
        $jy = (int) $request->get('jy', $jNow->getYear());
        $jm = (int) $request->get('jm', $jNow->getMonth());

        if ($jm < 1) {
            $jm = 12;
            $jy--;
        }
        if ($jm > 12) {
            $jm = 1;
            $jy++;
        }

        $jStart = new Jalalian($jy, $jm, 1);
        $gStart = $jStart->toCarbon()->startOfDay();
        $daysInMonth = $jStart->getMonthDays();
        $gEnd = (new Jalalian($jy, $jm, $daysInMonth))->toCarbon()->endOfDay();

        // شنبه = ستون ۰
        $startWeekday = ($gStart->dayOfWeek + 1) % 7;

        // ---- هفته جاری برای موبایل ----
        // anchor: تاریخ انتخاب‌شده یا امروز (در محدوده ماه اگر ماه عوض شده)
        $jd = (int) $request->get('jd', 0);
        if ($jd < 1 || $jd > $daysInMonth) {
            if ($jy === $jNow->getYear() && $jm === $jNow->getMonth()) {
                $jd = $jNow->getDay();
            } else {
                $jd = 1;
            }
        }
        $weekOffset = (int) $request->get('wo', 0); // جابه‌جایی هفته

        $anchor = (new Jalalian($jy, $jm, $jd))->toCarbon()->startOfDay()->addWeeks($weekOffset);
        // شروع هفته از شنبه
        $dow = ($anchor->dayOfWeek + 1) % 7; // 0=شنبه
        $weekStart = $anchor->copy()->subDays($dow)->startOfDay();
        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $g = $weekStart->copy()->addDays($i);
            $j = Jalalian::fromCarbon($g);
            $weekDays[] = [
                'g' => $g,
                'j' => $j,
                'key' => $j->format('Y-m-d'),
                'day' => $j->getDay(),
                'monthName' => $j->format('%B'),
                'jy' => $j->getYear(),
                'jm' => $j->getMonth(),
                'isFri' => $i === 6,
                'isToday' => $j->format('Y-m-d') === $jNow->format('Y-m-d'),
            ];
        }
        $weekRangeStart = $weekStart->copy()->startOfDay();
        $weekRangeEnd = $weekStart->copy()->addDays(6)->endOfDay();

        $dayNames = ['شنبه','یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه'];
        $view = in_array($request->get('view'), ['month', 'week', 'day'], true) ? $request->get('view') : 'month';
        $doOffset = (int) $request->get('do', 0);
        $dayAnchor = (new Jalalian($jy, $jm, $jd))->toCarbon()->startOfDay()->addDays($doOffset);
        $dayJ = Jalalian::fromCarbon($dayAnchor);
        $dayDow = ($dayAnchor->dayOfWeek + 1) % 7;
        $dayInfo = [
            'key' => $dayJ->format('Y-m-d'),
            'day' => $dayJ->getDay(),
            'monthName' => $dayJ->format('%B'),
            'year' => $dayJ->getYear(),
            'dayName' => $dayNames[$dayDow] ?? '',
            'isFri' => $dayDow === 6,
            'isToday' => $dayJ->format('Y-m-d') === $jNow->format('Y-m-d'),
        ];
        $dayRangeStart = $dayAnchor->copy()->startOfDay();
        $dayRangeEnd = $dayAnchor->copy()->endOfDay();

        // بارگذاری وظایف ماه + هفته (برای هر دو نما)
        $rangeStart = $gStart->lt($weekRangeStart) ? $gStart : $weekRangeStart;
        $rangeStart = $rangeStart->lt($dayRangeStart) ? $rangeStart : $dayRangeStart;
        $rangeEnd = $gEnd->gt($weekRangeEnd) ? $gEnd : $weekRangeEnd;
        $rangeEnd = $rangeEnd->gt($dayRangeEnd) ? $rangeEnd : $dayRangeEnd;

        $query = Task::with(['assignee', 'case'])
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$rangeStart, $rangeEnd])
            ->whereNotIn('status', ['cancelled']);

        if (!$user->hasAnyRole(['admin', 'technical_manager', 'financial_manager'])) {
            $query->visibleTo($user->id);
        }

        $tasks = $query->orderBy('due_at')->get()->groupBy(function ($t) {
            return Jalalian::fromCarbon($t->due_at)->format('Y-m-d');
        });

        // M13: تعطیلات رسمی سفارشی — جدا از جمعه‌ها (که قبلاً هاردکد قرمز
        // بودند)، نگاشت 'Y-m-d' شمسی → عنوان می‌سازیم تا هر ویو (ماهانه/
        // هفتگی/روزانه/موبایل) با یک lookup ساده رنگ قرمز را اعمال کند.
        $holidays = [];
        try {
            $holidays = \App\Models\Holiday::fixedByDate();
            $recurringMd = \App\Models\Holiday::recurringByMonthDay();
            if ($recurringMd) {
                $years = collect([$jy])
                    ->merge(collect($weekDays)->pluck('jy'))
                    ->push($dayJ->getYear())
                    ->unique();
                foreach ($years as $yy) {
                    foreach ($recurringMd as $md => $title) {
                        $holidays[sprintf('%04d-%s', $yy, $md)] = $title;
                    }
                }
            }
        } catch (\Throwable $e) {
            // جدول هنوز مایگریت نشده — تقویم بدون تعطیلات سفارشی (فقط جمعه) کار می‌کند
        }

        $monthName = $jStart->format('%B');
        $todayKey = $jNow->format('Y-m-d');

        $prevJy = $jm === 1 ? $jy - 1 : $jy;
        $prevJm = $jm === 1 ? 12 : $jm - 1;
        $nextJy = $jm === 12 ? $jy + 1 : $jy;
        $nextJm = $jm === 12 ? 1 : $jm + 1;

        $weekLabel = $weekDays[0]['j']->format('%d %B').' — '.$weekDays[6]['j']->format('%d %B %Y');

        return view('calendar.index', compact(
            'tasks', 'jy', 'jm', 'daysInMonth', 'startWeekday',
            'monthName', 'todayKey', 'prevJy', 'prevJm', 'nextJy', 'nextJm',
            'weekDays', 'weekOffset', 'jd', 'weekLabel', 'view', 'doOffset', 'dayInfo',
            'holidays'
        ));
    }
}
