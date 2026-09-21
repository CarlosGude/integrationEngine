<?php

declare(strict_types=1);

namespace CarlosgudeSdk\IntegrationEngine\Utils;

/**
 * Options for CSV parsing.
 *
 * Configure delimiter, enclosure, encoding, and header behavior.
 */
final class CsvParseOptions
{
    public function __construct(
        public string $delimiter = ',',
        public string $enclosure = '"',
        public ?string $encoding = 'UTF-8',
        public int $skipEmptyLines = 1,
        public int $headerRow = 0,
    ) {
        if ('' === $delimiter) {
            throw new \InvalidArgumentException('delimiter cannot be empty');
        }
        if ('' === $enclosure) {
            throw new \InvalidArgumentException('enclosure cannot be empty');
        }
        if ($headerRow < 0) {
            throw new \InvalidArgumentException('headerRow must be >= 0');
        }
    }

    /**
     * Create options for semicolon-delimited CSV (common in EU).
     */
    public static function semiocolnDelimited(): self
    {
        return new self(delimiter: ';');
    }

    /**
     * Create options for tab-delimited data (TSV).
     */
    public static function tabDelimited(): self
    {
        return new self(delimiter: "\t");
    }

    /**
     * Create options for UTF-16 encoded CSV.
     */
    public static function utf16Encoded(): self
    {
        return new self(encoding: 'UTF-16');
    }

    /**
     * Create options for ISO-8859-1 encoded CSV.
     */
    public static function iso88591Encoded(): self
    {
        return new self(encoding: 'ISO-8859-1');
    }
}
