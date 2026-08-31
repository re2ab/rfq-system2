<?php

namespace App\Services\Documents\Converters;

use App\Services\Documents\Contracts\PdfConverter;

/**
 * درایور پیش‌فرض/راه‌فرار وقتی هیچ درایور واقعی PDF در دسترس نیست (cPanel
 * اشتراکی بدون exec و بدون سرویس ابری تنظیم‌شده). هیچ‌وقت خطا نمی‌دهد —
 * فقط null برمی‌گرداند تا لایه‌ی بالاتر کاربر را به دانلود مستقیم Word/Excel
 * هدایت کند، نه یک صفحه‌ی خطای فنی.
 */
class NullPdfConverter implements PdfConverter
{
    public function key(): string
    {
        return 'none';
    }

    public function label(): string
    {
        return 'غیرفعال — فقط دانلود Word/Excel';
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function diagnosis(): string
    {
        return 'درایور PDF روی این سرور تنظیم نشده؛ فایل واقعی Word/Excel هنوز قابل‌دانلود است.';
    }

    public function convert(string $sourcePath, string $destinationPath): ?string
    {
        return null;
    }
}
