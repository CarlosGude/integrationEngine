<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Quality;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QualityConfigTest extends TestCase
{
    private const ROOT = __DIR__.'/../..';

    #[Test]
    public function infectionThresholdsAreTheSingleStandard(): void
    {
        $config = self::readInfectionConfig();

        self::assertSame(85, $config['minMsi'], 'infection.json5 minMsi must be the single 85 standard.');
        self::assertSame(95, $config['minCoveredMsi'], 'infection.json5 minCoveredMsi must be the single 95 standard.');
    }

    #[Test]
    public function infectionExcludedPathsExist(): void
    {
        $config = self::readInfectionConfig();

        self::assertNotEmpty($config['excludes'], 'infection.json5 source.excludes must not be empty.');

        foreach ($config['excludes'] as $excluded) {
            self::assertFileExists(
                self::ROOT.'/src/'.$excluded,
                \sprintf('infection.json5 excludes an inexistent path: src/%s', $excluded),
            );
        }
    }

    #[Test]
    public function phpunitExcludedFilesExist(): void
    {
        $xml = new \DOMDocument();
        $xml->load(self::ROOT.'/phpunit.xml.dist');

        $excludeNode = $xml->getElementsByTagName('exclude')->item(0);
        self::assertNotNull($excludeNode, 'phpunit.xml.dist must declare a <source><exclude> section.');

        $files = $excludeNode->getElementsByTagName('file');
        self::assertGreaterThan(0, $files->length, 'phpunit.xml.dist must declare excluded files.');

        foreach ($files as $file) {
            $path = self::ROOT.'/'.$file->textContent;
            self::assertFileExists($path, \sprintf('phpunit.xml.dist excludes an inexistent file: %s', $file->textContent));
        }
    }

    #[Test]
    public function makefileAndCiDoNotDeclareTheirOwnMutationThresholds(): void
    {
        $workflowFiles = glob(self::ROOT.'/.github/workflows/*.yml');
        self::assertIsArray($workflowFiles, 'Unable to list .github/workflows/*.yml files.');

        foreach ([self::ROOT.'/Makefile', ...$workflowFiles] as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents, \sprintf('Unable to read %s', $file));

            self::assertDoesNotMatchRegularExpression(
                '/--min-msi|--min-covered-msi/',
                $contents,
                \sprintf('%s must not declare its own mutation thresholds; infection.json5 is the single source.', $file),
            );
        }
    }

    /**
     * @return array{minMsi: int, minCoveredMsi: int, excludes: list<string>}
     */
    private static function readInfectionConfig(): array
    {
        $raw = (string) file_get_contents(self::ROOT.'/infection.json5');
        $withoutBlockComments = (string) preg_replace('#/\*.*?\*/#s', '', $raw);
        $withoutLineComments = (string) preg_replace('#(^|\s)//[^\n]*#', '', $withoutBlockComments);
        $withoutTrailingCommas = (string) preg_replace('/,(\s*[}\]])/', '$1', $withoutLineComments);

        $decoded = json_decode($withoutTrailingCommas, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        self::assertArrayHasKey('minMsi', $decoded);
        self::assertIsInt($decoded['minMsi']);

        self::assertArrayHasKey('minCoveredMsi', $decoded);
        self::assertIsInt($decoded['minCoveredMsi']);

        self::assertArrayHasKey('source', $decoded);
        self::assertIsArray($decoded['source']);
        self::assertArrayHasKey('excludes', $decoded['source']);
        self::assertIsArray($decoded['source']['excludes']);

        $excludes = [];
        foreach ($decoded['source']['excludes'] as $excluded) {
            self::assertIsString($excluded);
            $excludes[] = $excluded;
        }

        return [
            'minMsi' => $decoded['minMsi'],
            'minCoveredMsi' => $decoded['minCoveredMsi'],
            'excludes' => $excludes,
        ];
    }
}
