<?php

namespace App\Services\Documents\Contracts;

/**
 * Turns a rendered .docx/.xlsx into a PDF.
 *
 * Deployment targets differ wildly — shared cPanel has no system binaries, a VPS
 * or container can run LibreOffice — so conversion is a swappable driver rather
 * than a hard dependency.
 */
interface PdfConverter
{
    /** Machine key stored in settings: "libreoffice", "mpdf", "none". */
    public function key(): string;

    /** Human label shown in the settings screen. */
    public function label(): string;

    /** Whether this driver can actually run on this server right now. */
    public function isAvailable(): bool;

    /** One line explaining why it is or is not available. */
    public function diagnosis(): string;

    /**
     * @return string|null absolute path to the PDF, or null when unsupported
     *
     * @throws \RuntimeException on a real conversion failure
     */
    public function convert(string $sourcePath, string $destinationPath): ?string;
}
