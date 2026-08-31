<?php

namespace App\Services\OnlyOffice;

/**
 * پیاده‌سازی حداقلی JWT (فقط HS256) — عمداً بدون کتابخانه‌ی جدید Composer
 * (firebase/php-jwt و مانند آن در composer.json پروژه نیست و طبق سیاست کل
 * این پروژه، بدون اجازه‌ی صریح کاربر پکیج جدید اضافه نمی‌شود). قرارداد
 * ONLYOFFICE فقط HS256 با یک secret مشترک لازم دارد — چیز بیشتری هم لازم
 * نیست.
 */
class JwtService
{
    public static function encode(array $payload, string $secret): string
    {
        $header = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $body = self::b64(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $signature = self::b64(hash_hmac('sha256', "{$header}.{$body}", $secret, true));

        return "{$header}.{$body}.{$signature}";
    }

    /** @return array<string,mixed>|null در صورت نامعتبر بودن امضا یا ساختار، null. */
    public static function decode(string $jwt, string $secret): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $body, $signature] = $parts;

        $expected = self::b64(hash_hmac('sha256', "{$header}.{$body}", $secret, true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $decoded = json_decode(self::unb64($body), true);

        return is_array($decoded) ? $decoded : null;
    }

    protected static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    protected static function unb64(string $encoded): string
    {
        $encoded = strtr($encoded, '-_', '+/');
        $pad = strlen($encoded) % 4;
        if ($pad) {
            $encoded .= str_repeat('=', 4 - $pad);
        }

        return (string) base64_decode($encoded);
    }
}
