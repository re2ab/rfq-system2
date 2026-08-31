<?php

namespace App\Services\Documents;

use App\Models\CaseModel;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\DocumentType;
use App\Services\AuditLogger;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Facades\DB;

/**
 * M9پ: Import گروهی اسناد قدیمی (Migration) — ثبت شماره‌ها/فراداده‌ی اسناد
 * سیستم قبلی به‌عنوان Document+DocumentRevision منتشرشده در RFQ-Core، تا هم
 * سابقه در سیستم جدید قابل جست‌وجو باشد و هم شماره‌گذاری خودکار از این پس
 * با شماره‌های قدیمی برخورد نکند (نیاز اصلی «تداوم شماره‌گذاری» که از همان
 * ابتدای این پروژه مطرح شد).
 *
 * فایل واقعی سند اینجا منتقل نمی‌شود (اغلب اسناد قدیمی نسخه‌ی دیجیتال یکدست
 * ندارند) — فقط رکورد رسمی+شماره ثبت می‌شود؛ اگر بعداً فایل واقعی آن پیدا شد،
 * از همان صفحه‌ی «آوردن فایل موجود» (M9الف) روی همین سند آپلود می‌شود.
 *
 * پیشرَوی شمارنده (number_sequences.last_number) فقط رو‌به‌جلوست — هیچ‌وقت
 * شمارنده‌ای که از قبل جلوتر است را عقب نمی‌کشد.
 */
class DocumentLegacyImportService
{
    /** @return array{ok:bool,message:string,created?:int,skipped?:int,errors?:array<int,string>} */
    public function importFromCsv(string $raw, ?int $userId): array
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        } elseif (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16');
        } elseif (!mb_check_encoding($raw, 'UTF-8')) {
            foreach (['CP1256', 'ISO-8859-6', 'ISO-8859-1'] as $enc) {
                if (!in_array($enc, mb_list_encodings(), true)) {
                    continue;
                }
                $tmp = @mb_convert_encoding($raw, 'UTF-8', $enc);
                if (is_string($tmp) && $tmp !== '' && mb_check_encoding($tmp, 'UTF-8')) {
                    $raw = $tmp;
                    break;
                }
            }
        }

        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $lines = array_values(array_filter(explode("\n", $raw), fn ($l) => trim($l) !== ''));
        if (count($lines) < 2) {
            return ['ok' => false, 'message' => 'فایل خالی است یا فقط عنوان ستون‌ها را دارد.'];
        }

        $delimiter = str_contains($lines[0], "\t") ? "\t" : (substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',');
        $headerRow = str_getcsv($lines[0], $delimiter);
        $headers = array_map(fn ($h) => $this->normalizeHeader((string) $h), $headerRow);
        $map = $this->mapHeaders($headers);

        if (!isset($map['case_number']) || !isset($map['document_type']) || !isset($map['document_number'])) {
            $seen = implode(' | ', array_map(fn ($h) => $h === '' ? '(خالی)' : $h, $headers));
            return ['ok' => false, 'message' => 'ستون‌های الزامی (شماره پرونده، نوع سند، شماره سند) پیدا نشد. عنوان‌های خوانده‌شده: ['.$seen.']'];
        }

        $documentTypes = DocumentType::all()->keyBy(fn ($t) => mb_strtolower(trim($t->key)));
        $documentTypesByName = DocumentType::all()->keyBy(fn ($t) => mb_strtolower(trim($t->name_fa)));

        $created = 0;
        $skipped = 0;
        $errors = [];
        /** @var array<string,int> $maxSerialByType */
        $maxSerialByType = [];

        for ($r = 1; $r < count($lines); $r++) {
            $row = str_getcsv($lines[$r], $delimiter);
            if ($this->rowEmpty($row)) {
                continue;
            }

            $get = function (string $key) use ($map, $row) {
                if (!isset($map[$key])) {
                    return null;
                }
                $i = $map[$key];
                return isset($row[$i]) ? trim((string) $row[$i]) : null;
            };

            $caseNumber = $get('case_number') ?: '';
            $typeRaw = $get('document_type') ?: '';
            $documentNumber = $get('document_number') ?: '';

            if ($caseNumber === '' || $typeRaw === '' || $documentNumber === '') {
                $skipped++;
                $errors[] = 'ردیف '.($r + 1).': شماره پرونده/نوع سند/شماره سند خالی است.';
                continue;
            }

            $case = CaseModel::where('case_number', $caseNumber)->first();
            if (!$case) {
                $skipped++;
                $errors[] = 'ردیف '.($r + 1).": پرونده‌ی «{$caseNumber}» پیدا نشد.";
                continue;
            }

            $typeKey = mb_strtolower(trim($typeRaw));
            $documentType = $documentTypes->get($typeKey) ?: $documentTypesByName->get($typeKey);
            if (!$documentType) {
                $skipped++;
                $errors[] = 'ردیف '.($r + 1).": نوع سند «{$typeRaw}» در فهرست انواع سند پیدا نشد.";
                continue;
            }

            $revisionNumber = max(1, (int) ($get('revision_number') ?: 1));
            $title = $get('title') ?: null;
            $serialRaw = $get('serial');
            $publishedDateRaw = $get('published_date');

            try {
                DB::transaction(function () use (
                    $case, $documentType, $documentNumber, $revisionNumber, $title, $userId, $publishedDateRaw
                ) {
                    $publishedAt = now();
                    if ($publishedDateRaw) {
                        try {
                            $publishedAt = \Illuminate\Support\Carbon::parse($publishedDateRaw);
                        } catch (\Throwable) {
                            // تاریخ نامعتبر → همان زمان Import
                        }
                    }

                    $doc = Document::create([
                        'case_id' => $case->id,
                        'type' => $documentType->key,
                        'document_type_id' => $documentType->id,
                        'document_number' => $documentNumber,
                        'number_base' => $documentNumber,
                        'status' => Document::STATUS_PUBLISHED,
                        'title' => $title,
                    ]);

                    $revision = DocumentRevision::create([
                        'document_id' => $doc->id,
                        'revision_number' => $revisionNumber,
                        'status' => DocumentRevision::STATUS_PUBLISHED,
                        'formatted_number' => $documentNumber,
                        'created_by' => $userId,
                        'is_locked' => true,
                        'published_by' => $userId,
                        'published_at' => $publishedAt,
                    ]);

                    $doc->update([
                        'current_revision_id' => $revision->id,
                        'published_revision_id' => $revision->id,
                    ]);

                    AuditLogger::log('document_legacy_imported', 'document', $doc->id, [
                        'source' => 'legacy_migration',
                        'legacy_number' => $documentNumber,
                    ]);
                });
                $created++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = 'ردیف '.($r + 1).': خطا در ثبت — '.$e->getMessage();
                continue;
            }

            $serial = null;
            if ($serialRaw !== null && $serialRaw !== '' && is_numeric($serialRaw)) {
                $serial = (int) $serialRaw;
            } elseif (preg_match('/(\d+)/', $documentNumber, $m)) {
                $serial = (int) $m[1];
            }
            if ($serial !== null) {
                $key = $documentType->key;
                $maxSerialByType[$key] = max($maxSerialByType[$key] ?? 0, $serial);
            }
        }

        foreach ($maxSerialByType as $typeKey => $maxSerial) {
            $this->advanceSequence($typeKey, $maxSerial);
        }

        $msg = "ایمپورت اسناد قدیمی انجام شد. ثبت‌شده: {$created} · رد شده: {$skipped}";
        if ($errors) {
            $msg .= ' | نمونه خطاها: '.implode(' — ', array_slice($errors, 0, 5));
        }

        return ['ok' => true, 'message' => $msg, 'created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    /** شمارنده‌ی یک نوع سند را فقط رو‌به‌جلو (هرگز عقب نه) تا حداقل $minLast جلو می‌برد. */
    protected function advanceSequence(string $typeKey, int $minLast): void
    {
        $defaults = NumberGeneratorService::DEFAULTS[$typeKey] ?? ['prefix' => strtoupper($typeKey), 'pad' => 6];
        $current = DB::table('number_sequences')->where('type', $typeKey)->first();
        $currentLast = $current ? (int) $current->last_number : 0;

        if ($minLast <= $currentLast) {
            return;
        }

        DB::table('number_sequences')->updateOrInsert(
            ['type' => $typeKey],
            [
                'prefix' => $current->prefix ?? $defaults['prefix'],
                'pad_length' => $current->pad_length ?? $defaults['pad'],
                'start_number' => $current->start_number ?? 1,
                'last_number' => $minLast,
                'updated_at' => now(),
                'created_at' => $current->created_at ?? now(),
            ]
        );
    }

    protected function normalizeHeader(string $h): string
    {
        $h = trim($h);
        $h = str_replace("\xE2\x80\x8C", '', $h); // نیم‌فاصله/zero-width
        return mb_strtolower($h);
    }

    /** @return array<string,int> */
    protected function mapHeaders(array $headers): array
    {
        $aliases = [
            'case_number' => ['شماره پرونده', 'شماره‌ پرونده', 'پرونده', 'case_number', 'case', 'case number'],
            'document_type' => ['نوع سند', 'نوع', 'document_type', 'type'],
            'document_number' => ['شماره سند', 'شماره‌ سند', 'شماره', 'document_number', 'number', 'legacy_number'],
            'revision_number' => ['شماره نسخه', 'نسخه', 'revision_number', 'revision'],
            'serial' => ['سریال', 'serial'],
            'title' => ['عنوان', 'title'],
            'published_date' => ['تاریخ', 'تاریخ انتشار', 'published_date', 'date'],
        ];

        $map = [];
        foreach ($headers as $i => $h) {
            foreach ($aliases as $key => $names) {
                if (isset($map[$key])) {
                    continue;
                }
                if (in_array($h, array_map('mb_strtolower', $names), true)) {
                    $map[$key] = $i;
                }
            }
        }
        return $map;
    }

    protected function rowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }
        return true;
    }
}
