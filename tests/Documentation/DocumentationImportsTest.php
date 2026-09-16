<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Documentation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Every `use IntegrationEngine\...;` shown in the documentation must resolve
 * to a real class/interface/trait/enum — otherwise copying an example
 * straight out of the docs doesn't work.
 */
final class DocumentationImportsTest extends TestCase
{
    private const ROOT = __DIR__.'/../..';

    private const EXCLUDED_DIRECTORIES = ['vendor', 'var', 'node_modules', 'landing', 'memory'];

    /** @param list<string> $imports */
    #[Test]
    #[DataProvider('provideEveryImportUsedInMarkdownResolvesCases')]
    public function everyImportUsedInMarkdownResolves(string $file, array $imports): void
    {
        foreach ($imports as $fqcn) {
            self::assertTrue(
                class_exists($fqcn) || interface_exists($fqcn) || trait_exists($fqcn) || enum_exists($fqcn),
                \sprintf('%s: %s does not exist.', $file, $fqcn),
            );
        }
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function provideEveryImportUsedInMarkdownResolvesCases(): iterable
    {
        foreach (self::markdownFiles() as $file) {
            $imports = self::extractImports($file);

            if ([] === $imports) {
                continue;
            }

            yield substr($file, \strlen(self::ROOT) + 1) => [$file, $imports];
        }
    }

    /** @return list<string> */
    private static function extractImports(string $file): array
    {
        $contents = (string) file_get_contents($file);

        preg_match_all('/^use\s+(IntegrationEngine\\\[\w\\\]+)\s*;/m', $contents, $matches);

        return $matches[1];
    }

    /** @return list<string> */
    private static function markdownFiles(): array
    {
        $files = [];

        /** @var \SplFileInfo $fileInfo */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT, \FilesystemIterator::SKIP_DOTS)) as $fileInfo) {
            if ('md' !== $fileInfo->getExtension()) {
                continue;
            }

            $relativePath = substr($fileInfo->getPathname(), \strlen(self::ROOT) + 1);

            if (self::isExcluded($relativePath)) {
                continue;
            }

            $files[] = $fileInfo->getPathname();
        }

        sort($files);

        return $files;
    }

    private static function isExcluded(string $relativePath): bool
    {
        foreach (self::EXCLUDED_DIRECTORIES as $excludedDirectory) {
            if (str_starts_with($relativePath, $excludedDirectory.'/')) {
                return true;
            }
        }

        return false;
    }
}
