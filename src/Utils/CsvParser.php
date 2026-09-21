<?php

declare(strict_types=1);

namespace IntegrationEngine\Utils;

/**
 * Parse CSV data into arrays.
 *
 * Handles:
 * - Different delimiters (comma, semicolon, tab)
 * - Different encodings (UTF-8, ISO-8859-1)
 * - Header row detection
 * - Empty line skipping
 *
 * Example:
 * $csv = "name,age\nJohn,30\nJane,28";
 * $rows = CsvParser::parse($csv);
 * // [['name' => 'John', 'age' => '30'], ['name' => 'Jane', 'age' => '28']]
 */
final class CsvParser
{
    /**
     * Parse CSV string into array of associative arrays.
     *
     * @param string               $csv     raw CSV content
     * @param null|CsvParseOptions $options parsing options
     *
     * @return array<array<string, string>> array of rows with headers as keys
     *
     * @throws \InvalidArgumentException if CSV is malformed
     */
    public static function parse(string $csv, ?CsvParseOptions $options = null): array
    {
        $options ??= new CsvParseOptions();

        // Normalize encoding if needed
        if (null !== $options->encoding && 'UTF-8' !== $options->encoding) {
            $csv = (string) mb_convert_encoding($csv, 'UTF-8', $options->encoding);
        }

        // Split into lines
        $csv = trim($csv);
        $lines = explode("\n", $csv);

        // Get header row
        if ($options->headerRow >= \count($lines)) {
            throw new \InvalidArgumentException(
                \sprintf('headerRow %d exceeds line count %d', $options->headerRow, \count($lines))
            );
        }

        $headerLine = $lines[$options->headerRow] ?? '';
        $headers = self::parseRow($headerLine, $options->delimiter, $options->enclosure);

        if (empty($headers)) {
            throw new \InvalidArgumentException('CSV header row is empty');
        }

        // Parse data rows
        $rows = [];
        $headerCount = \count($headers);

        for ($i = $options->headerRow + 1; $i < \count($lines); ++$i) {
            $line = trim($lines[$i]);

            // Skip empty lines if configured
            if ('' === $line && $options->skipEmptyLines) {
                continue;
            }

            $values = self::parseRow($line, $options->delimiter, $options->enclosure);

            // Ensure column count matches header
            if (\count($values) !== $headerCount) {
                throw new \InvalidArgumentException(
                    \sprintf(
                        'Row %d has %d columns, expected %d',
                        $i + 1,
                        \count($values),
                        $headerCount
                    )
                );
            }

            $rows[] = array_combine($headers, $values);
        }

        return $rows;
    }

    /**
     * Parse a single CSV row.
     *
     * @return list<string>
     */
    private static function parseRow(string $line, string $delimiter = ',', string $enclosure = '"'): array
    {
        // $escape is passed explicitly as '' — RFC 4180 has no escape character,
        // it escapes an enclosure by doubling it ("" inside a quoted field).
        // PHP's default is still "\\" but emits a deprecation from 8.4 and flips
        // to '' in PHP 9; pinning it here keeps one behaviour across versions.
        $values = str_getcsv($line, $delimiter, $enclosure, '');

        // Trim whitespace from each value
        return array_map(static fn ($v) => trim($v ?? ''), $values);
    }
}
