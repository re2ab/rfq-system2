<?php

namespace Tests\Feature;

/**
 * پوشش T1–T5 سند معماری (فاز ۱۴، ریسک شماره‌گذاری) برای M3:
 *   T1 — صحت فرمول شماره‌گذاری روی مثال واقعی کاربر (INQ-101633 → TC-200242-1633-R01)
 *   T2 — number_base فقط یک‌بار صادر می‌شود و بین Revisionهای بعدی ثابت می‌ماند
 *   T3 — Revisionِ منتشرشده غیرقابل‌ویرایش/غیرقابل‌انتشار مجدد است (Rule 11)
 *   T4 — دو Publish پیاپی هرگز یک سریال را دوبار نمی‌گیرند (سازگاری ترتیبی؛
 *        رقابت واقعیِ چندپردازه‌ای را PHPUnit تک‌رشته‌ای نمی‌تواند شبیه‌سازی کند —
 *        این محدودیت را صادقانه همینجا مستند می‌کنیم، نه چیزی که ادعا کند پوشش داده)
 *   T5 — Publish یک Revisionِ تازه، Revisionِ منتشرشده‌ی قبلی را Superseded می‌کند
 *
 * ⚠️ اجرای واقعی این فایل نیازمند vendor/ (composer install) است که در sandbox
 * توسعه‌ی این پروژه در دسترس نیست (packagist.org برای این محیط ۴۰۳ برمی‌گرداند).
 * کد به‌دقت و بر اساس اسکیمای واقعی مرور شده، اما هرگز per واقعاً اجرا نشده —
 * این محدودیت باید پیش از تکیه‌کردن به «سبز بودن» این تست‌ها برای کاربر روشن باشد.
 * پیش‌نیاز اجرا: php artisan test --testsuite=Feature (به phpunit.xml و
 * tests/TestCase.php/CreatesApplication.php اضافه‌شده در همین گام نیاز دارد).
 */

use App\Models\CaseModel;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\DocumentType;
use App\Services\Documents\DocumentNumberingService;
use App\Services\Documents\DocumentPublishService;
use App\Services\Documents\DocumentRevisionService;
use App\Services\NumberGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentPublishServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeCase(string $caseNumber = 'INQ-101633'): CaseModel
    {
        return CaseModel::create([
            'case_number' => $caseNumber,
            'title' => 'پرونده تست',
        ]);
    }

    protected function makeDraftDocument(CaseModel $case, string $typeKey = 'technical_proposal'): Document
    {
        $documentType = DocumentType::where('key', $typeKey)->firstOrFail();

        $doc = Document::create([
            'case_id' => $case->id,
            'type' => $typeKey,
            'document_type_id' => $documentType->id,
            // ستون قدیمی NOT NULL+UNIQUE — تا Publish یک مقدار موقت یکتا دارد (بند طراحی M1).
            'document_number' => 'DRAFT-'.uniqid(),
            'status' => Document::STATUS_DRAFT,
        ]);

        app(DocumentRevisionService::class)->createInitial($doc, null, [], null);

        return $doc->fresh(['currentRevision']);
    }

    public function test_t1_formatted_number_matches_users_worked_example(): void
    {
        // پیشوند و سریال شروع را دستی روی مقادیر مثال کاربر تنظیم می‌کنیم تا خروجی
        // دقیقاً TC-200242-1633-R01 باشد (سریال بعدی = start_number چون last_number=0 است).
        \Illuminate\Support\Facades\DB::table('number_sequences')->updateOrInsert(
            ['type' => 'technical_proposal'],
            ['prefix' => 'TC', 'pad_length' => 6, 'start_number' => 200242, 'last_number' => 200241, 'updated_at' => now(), 'created_at' => now()]
        );

        $case = $this->makeCase('INQ-101633');
        $doc = $this->makeDraftDocument($case, 'technical_proposal');

        $published = app(DocumentPublishService::class)->publish($doc->currentRevision, null);

        $this->assertSame('TC-200242-1633-R01', $published->formatted_number);
        $this->assertSame('TC-200242-1633', $doc->fresh()->number_base);
        $this->assertSame('TC-200242-1633-R01', $doc->fresh()->document_number);
    }

    public function test_t2_number_base_is_issued_once_and_stays_fixed_across_revisions(): void
    {
        $case = $this->makeCase('INQ-105001');
        $doc = $this->makeDraftDocument($case, 'technical_proposal');

        $rev1 = app(DocumentPublishService::class)->publish($doc->currentRevision, null);
        $baseAfterFirstPublish = $doc->fresh()->number_base;

        $rev2 = app(DocumentRevisionService::class)->createNextDraft($doc->fresh(), [], 'ویرایش دوم', null);
        $publishedRev2 = app(DocumentPublishService::class)->publish($rev2, null);

        $this->assertSame($baseAfterFirstPublish, $doc->fresh()->number_base);
        $this->assertSame($baseAfterFirstPublish.'-R01', $rev1->formatted_number);
        $this->assertSame($baseAfterFirstPublish.'-R02', $publishedRev2->formatted_number);
    }

    public function test_t3_published_revision_cannot_be_published_again_or_edited(): void
    {
        $case = $this->makeCase('INQ-105002');
        $doc = $this->makeDraftDocument($case, 'technical_proposal');

        $rev1 = app(DocumentPublishService::class)->publish($doc->currentRevision, null);

        $this->assertTrue($rev1->fresh()->is_locked);
        $this->assertFalse($rev1->fresh()->isEditable());

        $this->expectException(\RuntimeException::class);
        app(DocumentPublishService::class)->publish($rev1->fresh(), null);
    }

    public function test_t4_sequential_publishes_never_reuse_a_serial(): void
    {
        // محدودیت صادقانه: این فقط سازگاری *ترتیبی* را تضمین می‌کند (لاک ردیف
        // number_sequences در NumberGeneratorService::next() درست کار می‌کند).
        // رقابت واقعیِ دو پردازه‌ی هم‌زمان نیازمند یک اسکریپت جدا با دو پردازه‌ی
        // واقعی PHP است، نه PHPUnit تک‌رشته‌ای.
        $case1 = $this->makeCase('INQ-105003');
        $case2 = $this->makeCase('INQ-105004');
        $doc1 = $this->makeDraftDocument($case1, 'technical_proposal');
        $doc2 = $this->makeDraftDocument($case2, 'technical_proposal');

        $pub1 = app(DocumentPublishService::class)->publish($doc1->currentRevision, null);
        $pub2 = app(DocumentPublishService::class)->publish($doc2->currentRevision, null);

        $this->assertNotSame($pub1->formatted_number, $pub2->formatted_number);
        $this->assertNotSame($doc1->fresh()->number_base, $doc2->fresh()->number_base);
    }

    public function test_t5_publishing_a_new_revision_supersedes_the_previous_published_one(): void
    {
        $case = $this->makeCase('INQ-105005');
        $doc = $this->makeDraftDocument($case, 'technical_proposal');

        $rev1 = app(DocumentPublishService::class)->publish($doc->currentRevision, null);
        $this->assertSame(DocumentRevision::STATUS_PUBLISHED, $rev1->fresh()->status);
        $numberAfterFirstPublish = $rev1->fresh()->formatted_number;

        $rev2 = app(DocumentRevisionService::class)->createNextDraft($doc->fresh(), [], null, null);
        app(DocumentPublishService::class)->publish($rev2, null);

        // Rule 11: نسخه‌ی قبلی خودش هرگز تغییر نمی‌کند (formatted_number ثابت می‌ماند)،
        // فقط دیگر «فعلیِ منتشرشده» نیست.
        $this->assertSame(DocumentRevision::STATUS_SUPERSEDED, $rev1->fresh()->status);
        $this->assertSame($numberAfterFirstPublish, $rev1->fresh()->formatted_number);
        $this->assertSame($rev2->fresh()->id, $doc->fresh()->published_revision_id);
    }
}
