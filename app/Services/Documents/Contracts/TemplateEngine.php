<?php

namespace App\Services\Documents\Contracts;

/**
 * Renders a stored office template into a finished document.
 *
 * Implementations MUST preserve the template file's own formatting: the file is
 * edited at the text level, never re-generated, so letterhead, styles, fonts and
 * page setup survive untouched.
 */
interface TemplateEngine
{
    /** File extension this engine handles, without the dot ("docx", "xlsx"). */
    public function extension(): string;

    /** True when this engine can render the given file type. */
    public function supports(string $fileType): bool;

    /**
     * Every {{placeholder}} key found inside the template file.
     *
     * @return array<int,string>
     */
    public function placeholders(string $templatePath): array;

    /**
     * Merge values into the template and write the result to $destination.
     *
     * @param array<string,string|int|float|null>              $values     scalar merge fields
     * @param array<int,array<string,string|int|float|null>>   $lineRows   repeated table rows
     * @param string                                           $lineMarker placeholder identifying the specimen row
     * @param array<int,string>                                $except     (M36) placeholder keys to leave literally
     *   in the output instead of blanking — used for values that don't exist
     *   yet at generation time (e.g. {{document.number}}, filled later at publish).
     *
     * @return string absolute path of the written file
     */
    public function render(
        string $templatePath,
        array $values,
        array $lineRows,
        string $lineMarker,
        string $destination,
        array $except = []
    ): string;

    /**
     * Quick structural check used at upload time.
     *
     * @return array{ok:bool,message:string}
     */
    public function validate(string $templatePath): array;
}
