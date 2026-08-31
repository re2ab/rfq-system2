<?php

namespace App\Services\Documents\Engines;

use App\Services\Documents\Contracts\TemplateEngine;
use App\Services\Documents\XlsxTemplateProcessor;

class XlsxEngine implements TemplateEngine
{
    public function extension(): string
    {
        return 'xlsx';
    }

    public function supports(string $fileType): bool
    {
        return strtolower($fileType) === 'xlsx';
    }

    public function placeholders(string $templatePath): array
    {
        return (new XlsxTemplateProcessor($templatePath))->placeholders();
    }

    public function render(
        string $templatePath,
        array $values,
        array $lineRows,
        string $lineMarker,
        string $destination,
        array $except = []
    ): string {
        $processor = new XlsxTemplateProcessor($templatePath);

        if ($lineMarker !== '') {
            $processor->cloneRow($lineMarker, $lineRows);
        }

        return $processor->setValues($values)->clearUnused($except)->saveAs($destination);
    }

    public function validate(string $templatePath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($templatePath) !== true) {
            return ['ok' => false, 'message' => 'فایل یک آرشیو معتبر نیست — احتمالاً .xls قدیمی است، نه .xlsx'];
        }

        $hasWorkbook = $zip->locateName('xl/workbook.xml') !== false;
        $zip->close();

        if (!$hasWorkbook) {
            return ['ok' => false, 'message' => 'xl/workbook.xml داخل فایل نیست. این یک فایل Excel معتبر نیست.'];
        }

        return ['ok' => true, 'message' => 'فایل Excel معتبر است.'];
    }
}
