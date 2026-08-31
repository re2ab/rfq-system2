<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentRevision;

/**
 * مسیر نقطه‌ای (dot-notation) یک TemplateField.binding را در برابر «ریشه‌های»
 * شناخته‌شده resolve می‌کند — مثال: "case.organization.name"، "case.case_number"،
 * "document.title"، "today".
 *
 * اگر یک placeholder اصلاً binding صریحی نداشته باشد (source=auto ولی binding
 * هنوز از صفحه‌ی تنظیم قالب انتخاب نشده)، فراخواننده (DocumentGenerationService)
 * خودِ کلید placeholder را به‌عنوان مسیر امتحان می‌کند — یعنی نویسنده‌ی قالب
 * می‌تواند مستقیماً {{case.case_number}} تایپ کند و بدون هیچ تنظیم اضافه‌ای کار کند.
 */
class BindingResolver
{
    /** @param array<string,mixed> $roots */
    public function __construct(protected array $roots)
    {
    }

    public static function forDocument(Document $document, ?DocumentRevision $revision = null): self
    {
        $case = $document->relationLoaded('case') ? $document->case : $document->case()->first();

        return new self([
            'document' => $document,
            'case' => $case,
            'organization' => $case?->organization,
            'contact' => $case?->contact,
            'revision' => $revision,
            'today' => now(),
        ]);
    }

    /** مسیر را resolve و به رشته‌ی چاپ‌پذیر تبدیل می‌کند؛ هر شکست، رشته‌ی خالی است — نه استثنا. */
    public function resolve(string $path): string
    {
        $segments = explode('.', trim($path));
        if ($segments === [] || $segments[0] === '') {
            return '';
        }

        $root = array_shift($segments);
        $value = $this->roots[$root] ?? null;

        foreach ($segments as $seg) {
            if ($value === null) {
                return '';
            }
            $value = $this->readSegment($value, $seg);
        }

        return $this->stringify($value);
    }

    protected function readSegment(mixed $value, string $segment): mixed
    {
        if (is_array($value)) {
            return $value[$segment] ?? null;
        }
        if (is_object($value)) {
            try {
                return $value->{$segment} ?? null; // برای مدل‌های Eloquent: هم attribute هم relation را lazy-load می‌کند
            } catch (\Throwable $e) {
                return null;
            }
        }
        return null;
    }

    protected function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return function_exists('jdate') ? jdate($value) : $value->format('Y-m-d');
        }
        if (is_bool($value)) {
            return $value ? 'بله' : 'خیر';
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }
        if (is_array($value) || is_object($value)) {
            return ''; // مسیر ناقص مانده (مثلاً به یک relation ختم شده، نه یک ستون واقعی) — به‌جای dump کردن، خالی
        }
        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
        }

        return (string) $value;
    }
}
