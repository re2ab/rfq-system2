<?php

if (!function_exists('dashboard_widget_catalog')) {
    function dashboard_widget_catalog(): array
    {
        return [
            'pipeline_status' => 'توزیع وضعیت (پایپ‌لاین)',
            'recent_cases' => 'آخرین پرونده‌ها',
            'trend_cases' => 'روند پرونده‌های جدید (۱۴ روز)',
            'trend_activity' => 'روند فعالیت‌ها و برد (۱۴ روز)',
            'mgmt_chart' => 'نمودار مدیریتی',
            'pie_chart' => 'نمودار پای',
            'industry_chart' => 'پرونده بر حسب صنعت مشتری',
            'assigned_open_tasks' => 'وظایف اختصاص‌داده‌شده باز',
            'my_tasks' => 'وظایف من',
            'recent_activities' => 'فعالیت‌های اخیر',
        ];
    }
}

if (!function_exists('dashboard_layout_default')) {
    function dashboard_layout_default(): array
    {
        $widths = [
            'pipeline_status' => 50, 'recent_cases' => 50,
            'trend_cases' => 50, 'trend_activity' => 50,
            'mgmt_chart' => 33, 'pie_chart' => 33, 'industry_chart' => 33,
            'assigned_open_tasks' => 50, 'my_tasks' => 50,
            'recent_activities' => 100,
        ];
        $out = [];
        foreach (dashboard_widget_catalog() as $key => $label) {
            $out[] = ['key' => $key, 'width' => $widths[$key] ?? 50];
        }
        return $out;
    }
}

if (!function_exists('dashboard_layout')) {
    function dashboard_layout(): array
    {
        $catalog = dashboard_widget_catalog();
        $default = dashboard_layout_default();
        try {
            $raw = \App\Models\AppSetting::get('dashboard_layout');
            $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
            if (is_array($decoded) && count($decoded)) {
                $seen = [];
                $out = [];
                foreach ($decoded as $item) {
                    $key = $item['key'] ?? null;
                    if (!$key || !isset($catalog[$key]) || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $width = (int) ($item['width'] ?? 50);
                    $out[] = ['key' => $key, 'width' => in_array($width, [33, 50, 70, 100], true) ? $width : 50];
                }
                // هر ویجت جدیدی که بعداً به کاتالوگ اضافه شود، انتهای چیدمان اضافه می‌شود
                foreach ($default as $d) {
                    if (!isset($seen[$d['key']])) {
                        $out[] = $d;
                    }
                }
                if (count($out)) {
                    return $out;
                }
            }
        } catch (\Throwable $e) {
        }
        return $default;
    }
}
if (!function_exists('currency_label')) {
    function currency_label(?string $code): string
    {
        $code = strtoupper(trim((string) $code));
        return match ($code) {
            'EUR' => 'یورو',
            'IRR' => 'ریال',
            'USD' => 'دلار',
            '' => '—',
            default => $code,
        };
    }
}

if (!function_exists('fa_num')) {
    function fa_num(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $s = is_float($value) || is_int($value)
            ? (is_float($value) ? rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') : (string) $value)
            : (string) $value;

        return strtr($s, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
            ',' => '٬', '.' => '٫',
        ]);
    }
}

if (!function_exists('en_num')) {
    function en_num(mixed $value): string
    {
        return strtr((string) $value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٬' => '', '٫' => '.',
        ]);
    }
}

if (!function_exists('jdate')) {
    function jdate(mixed $date, string $format = 'Y/m/d'): string
    {
        if ($date === null || $date === '') {
            return '—';
        }
        $format = preg_replace('/[HhGgisuaA:]+/', '', $format);
        $format = trim(preg_replace('/\s+/', ' ', $format)) ?: 'Y/m/d';
        try {
            if (is_string($date) && preg_match('/^(\d{4}-\d{2}-\d{2})/', $date, $m)) {
                $j = \Morilog\Jalali\Jalalian::fromDateTime($m[1]);
            } elseif ($date instanceof \Carbon\CarbonInterface || $date instanceof \DateTimeInterface) {
                $j = \Morilog\Jalali\Jalalian::fromDateTime($date->format('Y-m-d'));
            } else {
                $c = \Carbon\Carbon::parse((string) $date);
                $j = \Morilog\Jalali\Jalalian::fromDateTime($c->format('Y-m-d'));
            }
            $out = fa_num($j->format($format));
            $out = preg_replace('/\s*[۰-۹0-9]{1,2}:[۰-۹0-9]{2}(:[۰-۹0-9]{2})?\s*/u', '', $out);
            $out = trim($out) !== '' ? trim($out) : '—';
            return jbidi($out);
        } catch (\Throwable $e) {
            // این مسیر فقط برای رشته‌های واقعاً غیرقابل‌پارس اجرا می‌شود (چیزی
            // که خودِ Jalalian/Carbon هم رویش throw می‌کنند). توجه: رشته‌ی
            // شمسی مثل «۱۴۰۵-۰۳-۰۷» اینجا نمی‌آید — چون Carbon::parse آن را
            // (به‌اشتباه، بدون throw) به‌عنوان سال ۱۴۰۵ *میلادی* واقعی می‌پذیرد؛
            // آن حالت را همان بلوک try بالا (نه اینجا) هندل می‌کند. اینجا فقط
            // یک fallback بی‌خطر است: ساعت را (اگر باشد) حذف می‌کنیم و همان
            // رشته را خام برمی‌گردانیم.
            $s = preg_replace('/\s*\d{1,2}:\d{2}(:\d{2})?\s*/', ' ', (string) $date);
            return jbidi(fa_num(trim($s)));
        }
    }
}

if (!function_exists('jbidi')) {
    /**
     * یک رشته‌ی تاریخ/ساعت را با نشانگرهای نامرئی Unicode (LRI…PDI) در یک
     * «جزیره‌ی» چپ‌به‌راست ایزوله می‌کند — بدون این، وقتی خروجی jdate()/
     * jdatetime() (که دو تکه‌ی عددی جدا مثل «تاریخ ساعت» است) بدون
     * dir="ltr" داخل یک پاراگراف/جمله‌ی راست‌به‌چپ چاپ شود، الگوریتم
     * bidi مرورگر می‌تواند ترتیب دیداری دو تکه را برعکس کند (مثلاً «ساعت
     * تاریخ» به‌جای «تاریخ ساعت» دیده شود) — دقیقاً همان چیزی که کاربر در
     * چند بخش (سررسید، لاگ‌ها) گزارش داد. چون فقط کاراکتر کنترلی نامرئی
     * است، هم در HTML خام امن است هم در `{{ }}` (بدون نیاز به `{!! !!}`).
     */
    function jbidi(string $s): string
    {
        if ($s === '' || $s === '—') {
            return $s;
        }
        return "\u{2066}" . $s . "\u{2069}";
    }
}

if (!function_exists('jdatetime')) {
    /**
     * مثل jdate() ولی ساعت را هم نگه می‌دارد — برای جاهایی که واقعاً یک
     * لحظه/رویداد نشان داده می‌شود (مثلاً «ثبت شد در…»، لاگ حسابرسی، نسخه‌ی
     * سند) نه یک تاریخ خالص (سررسید، تاریخ تحویل). یکدست‌سازی M13: قبلاً در
     * چند صفحه مقدار خام Carbon چاپ می‌شد که میلادی و با ساعت بود؛ حالا همه
     * جا شمسی‌اند — تاریخ‌های خالص با jdate() (بدون ساعت) و لحظه‌ها با
     * jdatetime() (با ساعت).
     */
    function jdatetime(mixed $date, string $format = 'Y/m/d H:i'): string
    {
        if ($date === null || $date === '') {
            return '—';
        }
        try {
            $c = ($date instanceof \Carbon\CarbonInterface || $date instanceof \DateTimeInterface)
                ? \Carbon\Carbon::parse($date->format('Y-m-d H:i:s'))
                : \Carbon\Carbon::parse((string) $date);
            $j = \Morilog\Jalali\Jalalian::fromCarbon($c);
            $dateFormat = preg_replace('/[HhGgisuaA:]+/', '', $format);
            $dateFormat = trim(preg_replace('/\s+/', ' ', $dateFormat)) ?: 'Y/m/d';
            $datePart = trim(fa_num($j->format($dateFormat)));
            $timePart = fa_num($c->format('H:i'));
            return jbidi($datePart !== '' ? ($datePart . ' ' . $timePart) : $timePart);
        } catch (\Throwable $e) {
            return jdate($date);
        }
    }
}

if (!function_exists('due_label')) {
    function due_label(mixed $date): string
    {
        return jdate($date, 'Y/m/d');
    }
}

if (!function_exists('jdate_input')) {
    function jdate_input(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        try {
            return jdate($date, 'Y/m/d');
        } catch (\Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('parse_due_date')) {
    function parse_due_date(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = en_num($raw);
        $s = trim($s);
        $s = preg_replace('/[T\s]+\d{1,2}:\d{2}.*$/', '', $s);
        $s = str_replace(['.', '-'], '/', $s);
        $s = trim($s);

        if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $s, $m)) {
            $y = (int) $m[1];
            $mo = (int) $m[2];
            $d = (int) $m[3];
            if ($y >= 1200 && $y <= 1500) {
                try {
                    $j = \Morilog\Jalali\Jalalian::fromFormat(
                        'Y/m/d',
                        sprintf('%04d/%02d/%02d', $y, $mo, $d)
                    );
                    return $j->toCarbon()->format('Y-m-d') . ' 12:00:00';
                } catch (\Throwable $e) {
                    return null;
                }
            }
            if ($y >= 1900 && $y <= 2100) {
                return sprintf('%04d-%02d-%02d 12:00:00', $y, $mo, $d);
            }
        }

        $s2 = str_replace('/', '-', $s);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s2, $m)) {
            return sprintf('%04d-%02d-%02d 12:00:00', (int)$m[1], (int)$m[2], (int)$m[3]);
        }

        try {
            return \Carbon\Carbon::parse($s2)->format('Y-m-d') . ' 12:00:00';
        } catch (\Throwable $e) {
            return null;
        }
    }
}


if (!function_exists('task_priorities')) {
    function task_priorities(): array
    {
        $defaults = [
            'low' => 'پایین',
            'medium' => 'متوسط',
            'high' => 'بالا',
            'urgent' => 'فوری',
        ];
        try {
            $raw = \App\Models\AppSetting::get('task_priorities');
            if ($raw) {
                $decoded = is_array($raw) ? $raw : json_decode($raw, true);
                if (is_array($decoded) && count($decoded)) {
                    return $decoded;
                }
            }
        } catch (\Throwable $e) {
        }
        return $defaults;
    }
}

if (!function_exists('task_priorities_meta')) {
    function task_priorities_meta(): array
    {
        $defaults = [
            'low' => ['label' => 'پایین', 'color' => '#64748b'],
            'medium' => ['label' => 'متوسط', 'color' => '#0ea5e9'],
            'high' => ['label' => 'بالا', 'color' => '#f59e0b'],
            'urgent' => ['label' => 'فوری', 'color' => '#dc2626'],
        ];
        try {
            $raw = \App\Models\AppSetting::get('task_priorities');
            if ($raw) {
                $decoded = is_array($raw) ? $raw : json_decode($raw, true);
                if (is_array($decoded) && count($decoded)) {
                    $out = [];
                    foreach ($decoded as $k => $v) {
                        if (is_array($v)) {
                            $out[$k] = [
                                'label' => $v['label'] ?? (string) $k,
                                'color' => $v['color'] ?? ($defaults[$k]['color'] ?? '#64748b'),
                            ];
                        } else {
                            $out[$k] = [
                                'label' => (string) $v,
                                'color' => $defaults[$k]['color'] ?? '#64748b',
                            ];
                        }
                    }
                    return $out;
                }
            }
        } catch (\Throwable $e) {
        }
        return $defaults;
    }
}

if (!function_exists('task_priorities')) {
    function task_priorities(): array
    {
        $m = task_priorities_meta();
        $out = [];
        foreach ($m as $k => $v) {
            $out[$k] = is_array($v) ? ($v['label'] ?? $k) : $v;
        }
        return $out;
    }
}

if (!function_exists('case_priorities_meta')) {
    function case_priorities_meta(): array
    {
        $defaults = [
            'low' => ['label' => 'پایین', 'color' => '#64748b'],
            'medium' => ['label' => 'متوسط', 'color' => '#0ea5e9'],
            'high' => ['label' => 'بالا', 'color' => '#f59e0b'],
            'urgent' => ['label' => 'فوری', 'color' => '#dc2626'],
        ];
        try {
            $raw = \App\Models\AppSetting::get('case_priorities');
            if ($raw) {
                $decoded = is_array($raw) ? $raw : json_decode($raw, true);
                if (is_array($decoded) && count($decoded)) {
                    $out = [];
                    foreach ($decoded as $k => $v) {
                        if (is_array($v)) {
                            $out[$k] = [
                                'label' => $v['label'] ?? (string) $k,
                                'color' => $v['color'] ?? ($defaults[$k]['color'] ?? '#64748b'),
                            ];
                        } else {
                            $out[$k] = [
                                'label' => (string) $v,
                                'color' => $defaults[$k]['color'] ?? '#64748b',
                            ];
                        }
                    }
                    return $out;
                }
            }
        } catch (\Throwable $e) {
        }
        return $defaults;
    }
}

if (!function_exists('case_priorities')) {
    function case_priorities(): array
    {
        $m = case_priorities_meta();
        $out = [];
        foreach ($m as $k => $v) {
            $out[$k] = is_array($v) ? ($v['label'] ?? $k) : $v;
        }
        return $out;
    }
}

if (!function_exists('role_label')) {
    /**
     * نام فارسی نقش‌های سیستم (Spatie Role slug → برچسب فارسی) — برای
     * نمایش در فهرست کاربران/فرم‌ها. اگر نقشی خارج از این فهرست بود (مثلاً
     * نقش سفارشی که بعداً اضافه شده)، همان اسم خام برگردانده می‌شود.
     */
    function role_label(?string $role): string
    {
        $labels = [
            'admin' => 'مدیر سیستم',
            'technical_manager' => 'مدیر فنی',
            'financial_manager' => 'مدیر مالی',
            'finance_manager' => 'مدیر مالی',
            'technical_expert' => 'کارشناس فنی',
            'financial_expert' => 'کارشناس مالی',
            'expert' => 'کارشناس',
            'viewer' => 'بازدیدکننده',
        ];
        return $labels[$role] ?? ($role ?: '—');
    }
}

if (!function_exists('priority_badge_style')) {
    function priority_badge_style(?string $key, string $scope = 'task'): array
    {
        $meta = $scope === 'case' ? case_priorities_meta() : task_priorities_meta();
        $item = $meta[$key] ?? null;
        if (!$item) {
            return ['label' => $key ?: '—', 'color' => '#64748b'];
        }
        if (is_array($item)) {
            return ['label' => $item['label'] ?? $key, 'color' => $item['color'] ?? '#64748b'];
        }
        return ['label' => (string) $item, 'color' => '#64748b'];
    }
}
