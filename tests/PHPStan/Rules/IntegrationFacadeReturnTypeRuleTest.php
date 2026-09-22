<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\PHPStan\Rules;

use IntegrationEngine\PHPStan\Rules\IntegrationFacadeReturnTypeRule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<IntegrationFacadeReturnTypeRule> */
final class IntegrationFacadeReturnTypeRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/fixtures.neon'];
    }

    public function testPublicFacadeReturnTypes(): void
    {
        $this->analyse([__DIR__.'/data/facades.php.fixture'], [
            ['Public integration facade methods must declare a return type; collections must contain objects.', 12],
            ['Public integration facade methods must declare a return type; collections must contain objects.', 14],
            ['Public integration facade methods must declare a return type; collections must contain objects.', 15],
            ['Public integration facade methods must declare a return type; collections must contain objects.', 16],
            ['Public integration facade methods must declare a return type; collections must contain objects.', 17],
            ['Public integration facade methods must declare a return type; collections must contain objects.', 25],
            ['Public integration facade methods must declare a return type; collections must contain objects.', 27],
        ]);
    }

    protected function getRule(): IntegrationFacadeReturnTypeRule
    {
        return new IntegrationFacadeReturnTypeRule();
    }
}
