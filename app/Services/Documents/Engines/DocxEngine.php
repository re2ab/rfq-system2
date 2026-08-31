<?php

namespace App\Services\Documents\Engines;

use App\Services\Documents\Contracts\TemplateEngine;
use App\Services\Documents\DocxTemplateProcessor;

class DocxEngine implements TemplateEngine
{
    public function extension(): string
    {
        return 'docx';
    }

    public function supports(string $fileType): bool
    {
        return strtolower($fileType) === 'docx';
    }

    public function placeholders(string $templatePath): array
    {
        return (new DocxTemplateProcessor($templatePath))->placeholders();
    }

    public function render(
        string $templatePath,
        array $values,
        array $lineRows,
        string $lineMarker,
        string $destination,
        array $except = []
    ): string {
        $processor = new DocxTemplateProcessor($templatePath);

        if ($lineMarker !== '') {
            $processor->cloneRow($lineMarker, $lineRows);
        }

        return $processor->setValues($values)->clearUnused($except)->saveAs($destination);
    }

    public function validate(string $templatePath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($templatePath) !== true) {
            return ['ok' => false, 'message' => 'فایل یک آرشیو معتبر نیست — احتمالاً .doc قدیمی است، نه .docx'];
        }

        $hasDocument = $zip->locateName('word/document.xml') !== false;
        $zip->close();

        if (!$hasDocument) {
            return ['ok' => false, 'message' => 'word/document.xml داخل فایل نیست. این یک فایل Word معتبر نیست.'];
        }

        return ['ok' => true, 'message' => 'فایل Word معتبر است.'];
    }
}
