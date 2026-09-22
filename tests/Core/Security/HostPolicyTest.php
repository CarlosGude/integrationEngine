<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core\Security;

use IntegrationEngine\Core\Exception\DisallowedHostException;
use IntegrationEngine\Core\Security\HostPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HostPolicyTest extends TestCase
{
    /** @param list<string> $hosts */
    #[DataProvider('provideAllowsConfiguredHostsCases')]
    public function testAllowsConfiguredHosts(array $hosts, string $url): void
    {
        (new HostPolicy($hosts))->assertAllowed($url);
        $this->addToAssertionCount(1);
    }

    /** @return iterable<array{list<string>, string}> */
    public static function provideAllowsConfiguredHostsCases(): iterable
    {
        yield [['partner.example'], 'https://partner.example/path'];

        yield [['PARTNER.example'], 'https://Partner.Example:8443/path'];

        yield [['*.partners.example'], 'https://a.partners.example'];

        yield [['*.partners.example'], 'https://a.b.partners.example'];

        yield [[], 'https://anything.example'];
    }

    #[DataProvider('provideRejectsOtherHostsCases')]
    public function testRejectsOtherHosts(string $url): void
    {
        $this->expectException(DisallowedHostException::class);
        (new HostPolicy(['*.partners.example']))->assertAllowed($url);
    }

    /** @return iterable<array{string}> */
    public static function provideRejectsOtherHostsCases(): iterable
    {
        yield ['https://partners.example'];

        yield ['https://evilpartners.example'];

        yield ['https://a.partners.example@evil.example'];

        yield ['https://a.partners.example.evil.example'];

        yield ['/relative'];

        yield ['file://a.partners.example/file'];
    }
}
