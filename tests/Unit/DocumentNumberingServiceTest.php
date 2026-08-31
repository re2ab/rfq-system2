<?php

namespace Tests\Unit;

use App\Services\Documents\DocumentNumberingService;
use App\Services\NumberGeneratorService;
use PHPUnit\Framework\TestCase;

/**
 * تست منطق خالص فرمول شماره‌گذاری — caseTag() و formatRevisionNumber() هیچ
 * وابستگی به DB ندارند، پس مثل tests/Unit/VatCalculatorTest.php بدون
 * bootstrap لاراول اجرا می‌شوند. ensureBaseNumber() (که به DB و لاک ردیف
 * وابسته است) اینجا تست نشده — پوششش در
 * tests/Feature/DocumentPublishServiceTest.php است (نیازمند DB واقعی).
 *
 * این فایل با مثال دقیقی که کاربر تأیید کرد اعتبارسنجی می‌شود:
 * پرونده INQ-101633 → پیشنهاد فنی TC-200242-1633-R01.
 */
class DocumentNumberingServiceTest extends TestCase
{
    protected function service(): DocumentNumberingService
    {
        return new DocumentNumberingService(new NumberGeneratorService());
    }

    public function test_case_tag_extracts_last_four_digits(): void
    {
        $svc = $this->service();
        $this->assertSame('1633', $svc->caseTag('INQ-101633'));
    }

    public function test_case_tag_ignores_non_digit_characters(): void
    {
        $svc = $this->service();
        $this->assertSame('1633', $svc->caseTag('INQ/101633'));
        $this->assertSame('1633', $svc->caseTag('101633'));
    }

    public function test_case_tag_handles_short_numeric_case_number(): void
    {
        $svc = $this->service();
        // نظری: شماره‌ی پرونده کمتر از ۴ رقم — همه‌ی ارقام موجود برمی‌گردد، خطا نمی‌دهد.
        $this->assertSame('42', $svc->caseTag('CASE-42'));
    }

    public function test_case_tag_never_returns_empty_string(): void
    {
        $svc = $this->service();
        $this->assertNotSame('', $svc->caseTag(''));
        $this->assertNotSame('', $svc->caseTag('---'));
    }

    public function test_format_revision_number_pads_to_two_digits(): void
    {
        $svc = $this->service();
        $this->assertSame('TC-200242-1633-R01', $svc->formatRevisionNumber('TC-200242-1633', 1));
        $this->assertSame('TC-200242-1633-R09', $svc->formatRevisionNumber('TC-200242-1633', 9));
    }

    /**
     * درخواست بعدیِ کاربر: شماره‌گذاری ریویژن از صفر شروع می‌شود — اولین
     * Draft هر سند R00 است، نه R01 (DocumentRevisionService::createInitial()
     * حالا revision_number=0 می‌سازد).
     */
    public function test_format_revision_number_allows_zero_for_first_revision(): void
    {
        $svc = $this->service();
        $this->assertSame('TC-200242-1633-R00', $svc->formatRevisionNumber('TC-200242-1633', 0));
    }

    public function test_format_revision_number_does_not_truncate_beyond_two_digits(): void
    {
        $svc = $this->service();
        $this->assertSame('TC-200242-1633-R12', $svc->formatRevisionNumber('TC-200242-1633', 12));
    }

    public function test_matches_users_worked_example_end_to_end(): void
    {
        // پرونده INQ-101633، پیشنهاد فنی TC-200242، ریویژن ۱ → TC-200242-1633-R01
        // (مثال دقیقی که کاربر برای رفع OQ-1/OQ-2 داد).
        $svc = $this->service();
        $caseTag = $svc->caseTag('INQ-101633');
        $numberBase = 'TC-200242-'.$caseTag; // شبیه‌سازی همان چیزی که ensureBaseNumber می‌سازد
        $this->assertSame('TC-200242-1633-R01', $svc->formatRevisionNumber($numberBase, 1));

        // پیشنهاد مالی همان پرونده
        $numberBasePi = 'PI-300259-'.$caseTag;
        $this->assertSame('PI-300259-1633-R01', $svc->formatRevisionNumber($numberBasePi, 1));

        // فاکتور فروش همان پرونده (کاربر تأیید کرد: جا افتادن R01 در مثال اول فقط تایپو بود)
        $numberBaseCi = 'CI-400148-'.$caseTag;
        $this->assertSame('CI-400148-1633-R01', $svc->formatRevisionNumber($numberBaseCi, 1));
    }
}
