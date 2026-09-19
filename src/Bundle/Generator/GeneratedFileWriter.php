<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Writes the files produced by the make:* generators, reporting each outcome on the console.
 *
 * Static and stateless: the bundle autodiscovers this namespace as services, so a
 * constructor taking the console style or the force flag could not be autowired.
 */
final class GeneratedFileWriter
{
    public static function write(string $filePath, string $content, SymfonyStyle $io, bool $force): void
    {
        $dir = \dirname($filePath);

        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            $io->error("Cannot create directory: {$dir}");

            return;
        }

        $exists = file_exists($filePath);

        if ($exists && !$force) {
            $io->warning("Skipped (already exists): {$filePath}");

            return;
        }

        if (false === file_put_contents($filePath, $content)) {
            $io->error("Could not write file: {$filePath}");

            return;
        }

        $io->text($exists ? "  updated  {$filePath}" : "  created  {$filePath}");
    }
}
