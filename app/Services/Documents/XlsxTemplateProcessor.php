<?php

namespace App\Services\Documents;

/**
 * Zero-dependency .xlsx template processor.
 *
 * An .xlsx stores cell text in two places:
 *   1. xl/sharedStrings.xml — a pool of <si> entries; cells reference them by
 *      index (<c t="s"><v>10</v></c>). This is where most placeholders land.
 *   2. Inline strings inside the sheet (<c t="inlineStr"><is><t>…</t></is></c>).
 *
 * A cell with mixed formatting splits its <si> into <r> runs exactly the way
 * Word splits paragraphs, so the same normalisation applies.
 *
 * Row repetition rewrites cloned cells as INLINE strings rather than shared
 * ones. That sidesteps having to renumber the shared-string pool, which is the
 * usual source of corrupted output.
 *
 * KNOWN LIMIT: formulas in rows below a repeated block are not re-pointed when
 * rows shift (e.g. =SUM(D5:D5) stays as written). Put totals in the template as
 * {{gross_amount}} placeholders rather than formulas.
 */
class XlsxTemplateProcessor
{
    protected string $workFile;
    protected \ZipArchive $zip;

    /** @var array<string,string> */
    protected array $parts = [];

    protected string $sharedStringsPart = 'xl/sharedStrings.xml';

    public function __construct(protected string $templatePath)
    {
        if (!is_file($templatePath)) {
            throw new \RuntimeException("قالب پیدا نشد: {$templatePath}");
        }

        $this->workFile = tempnam(sys_get_temp_dir(), 'rfqxlsx') . '.xlsx';
        if (!copy($templatePath, $this->workFile)) {
            throw new \RuntimeException('کپی فایل قالب ممکن نشد.');
        }

        $this->zip = new \ZipArchive();
        if ($this->zip->open($this->workFile) !== true) {
            throw new \RuntimeException('فایل قالب یک .xlsx معتبر نیست.');
        }

        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $name = $this->zip->getNameIndex($i);
            if ($name === $this->sharedStringsPart) {
                $this->parts[$name] = $this->normalizeSharedStrings((string) $this->zip->getFromName($name), '{', self::PLACEHOLDER_PATTERN);
            } elseif (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $this->parts[$name] = (string) $this->zip->getFromName($name);
            }
        }
    }

    /** الگوی پیش‌فرض placeholderها. */
    protected const PLACEHOLDER_PATTERN = '#\{\{[A-Za-z0-9_.\-]{1,120}\}\}#';

    // ---------------------------------------------------------- normalisation

    /**
     * Collapse each <si>'s runs so a match of $pattern never straddles two
     * <t> nodes. $guardNeedle is a cheap pre-filter — '{' for the normal
     * placeholder pattern, or (M40-b) the literal search text itself when
     * restoring a placeholder from plain text after carryForward().
     */
    protected function normalizeSharedStrings(string $xml, string $guardNeedle, string $pattern): string
    {
        return (string) preg_replace_callback('#<si>(.*?)</si>#s', function (array $m) use ($guardNeedle, $pattern) {
            $inner = $m[1];
            if (!str_contains($inner, $guardNeedle)) {
                return $m[0];
            }
            if (!preg_match_all('#<t(?:\s[^>]*)?>(.*?)</t>#s', $inner, $tm)) {
                return $m[0];
            }
            $joined = implode('', $tm[1]);
            if (!preg_match($pattern, $joined)) {
                return $m[0];
            }
            // A placeholder/matched cell is a merge target, not styled prose —
            // collapsing it to a single run is both safe and what's expected.
            return '<si><t xml:space="preserve">' . $joined . '</t></si>';
        }, $xml);
    }

    // ------------------------------------------------------------------ values

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
     * Repeat the sheet row holding {{$marker}} once per data row.
     *
     * @param array<int,array<string,string|int|float|null>> $rows
     */
    public function cloneRow(string $marker, array $rows): static
    {
        $index = $this->sharedStringIndexFor($marker);

        foreach ($this->parts as $name => $part) {
            if (!preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                continue;
            }
            if (!preg_match_all('#<row[^>]*\sr="(\d+)"[^>]*>.*?</row>#s', $part, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                continue;
            }

            $targetIdx = null;
            foreach ($m as $i => $set) {
                if ($this->rowContainsPlaceholder($set[0][0], $marker, $index)) {
                    $targetIdx = $i;
                    break;
                }
            }
            if ($targetIdx === null) {
                continue;
            }

            $templateRow = $m[$targetIdx][0][0];
            $templateRowNo = (int) $m[$targetIdx][1][0];
            $shift = max(0, count($rows) - 1);

            // Build the replacement block, renumbering as we go.
            $block = '';
            foreach (array_values($rows) as $n => $data) {
                $block .= $this->buildRow($templateRow, $templateRowNo + $n, $data);
            }
            if (!$rows) {
                $block = '';           // no lines → drop the specimen row entirely
                $shift = -1;
            }

            // Rebuild the sheet: rows before, the block, then rows after shifted down.
            $out = substr($part, 0, $m[$targetIdx][0][1]);
            $out .= $block;
            $tailStart = $m[$targetIdx][0][1] + strlen($templateRow);
            $tail = substr($part, $tailStart);
            if ($shift !== 0) {
                $tail = $this->shiftRows($tail, $shift);
            }
            $out .= $tail;

            $this->parts[$name] = $this->fixDimension($out);
        }

        return $this;
    }

    protected function rowContainsPlaceholder(string $rowXml, string $marker, ?int $sharedIndex): bool
    {
        if (str_contains($rowXml, '{{' . $marker . '}}')) {
            return true;
        }
        if ($sharedIndex === null) {
            return false;
        }
        return (bool) preg_match('#<c[^>]*t="s"[^>]*>\s*<v>' . $sharedIndex . '</v>#', $rowXml);
    }

    /** Clone one row at $rowNo, replacing every cell that holds a marker. */
    protected function buildRow(string $templateRow, int $rowNo, array $data): string
    {
        $row = preg_replace('#(<row[^>]*\sr=")\d+(")#', '${1}' . $rowNo . '${2}', $templateRow, 1);

        $row = (string) preg_replace_callback('#<c\s([^>]*)>(.*?)</c>#s', function (array $m) use ($rowNo, $data) {
            $attrs = $m[1];
            $body = $m[2];

            // Re-anchor the cell reference to the new row.
            $attrs = preg_replace('#\br="([A-Z]+)\d+"#', 'r="$1' . $rowNo . '"', $attrs);

            $key = $this->cellPlaceholderKey($attrs, $body);
            if ($key === null || !array_key_exists($key, $data)) {
                return '<c ' . $attrs . '>' . $body . '</c>';
            }

            $value = $data[$key];

            // Numeric values become real numbers so Excel can sum and format them.
            if (is_int($value) || is_float($value) || (is_string($value) && $this->isNumeric($value))) {
                $attrs = preg_replace('#\st="[^"]*"#', '', $attrs);
                return '<c ' . $attrs . '><v>' . $this->numeric($value) . '</v></c>';
            }

            $attrs = preg_replace('#\st="[^"]*"#', '', $attrs) . ' t="inlineStr"';
            return '<c ' . $attrs . '><is><t xml:space="preserve">'
                . $this->escape((string) $value) . '</t></is></c>';
        }, $row);

        // Any marker the caller did not supply is blanked rather than printed.
        return (string) preg_replace('#\{\{[A-Za-z0-9_.\-]{1,120}\}\}#', '', $row);
    }

    /** Resolve which placeholder key a cell carries, shared or inline. */
    protected function cellPlaceholderKey(string $attrs, string $body): ?string
    {
        if (preg_match('#\{\{([A-Za-z0-9_.\-]{1,120})\}\}#', $body, $m)) {
            return $m[1];
        }
        if (str_contains($attrs, 't="s"') && preg_match('#<v>(\d+)</v>#', $body, $m)) {
            $text = $this->sharedStringAt((int) $m[1]);
            if ($text !== null && preg_match('#\{\{([A-Za-z0-9_.\-]{1,120})\}\}#', $text, $mm)) {
                return $mm[1];
            }
        }
        return null;
    }

    protected function shiftRows(string $tail, int $shift): string
    {
        $tail = (string) preg_replace_callback('#<row([^>]*)\sr="(\d+)"#', function (array $m) use ($shift) {
            return '<row' . $m[1] . ' r="' . ((int) $m[2] + $shift) . '"';
        }, $tail);

        return (string) preg_replace_callback('#<c([^>]*)\sr="([A-Z]+)(\d+)"#', function (array $m) use ($shift) {
            return '<c' . $m[1] . ' r="' . $m[2] . ((int) $m[3] + $shift) . '"';
        }, $tail);
    }

    protected function fixDimension(string $sheet): string
    {
        if (!preg_match_all('#<row[^>]*\sr="(\d+)"#', $sheet, $m)) {
            return $sheet;
        }
        $last = max(array_map('intval', $m[1]));
        return (string) preg_replace_callback('#<dimension\s+ref="([A-Z]+)\d+:([A-Z]+)\d+"\s*/>#', function (array $d) use ($last) {
            return '<dimension ref="' . $d[1] . '1:' . $d[2] . $last . '"/>';
        }, $sheet);
    }

    // ---------------------------------------------------------- shared strings

    /** @var array<int,string>|null */
    protected ?array $sharedCache = null;

    protected function sharedStringAt(int $index): ?string
    {
        if ($this->sharedCache === null) {
            $this->sharedCache = [];
            $xml = $this->parts[$this->sharedStringsPart] ?? '';
            if (preg_match_all('#<si>(.*?)</si>#s', $xml, $m)) {
                foreach ($m[1] as $i => $si) {
                    preg_match_all('#<t(?:\s[^>]*)?>(.*?)</t>#s', $si, $tm);
                    $this->sharedCache[$i] = implode('', $tm[1] ?? []);
                }
            }
        }
        return $this->sharedCache[$index] ?? null;
    }

    protected function sharedStringIndexFor(string $marker): ?int
    {
        $needle = '{{' . $marker . '}}';
        $i = 0;
        while (($s = $this->sharedStringAt($i)) !== null) {
            if (str_contains($s, $needle)) {
                return $i;
            }
            $i++;
        }
        return null;
    }

    // ------------------------------------------------------------------ output

    /**
     * @param array<int,string> $except کلیدهایی که عمداً دست‌نخورده باقی
     *   بمانند — مثلاً {{document.number}} (M36)، هم‌الگو با
     *   DocxTemplateProcessor::clearUnused().
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

    /** @return array<int,string> */
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
     * M40-b: مکملِ حذفِ برگشت‌ناپذیرِ placeholder توسطِ setValue()/clearUnused()
     * — هم‌الگو با DocxTemplateProcessor::restoreLiteralAsPlaceholder(). هم
     * shared strings و هم رشته‌های inline داخلِ شیت را چک می‌کند، چون
     * setValue() مقدار را هرجا پیدا کند (نه فقط shared strings) می‌نویسد.
     */
    public function restoreLiteralAsPlaceholder(string $literalValue, string $key): static
    {
        $literalValue = trim($literalValue);
        if ($literalValue === '') {
            return $this;
        }

        $pattern = '#' . preg_quote($literalValue, '#') . '#';
        $placeholder = '{{' . $key . '}}';

        if (isset($this->parts[$this->sharedStringsPart])) {
            $merged = $this->normalizeSharedStrings($this->parts[$this->sharedStringsPart], $literalValue, $pattern);
            $this->parts[$this->sharedStringsPart] = (string) preg_replace($pattern, $placeholder, $merged);
            $this->sharedCache = null; // محتوای shared strings عوض شد، کشِ قدیمی معتبر نیست
        }

        foreach ($this->parts as $name => $part) {
            if ($name === $this->sharedStringsPart || !preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                continue;
            }
            if (str_contains($part, $literalValue)) {
                $this->parts[$name] = (string) preg_replace($pattern, $placeholder, $part);
            }
        }

        return $this;
    }

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

    protected function isNumeric(string $v): bool
    {
        return $v !== '' && preg_match('#^-?\d+(\.\d+)?$#', str_replace(',', '', $v)) === 1;
    }

    protected function numeric(string|int|float $v): string
    {
        return (string) (0 + (float) str_replace(',', '', (string) $v));
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    public function __destruct()
    {
        if (is_file($this->workFile)) {
            @unlink($this->workFile);
        }
    }
}
