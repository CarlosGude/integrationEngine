<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\AuthorizationConfig;
use IntegrationEngine\Core\Contract\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuthorizationConfigTest extends TestCase
{
    // ── AuthorizationConfig::fromArray dispatch ──────────────────────────────

    #[Test]
    public function fromArrayThrowsWhenTypeIsMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Authorization config must define a "type".');

        AuthorizationConfig::fromArray(['token' => 'abc']);
    }

    #[Test]
    public function fromArrayDispatchesToDynamicConfig(): void
    {
        $config = AuthorizationConfig::fromArray([
            'type' => 'dynamic',
            'action' => 'fetch_token',
            'token_field' => 'access_token',
            'ttl' => 300,
        ]);

        self::assertInstanceOf(DynamicAuthorizationConfig::class, $config);
    }

    #[Test]
    public function fromArrayDispatchesToStaticConfigForAnyOtherType(): void
    {
        $config = AuthorizationConfig::fromArray(['type' => 'static', 'token' => 'abc']);

        self::assertInstanceOf(StaticAuthorizationConfig::class, $config);
    }

    // ── StaticAuthorizationConfig::fromArray ─────────────────────────────────

    #[Test]
    public function staticFromArrayThrowsWhenTypeIsNotAString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Static authorization config must define a string "type".');

        StaticAuthorizationConfig::fromArray(['type' => 123, 'token' => 'abc']);
    }

    #[Test]
    public function staticFromArrayExcludesTypeFromParams(): void
    {
        $config = StaticAuthorizationConfig::fromArray(['type' => 'static', 'token' => 'abc', 'header' => 'X-Key']);

        self::assertSame('static', $config->type);
        self::assertSame(['token' => 'abc', 'header' => 'X-Key'], $config->params);
    }

    // ── DynamicAuthorizationConfig::fromArray required keys ──────────────────

    #[Test]
    public function dynamicFromArrayThrowsWhenActionIsMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Dynamic authorization config must define "action".');

        DynamicAuthorizationConfig::fromArray(['type' => 'dynamic', 'token_field' => 'access_token', 'ttl' => 300]);
    }

    #[Test]
    public function dynamicFromArrayThrowsWhenTokenFieldIsMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Dynamic authorization config must define "token_field".');

        DynamicAuthorizationConfig::fromArray(['type' => 'dynamic', 'action' => 'fetch_token', 'ttl' => 300]);
    }

    #[Test]
    public function dynamicFromArrayThrowsWhenTtlIsMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Dynamic authorization config must define "ttl".');

        DynamicAuthorizationConfig::fromArray(['type' => 'dynamic', 'action' => 'fetch_token', 'token_field' => 'access_token']);
    }

    // ── DynamicAuthorizationConfig::fromArray field types ────────────────────

    #[Test]
    public function dynamicFromArrayThrowsWhenActionIsNotAString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DynamicAuthorizationConfig::fromArray(['type' => 'dynamic', 'action' => 123, 'token_field' => 'access_token', 'ttl' => 300]);
    }

    #[Test]
    public function dynamicFromArrayThrowsWhenTokenFieldIsNotAString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DynamicAuthorizationConfig::fromArray(['type' => 'dynamic', 'action' => 'fetch_token', 'token_field' => 123, 'ttl' => 300]);
    }

    #[Test]
    public function dynamicFromArrayThrowsWhenTtlIsNotScalar(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DynamicAuthorizationConfig::fromArray(['type' => 'dynamic', 'action' => 'fetch_token', 'token_field' => 'access_token', 'ttl' => []]);
    }

    #[Test]
    public function dynamicFromArrayThrowsWhenTtlIsNonNumericString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"ttl" must be a non-negative integer');

        DynamicAuthorizationConfig::fromArray(['type' => 'dynamic', 'action' => 'fetch_token', 'token_field' => 'access_token', 'ttl' => 'abc']);
    }

    #[Test]
    public function dynamicFromArrayThrowsWhenTtlIsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"ttl" must be a non-negative integer');

        DynamicAuthorizationConfig::fromArray(['type' => 'dynamic', 'action' => 'fetch_token', 'token_field' => 'access_token', 'ttl' => -1]);
    }

    #[Test]
    public function dynamicFromArrayCastsTtlToInt(): void
    {
        $config = DynamicAuthorizationConfig::fromArray(['type' => 'dynamic', 'action' => 'fetch_token', 'token_field' => 'access_token', 'ttl' => '300']);

        self::assertSame(300, $config->ttl);
    }

    // ── DynamicAuthorizationConfig::fromArray header and prefix ──────────────

    #[Test]
    public function dynamicFromArrayUsesConfiguredHeader(): void
    {
        $config = DynamicAuthorizationConfig::fromArray([
            'type' => 'dynamic',
            'action' => 'fetch_token',
            'token_field' => 'access_token',
            'ttl' => 300,
            'header' => 'X-Api-Key',
        ]);

        self::assertSame('X-Api-Key', $config->header);
    }

    #[Test]
    public function dynamicFromArrayFallsBackToAuthorizationHeaderWhenNotAString(): void
    {
        $config = DynamicAuthorizationConfig::fromArray([
            'type' => 'dynamic',
            'action' => 'fetch_token',
            'token_field' => 'access_token',
            'ttl' => 300,
            'header' => 123,
        ]);

        self::assertSame('Authorization', $config->header);
    }

    #[Test]
    public function dynamicFromArrayUsesConfiguredPrefix(): void
    {
        $config = DynamicAuthorizationConfig::fromArray([
            'type' => 'dynamic',
            'action' => 'fetch_token',
            'token_field' => 'access_token',
            'ttl' => 300,
            'prefix' => 'Token',
        ]);

        self::assertSame('Token', $config->prefix);
    }

    #[Test]
    public function dynamicFromArrayFallsBackToNullPrefixWhenNotAString(): void
    {
        $config = DynamicAuthorizationConfig::fromArray([
            'type' => 'dynamic',
            'action' => 'fetch_token',
            'token_field' => 'access_token',
            'ttl' => 300,
            'prefix' => 123,
        ]);

        self::assertNull($config->prefix);
    }
}
