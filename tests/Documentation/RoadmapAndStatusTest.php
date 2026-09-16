<?php

declare(strict_types=1);

namespace Tests\Documentation;

use PHPUnit\Framework\TestCase;

final class RoadmapAndStatusTest extends TestCase
{
    private const ROADMAP_PATH = __DIR__.'/../../ROADMAP.md';
    private const README_PATH = __DIR__.'/../../README.md';

    public function testRoadmapFileExists(): void
    {
        self::assertFileExists(
            self::ROADMAP_PATH,
            'ROADMAP.md must exist at the repository root'
        );
    }

    public function testRoadmapContainsRequiredSections(): void
    {
        self::assertFileExists(self::ROADMAP_PATH);
        $content = file_get_contents(self::ROADMAP_PATH);
        self::assertIsString($content);

        $requiredSections = ['Now', 'Next', 'Later', 'Recently shipped', 'Out of scope'];
        foreach ($requiredSections as $section) {
            self::assertStringContainsString(
                $section,
                $content,
                \sprintf('ROADMAP.md must contain "%s" section', $section)
            );
        }
    }

    public function testReadmeContainsStatusBlock(): void
    {
        self::assertFileExists(self::README_PATH);
        $content = file_get_contents(self::README_PATH);
        self::assertIsString($content);

        // Check for a status or "Project Status" or "Roadmap" section in README
        self::assertTrue(
            str_contains($content, 'Status') || str_contains($content, 'Roadmap') || str_contains($content, 'Live'),
            'README.md must contain a status/roadmap block indicating project state'
        );
    }
}
