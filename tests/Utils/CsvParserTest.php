<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Utils;

use IntegrationEngine\Utils\CsvParseOptions;
use IntegrationEngine\Utils\CsvParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CsvParserTest extends TestCase
{
    // ── escaping ─────────────────────────────────────────────────────────────
    //
    // The parser pins str_getcsv's $escape to ''. PHP's own default is "\\",
    // which is deprecated from 8.4 and becomes '' in PHP 9. These tests fix the
    // chosen behaviour so the flip cannot change it silently.

    #[Test]
    public function backslashIsALiteralCharacterNotAnEscape(): void
    {
        $rows = CsvParser::parse("sku,path\nA1,C:\\tmp\\file");

        self::assertSame([['sku' => 'A1', 'path' => 'C:\tmp\file']], $rows);
    }

    #[Test]
    public function enclosureIsEscapedByDoublingItPerRfc4180(): void
    {
        $rows = CsvParser::parse("a,b\n\"x\"\"y\",z");

        self::assertSame([['a' => 'x"y', 'b' => 'z']], $rows);
    }

    #[Test]
    public function backslashDoesNotEscapeAnEnclosure(): void
    {
        // With $escape = "\\" this used to yield 'x"y'. RFC 4180 has no escape
        // character, so the backslash stays and the quote closes the field.
        $rows = CsvParser::parse("a,b\n\"x\\\"y\",z");

        self::assertSame([['a' => 'x\y"', 'b' => 'z']], $rows);
    }

    #[Test]
    public function parsingEmitsNoDeprecation(): void
    {
        $seen = [];
        set_error_handler(
            static function (int $errno, string $message) use (&$seen): bool {
                $seen[] = $message;

                return true;
            },
            E_DEPRECATED
        );

        try {
            CsvParser::parse("a,b\nx,y");
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $seen);
    }

    // ── parsing ──────────────────────────────────────────────────────────────

    #[Test]
    public function parsesHeaderAndRowsIntoAssociativeArrays(): void
    {
        $rows = CsvParser::parse("name,age\nJohn,30\nJane,28");

        self::assertSame([
            ['name' => 'John', 'age' => '30'],
            ['name' => 'Jane', 'age' => '28'],
        ], $rows);
    }

    #[Test]
    public function headerOnlyInputYieldsNoRows(): void
    {
        self::assertSame([], CsvParser::parse('a,b'));
    }

    #[Test]
    public function enclosedFieldMayContainTheDelimiter(): void
    {
        $rows = CsvParser::parse("a,b\n\"x,y\",z");

        self::assertSame([['a' => 'x,y', 'b' => 'z']], $rows);
    }

    #[Test]
    public function surroundingWhitespaceIsTrimmedFromEveryValue(): void
    {
        $rows = CsvParser::parse("a,b\n  x  ,  y  ");

        self::assertSame([['a' => 'x', 'b' => 'y']], $rows);
    }

    #[Test]
    public function carriageReturnsFromCrlfInputAreStripped(): void
    {
        $rows = CsvParser::parse("a,b\r\nx,y");

        self::assertSame([['a' => 'x', 'b' => 'y']], $rows);
    }

    #[Test]
    public function emptyLinesAreSkippedByDefault(): void
    {
        $rows = CsvParser::parse("a,b\nx,y\n\nz,w");

        self::assertSame([
            ['a' => 'x', 'b' => 'y'],
            ['a' => 'z', 'b' => 'w'],
        ], $rows);
    }

    #[Test]
    public function whitespaceOnlyLinesCountAsEmpty(): void
    {
        $rows = CsvParser::parse("a,b\nx,y\n   \nz,w");

        self::assertSame([
            ['a' => 'x', 'b' => 'y'],
            ['a' => 'z', 'b' => 'w'],
        ], $rows);
    }

    #[Test]
    public function aRowWhoseOnlyValueIsZeroIsNotMistakenForAnEmptyLine(): void
    {
        // empty('0') is true in PHP, which used to drop this row silently.
        $rows = CsvParser::parse("v\n0\n1\n2");

        self::assertSame([['v' => '0'], ['v' => '1'], ['v' => '2']], $rows);
    }

    #[Test]
    public function leadingAndTrailingBlankLinesAroundTheDocumentAreIgnored(): void
    {
        $rows = CsvParser::parse("\na,b\nx,y\n");

        self::assertSame([['a' => 'x', 'b' => 'y']], $rows);
    }

    // ── options ──────────────────────────────────────────────────────────────

    #[Test]
    public function skipEmptyLinesIsOnByDefault(): void
    {
        // The flag is an int used as a boolean, so only the value itself pins
        // the default: any non-zero would behave identically in parse().
        self::assertSame(1, (new CsvParseOptions())->skipEmptyLines);
    }

    #[Test]
    public function honoursACustomDelimiter(): void
    {
        $rows = CsvParser::parse("a;b\nx;y", new CsvParseOptions(delimiter: ';'));

        self::assertSame([['a' => 'x', 'b' => 'y']], $rows);
    }

    #[Test]
    public function honoursTabDelimitedOptions(): void
    {
        $rows = CsvParser::parse("a\tb\nx\ty", CsvParseOptions::tabDelimited());

        self::assertSame([['a' => 'x', 'b' => 'y']], $rows);
    }

    #[Test]
    public function headerRowOffsetSkipsTheLinesAboveIt(): void
    {
        $rows = CsvParser::parse("junk line\na,b\nx,y", new CsvParseOptions(headerRow: 1));

        self::assertSame([['a' => 'x', 'b' => 'y']], $rows);
    }

    #[Test]
    public function keepingEmptyLinesMakesThemFailTheColumnCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Row 3 has 1 columns, expected 2');

        CsvParser::parse("a,b\nx,y\n\nz,w", new CsvParseOptions(skipEmptyLines: 0));
    }

    #[Test]
    public function transcodesFromTheDeclaredEncodingToUtf8(): void
    {
        $latin1 = (string) mb_convert_encoding("a,b\nCafé,x", 'ISO-8859-1', 'UTF-8');

        $rows = CsvParser::parse($latin1, new CsvParseOptions(encoding: 'ISO-8859-1'));

        self::assertSame([['a' => 'Café', 'b' => 'x']], $rows);
    }

    #[Test]
    public function leavesContentUntouchedWhenNoEncodingIsDeclared(): void
    {
        $rows = CsvParser::parse("a,b\nCafé,x", new CsvParseOptions(encoding: null));

        self::assertSame([['a' => 'Café', 'b' => 'x']], $rows);
    }

    // ── failure modes ────────────────────────────────────────────────────────

    #[Test]
    public function throwsWhenARowHasMoreColumnsThanTheHeader(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Row 2 has 3 columns, expected 2');

        CsvParser::parse("a,b\nx,y,z");
    }

    #[Test]
    public function throwsWhenHeaderRowIsBeyondTheInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('headerRow 5 exceeds line count 1');

        CsvParser::parse('a,b', new CsvParseOptions(headerRow: 5));
    }

    #[Test]
    public function throwsWhenHeaderRowIsExactlyTheLineCount(): void
    {
        // Boundary: headerRow is 0-indexed, so index 1 of a single-line document
        // is already out of range.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('headerRow 1 exceeds line count 1');

        CsvParser::parse('a,b', new CsvParseOptions(headerRow: 1));
    }
}
