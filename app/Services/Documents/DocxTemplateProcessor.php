<?php

namespace App\Services\Documents;

/**
 * Zero-dependency .docx template processor.
 *
 * A .docx is a ZIP of XML parts. Word freely splits a single logical string
 * across several <w:r> runs (spell-check state, formatting, RSID marks), so a
 * placeholder typed as {{case_number}} is commonly stored as three runs:
 *
 *     <w:t>{{</w:t> <w:t>case_number</w:t> <w:t>}}</w:t>
 *
 * A naive str_replace therefore silently matches nothing. This class first
 * NORMALIZES each paragraph so every placeholder occupies exactly one <w:t>
 * node (keeping the formatting of the run where the placeholder starts, and
 * leaving all other runs untouched), then performs replacement.
 *
 * Because the original file is only ever edited at the text level, every bit
 * of the user's Word formatting — styles, letterhead, headers/footers, images,
 * page setup, fonts — is preserved exactly.
 */
class DocxTemplateProcessor
{
    /** Parts that may contain placeholders. */
    protected const TEXT_PARTS = '#^word/(document|header\d*|footer\d*|footnotes|endnotes)\.xml$#';

    protected string $workFile;
    protected \ZipArchive $zip;

    /** @var array<string,string> partName => xml */
    protected array $parts = [];

    public function __construct(protected string $templatePath)
    {
        if (!is_file($templatePath)) {
            throw new \RuntimeException("قالب پیدا نشد: {$templatePath}");
        }

        $this->workFile = tempnam(sys_get_temp_dir(), 'rfqdocx') . '.docx';
        if (!copy($templatePath, $this->workFile)) {
            throw new \RuntimeException('کپی فایل قالب ممکن نشد.');
        }

        $this->zip = new \ZipArchive();
        if ($this->zip->open($this->workFile) !== true) {
            throw new \RuntimeException('فایل قالب یک .docx معتبر نیست.');
        }

        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $name = $this->zip->getNameIndex($i);
            if (preg_match(self::TEXT_PARTS, $name)) {
                $this->parts[$name] = $this->normalize((string) $this->zip->getFromName($name), '{', self::PLACEHOLDER_PATTERN);
            }
        }
    }

    /** الگوی پیش‌فرض placeholderها — به‌صورت ثابت تا هم در normalize هم جاهای دیگر یکسان بماند. */
    protected const PLACEHOLDER_PATTERN = '#\{\{[A-Za-z0-9_.\-]{1,120}\}\}#';

    // ---------------------------------------------------------------- normalize

    /**
     * Rebuild every paragraph so each occurrence of $pattern lives in a single
     * <w:t>. $guardNeedle is a cheap pre-filter (skip paragraphs that can't
     * possibly contain a match) — '{' for the normal placeholder pattern, or
     * (M40-b) the literal search text itself when restoring a placeholder
     * from plain text after carryForward().
     */
    protected function normalize(string $xml, string $guardNeedle, string $pattern): string
    {
        return (string) preg_replace_callback(
            '#<w:p(?:\s[^>]*)?>.*?</w:p>#s',
            fn (array $m) => $this->normalizeParagraph($m[0], $guardNeedle, $pattern),
            $xml
        );
    }

    protected function normalizeParagraph(string $p, string $guardNeedle, string $pattern): string
    {
        if (!str_contains($p, $guardNeedle)) {
            return $p;
        }

        if (!preg_match_all('#<w:t(?:\s[^>]*)?>(.*?)</w:t>#s', $p, $m, PREG_OFFSET_CAPTURE)) {
            return $p;
        }

        // Concatenated visible text + the byte range each node occupies in it.
        $texts = [];
        $ranges = [];
        $cursor = 0;
        foreach ($m[1] as $i => [$text, $_off]) {
            $texts[$i] = $text;
            $len = strlen($text);
            $ranges[$i] = [$cursor, $cursor + $len];
            $cursor += $len;
        }
        $joined = implode('', $texts);

        // Placeholder/number chars are plain ASCII, never XML-escaped, so byte
        // offsets in the escaped string map 1:1 onto the logical string.
        if (!preg_match_all($pattern, $joined, $pm, PREG_OFFSET_CAPTURE)) {
            return $p;
        }

        // Collect per-node edits: the node where a placeholder STARTS receives
        // the whole placeholder; the tail nodes lose their fragment of it.
        $edits = [];
        foreach ($pm[0] as [$ph, $start]) {
            $end = $start + strlen($ph);
            foreach ($ranges as $i => [$ns, $ne]) {
                if ($ns >= $end || $ne <= $start) {
                    continue;
                }
                $edits[$i][] = [
                    max($start, $ns) - $ns,
                    min($end, $ne) - $ns,
                    $ns <= $start ? $ph : '',
                ];
            }
        }

        if (!$edits) {
            return $p;
        }

        // Apply node edits right-to-left so earlier offsets stay valid.
        foreach ($edits as $i => $nodeEdits) {
            usort($nodeEdits, fn ($a, $b) => $b[0] <=> $a[0]);
            $text = $texts[$i];
            foreach ($nodeEdits as [$ls, $le, $repl]) {
                $text = substr_replace($text, $repl, $ls, $le - $ls);
            }
            $texts[$i] = $text;
        }

        // Rewrite the <w:t> nodes, again right-to-left.
        for ($i = count($m[0]) - 1; $i >= 0; $i--) {
            if (!isset($edits[$i])) {
                continue;
            }
            [$whole, $offset] = $m[0][$i];
            $p = substr_replace(
                $p,
                '<w:t xml:space="preserve">' . $texts[$i] . '</w:t>',
                $offset,
                strlen($whole)
            );
        }

        return $p;
    }

    // ------------------------------------------------------------------ values

    /** Replace {{key}} everywhere. Newlines become real Word line breaks. */
    public function setValue(string $key, string|int|float|null $value): static
    {
        $needle = '{{' . $key . '}}';
        $xml = $this->escape((string) $value);
        foreach ($this->parts as $name => $part) {
            if (str_contains($part, $needle)) {
                $this->parts[$name] = str_replace($needle, $xml, $part);
            }
        }
        return $this;
    }

    /** @param array<string,string|int|float|null> $values */
    public function setValues(array $values): static
    {
        foreach ($values as $k => $v) {
            $this->setValue($k, $v);
        }
        return $this;
    }

    /**
     * Repeat the table row that contains {{$marker}} once per data row.
     *
     * The template holds ONE specimen row using {{line_desc}}, {{line_qty}}…
     * Its borders, shading, fonts and column widths are cloned verbatim.
     *
     * @param array<int,array<string,string|int|float|null>> $rows
     */
    public function cloneRow(string $marker, array $rows): static
    {
        $needle = '{{' . $marker . '}}';

        foreach ($this->parts as $name => $part) {
            if (!str_contains($part, $needle)) {
                continue;
            }
            if (!preg_match_all('#<w:tr(?:\s[^>]*)?>.*?</w:tr>#s', $part, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            for ($i = count($m[0]) - 1; $i >= 0; $i--) {
                [$rowXml, $offset] = $m[0][$i];
                if (!str_contains($rowXml, $needle)) {
                    continue;
                }

                $built = '';
                foreach ($rows as $data) {
                    $clone = $rowXml;
                    foreach ($data as $k => $v) {
                        $clone = str_replace('{{' . $k . '}}', $this->escape((string) $v), $clone);
                    }
                    // Any placeholder the caller did not supply is blanked, not left visible.
                    $clone = preg_replace('#\{\{[A-Za-z0-9_.\-]{1,120}\}\}#', '', $clone);
                    $built .= $clone;
                }

                $part = substr_replace($part, $built, $offset, strlen($rowXml));
            }

            $this->parts[$name] = $part;
        }

        return $this;
    }

    /**
     * Placeholders left unfilled would otherwise print as literal {{x}}.
     *
     * @param array<int,string> $except کلیدهایی که عمداً دست‌نخورده باقی
     *   بمانند — مثلاً {{document.number}} (M36) که مقدارش هنوز در زمانِ
     *   ساختِ سند وجود ندارد و قرار است بعداً (در Publish) با یک بازِ ذخیره‌ی
     *   جدا از همین کلاس پر شود؛ اگر همین‌جا پاک شود، دیگر هیچ‌وقت جایی برای
     *   نوشتنِ آن مقدار باقی نمی‌ماند.
     */
    public function clearUnused(array $except = []): static
    {
        if ($except === []) {
            foreach ($this->parts as $name => $part) {
                $this->parts[$name] = (string) preg_replace('#\{\{[A-Za-z0-9_.\-]{1,120}\}\}#', '', $part);
            }
            return $this;
        }
        $exceptSet = array_flip($except);
        foreach ($this->parts as $name => $part) {
            $this->parts[$name] = (string) preg_replace_callback(
                '#\{\{([A-Za-z0-9_.\-]{1,120})\}\}#',
                fn (array $m) => isset($exceptSet[$m[1]]) ? $m[0] : '',
                $part
            );
        }
        return $this;
    }

    /** @return array<int,string> placeholder keys still present in the template */
    public function placeholders(): array
    {
        $found = [];
        foreach ($this->parts as $part) {
            if (preg_match_all('#\{\{([A-Za-z0-9_.\-]{1,120})\}\}#', $part, $m)) {
                $found = array_merge($found, $m[1]);
            }
        }
        return array_values(array_unique($found));
    }

    /**
     * M40-b: مکملِ حذفِ برگشت‌ناپذیرِ placeholder توسطِ setValue()/clearUnused().
     * وقتی carryForward() فایلِ یک Revisionِ قبلاً منتشرشده را برای Draft
     * بعدی کپی می‌کند، جای {{document.number}} دیگر متنِ خامِ شماره‌ی رسمیِ
     * قبلی نشسته — این متد همان متنِ ثابت را (حتی اگر Word/ONLYOFFICE آن را
     * بینِ چند run پخش کرده باشد، با همان الگوریتمِ normalize بالا) پیدا و
     * دوباره به {{$key}} برمی‌گرداند، تا برای این Draft هم مکانیزمِ
     * Publish/Stamp کار کند. بهترین‌تلاش است: اگر متن پیدا نشود (مثلاً کاربر
     * دستی آن را ویرایش کرده)، بی‌سروصدا و بدونِ خطا هیچ کاری نمی‌کند.
     */
    public function restoreLiteralAsPlaceholder(string $literalValue, string $key): static
    {
        $literalValue = trim($literalValue);
        if ($literalValue === '') {
            return $this;
        }

        $pattern = '#' . preg_quote($literalValue, '#') . '#';
        $placeholder = '{{' . $key . '}}';

        foreach ($this->parts as $name => $part) {
            $merged = $this->normalize($part, $literalValue, $pattern);
            $this->parts[$name] = (string) preg_replace($pattern, $placeholder, $merged);
        }

        return $this;
    }

    // ------------------------------------------------------------------- output

    public function saveAs(string $destination): string
    {
        foreach ($this->parts as $name => $part) {
            $this->zip->addFromString($name, $part);
        }
        $this->zip->close();

        $dir = dirname($destination);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!rename($this->workFile, $destination) && !copy($this->workFile, $destination)) {
            throw new \RuntimeException('ذخیره فایل خروجی ممکن نشد.');
        }
        @unlink($this->workFile);

        return $destination;
    }

    protected function escape(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $parts = array_map(
            fn ($chunk) => htmlspecialchars($chunk, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            explode("\n", $value)
        );
        return implode('</w:t><w:br/><w:t xml:space="preserve">', $parts);
    }

    public function __destruct()
    {
        if (is_file($this->workFile)) {
            @unlink($this->workFile);
        }
    }
}
