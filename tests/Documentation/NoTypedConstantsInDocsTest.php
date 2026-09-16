<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Documentation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * composer.json declares php >=8.2, but typed class constants
 * (`public const string NAME`) are PHP 8.3 syntax — a PHP example copied
 * from the documentation must run on every PHP version the bundle claims
 * to support.
 */
final class NoTypedConstantsInDocsTest extends TestCase
{
    private const ROOT = __DIR__.'/../..';

    #[Test]
    #[DataProvider('providePhpCodeBlocksDoNotUseTypedClassConstantsCases')]
    public function phpCodeBlocksDoNotUseTypedClassConstants(string $file): void
    {
        self::assertFileExists($file);
        $contents = (string) file_get_contents($file);

        foreach (self::phpCodeBlocks($contents) as $block) {
            self::assertDoesNotMatchRegularExpression(
                '/const\s+(string|int|bool|float|array)\s+[A-Z_]+/',
                $block,
                \sprintf('%s contains a typed class constant inside a php code block.', $file),
            );
        }
    }

    /** @return iterable<string, array{string}> */
    public static function providePhpCodeBlocksDoNotUseTypedClassConstantsCases(): iterable
    {
        $files = [
            self::ROOT.'/README.md',
            self::ROOT.'/DOCUMENTATION.md',
            self::ROOT.'/DOCUMENTATION_ES.md',
            self::ROOT.'/UPGRADE-4.0.md',
            ...self::markdownFilesUnder(self::ROOT.'/docs'),
            ...self::markdownFilesUnder(self::ROOT.'/agent'),
        ];

        foreach (array_unique($files) as $file) {
            $relativePath = str_replace(self::ROOT.'/', '', $file);

            yield $relativePath => [$file];
        }
    }

    /** @return list<string> */
    private static function markdownFilesUnder(string $directory): array
    {
        $files = [];

        /** @var \SplFileInfo $fileInfo */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $fileInfo) {
            if ('md' === $fileInfo->getExtension()) {
                $files[] = $fileInfo->getPathname();
            }
        }

        return $files;
    }

    /** @return list<string> */
    private static function phpCodeBlocks(string $markdown): array
    {
        preg_match_all('/```php\b(.*?)```/s', $markdown, $matches);

        return $matches[1];
    }
}
