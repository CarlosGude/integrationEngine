<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Documentation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Every relative link in the documentation — Markdown links/images
 * `[text](target)` / `![alt](target)`, and backtick-quoted path-like
 * references such as `` `docs/foo.md` `` — must resolve to a file that
 * actually exists. External URLs, mailto: links and pure #anchors are
 * ignored.
 *
 * Markdown links resolve relative to the linking file's own directory
 * (the browser/GitHub convention). Backtick path mentions are prose, not
 * navigable links, and this codebase's docs consistently write them
 * relative to the repo root regardless of which file mentions them, so
 * they're resolved against the repo root instead.
 */
final class DocumentationLinksTest extends TestCase
{
    private const ROOT = __DIR__.'/../..';

    private const EXCLUDED_DIRECTORIES = ['vendor', 'var', 'node_modules', 'memory'];

    /**
     * docs/PLAN-STATUS.md is a forward-looking task plan — its prose
     * mentions file paths that are deliverables of *future* work (e.g.
     * docs/resilience.md) or live in the demo repository, so they don't
     * exist here by design. Checking those backtick mentions against "does
     * it exist now" would be checking the wrong thing. Its actual Markdown
     * links are still checked.
     */
    private const NO_BACKTICK_CHECK = ['docs/PLAN-STATUS.md'];

    /** @param list<array{0: string, 1: string}> $links */
    #[Test]
    #[DataProvider('provideEveryRelativeLinkResolvesCases')]
    public function everyRelativeLinkResolves(string $file, array $links): void
    {
        foreach ($links as [$original, $resolved]) {
            self::assertFileExists($resolved, \sprintf('%s: "%s" does not exist.', $file, $original));
        }
    }

    /** @return iterable<string, array{string, list<array{0: string, 1: string}>}> */
    public static function provideEveryRelativeLinkResolvesCases(): iterable
    {
        foreach (self::markdownFiles() as $file) {
            $links = self::extractRelativeLinks($file);

            if ([] === $links) {
                continue;
            }

            yield substr($file, \strlen(self::ROOT) + 1) => [$file, $links];
        }
    }

    /** @return list<array{0: string, 1: string}> */
    private static function extractRelativeLinks(string $file): array
    {
        // Fenced code blocks hold example content (e.g. a snippet of what a
        // future README should contain) — not this file's own live links.
        $contents = (string) preg_replace('/^[ \t]*```.*?^[ \t]*```$/ms', '', (string) file_get_contents($file));
        $fileDir = \dirname($file);
        $links = [];

        preg_match_all('/!?\[[^\]]*]\(([^)\s]+)(?:\s+"[^"]*")?\)/', $contents, $bracketMatches);
        foreach ($bracketMatches[1] as $target) {
            if (self::isExternalOrAnchor($target)) {
                continue;
            }

            $links[] = [$target, self::resolveRelativeTo($fileDir, $target)];
        }

        $relativePath = substr($file, \strlen(self::ROOT) + 1);

        if (!\in_array($relativePath, self::NO_BACKTICK_CHECK, true)) {
            preg_match_all('~`([.\w/-]+/[.\w-]+\.md)(?:#[\w-]+)?`~', $contents, $backtickMatches);
            foreach ($backtickMatches[1] as $target) {
                $links[] = [$target, self::resolveRelativeTo(self::ROOT, $target)];
            }
        }

        return $links;
    }

    private static function isExternalOrAnchor(string $target): bool
    {
        return str_starts_with($target, 'http://')
            || str_starts_with($target, 'https://')
            || str_starts_with($target, 'mailto:')
            || str_starts_with($target, '#');
    }

    private static function resolveRelativeTo(string $baseDir, string $target): string
    {
        $withoutAnchor = explode('#', $target, 2)[0];

        return $baseDir.'/'.$withoutAnchor;
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
