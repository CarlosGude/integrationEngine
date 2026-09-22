<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\PHPStan\Rules;

use IntegrationEngine\PHPStan\Rules\MapperActionRule;
use PHPStan\Parser\Parser;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<MapperActionRule> */
final class MapperActionRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/fixtures.neon'];
    }

    public function testMapperActionRelationship(): void
    {
        $this->analyse([__DIR__.'/data/mappers.php.fixture'], [
            ['Mapper getAction() must return a class constant extending AbstractAction.', 20],
            ['The action mapper() must return this mapper class constant.', 23],
            ['Mapper getAction() must return a class constant extending AbstractAction.', 27],
        ]);
    }

    protected function getRule(): MapperActionRule
    {
        require_once __DIR__.'/data/mappers.php.fixture';

        $parser = self::getContainer()->getService('currentPhpVersionRichParser');
        self::assertInstanceOf(Parser::class, $parser);

        return new MapperActionRule(self::getContainer()->getByType(ReflectionProvider::class), $parser);
    }
}
