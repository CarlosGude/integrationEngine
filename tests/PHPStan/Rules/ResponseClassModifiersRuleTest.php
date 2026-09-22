<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\PHPStan\Rules;

use IntegrationEngine\PHPStan\Rules\ResponseClassModifiersRule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<ResponseClassModifiersRule> */
final class ResponseClassModifiersRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/fixtures.neon'];
    }

    public function testResponseAndWebhookModifiers(): void
    {
        $this->analyse([__DIR__.'/data/responses.php.fixture'], [
            ['Response and webhook event classes must be final and readonly.', 8],
            ['Response and webhook event classes must be final and readonly.', 9],
            ['Response and webhook event classes must be final and readonly.', 10],
        ]);
    }

    protected function getRule(): ResponseClassModifiersRule
    {
        return new ResponseClassModifiersRule();
    }
}
