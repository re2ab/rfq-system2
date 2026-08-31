<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentLine;

/**
 * همگام‌سازی ردیف‌های اقلام یک سند + محاسبه‌ی مجدد جمع‌ها — استخراج‌شده از
 * DocumentController::syncLines() (M0-M3) تا هم مسیر قدیمی (محتوای HTML) و
 * هم مسیر جدید «سند از قالب واقعی» (M4) از یک منطق واحد استفاده کنند، نه دو
 * نسخه‌ی موازی که می‌توانند از هم جدا بیفتند.
 */
class DocumentLineService
{
    public function sync(Document $doc, array $lines, string|int|float|null $vatPercent = null): void
    {
        if (!$doc->typeSupportsLines()) {
            return;
        }

        $doc->lines()->delete();
        $order = 0;
        $has = false;

        foreach ($lines as $row) {
            $desc = trim((string) ($row['description'] ?? ''));
            if ($desc === '') {
                continue;
            }
            $has = true;
            $qty = (float) ($row['quantity'] ?? 1);
            $price = (float) ($row['unit_price'] ?? 0);
            $doc->lines()->create([
                'sort_order' => $order++,
                'description' => $desc,
                'unit' => $row['unit'] ?? 'عدد',
                'quantity' => $qty,
                'unit_price' => $price,
                'line_total' => DocumentLine::calcTotal($qty, $price),
            ]);
        }

        if ($has) {
            if ($vatPercent !== null && $vatPercent !== '') {
                $doc->vat_percent = (float) $vatPercent;
                $doc->save();
            }
            $doc->recalculateFromLines();
        }
    }
}
