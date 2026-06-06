<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ActionContextInterface;
use IntegrationEngine\Core\Contract\ClientAdapterInterface;
use IntegrationEngine\Core\Contract\RequestHeadersInterface;
use IntegrationEngine\Infrastructure\Http\ClientAdapterResolver;
use IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClientAdapterResolverTest extends TestCase
{
    #[Test]
    public function resolveReturnsBultInRestAdapter(): void
    {
        $resolver = new ClientAdapterResolver();
        $resolver->register('rest', SymfonyHttpClientAdapter::class);

        self::assertSame(SymfonyHttpClientAdapter::class, $resolver->resolve('rest'));
    }

    #[Test]
    public function resolveReturnsBultInGraphQLAdapter(): void
    {
        $resolver = new ClientAdapterResolver();
        $resolver->register('graphql', GraphQLClientAdapter::class);

        self::assertSame(GraphQLClientAdapter::class, $resolver->resolve('graphql'));
    }

    #[Test]
    public function resolveUnknownTypeThrowsInvalidArgumentException(): void
    {
        $resolver = new ClientAdapterResolver();
        $resolver->register('rest', SymfonyHttpClientAdapter::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown client type "soap"/');
        $this->expectExceptionMessageMatches('/Registered types: rest/');

        $resolver->resolve('soap');
    }

    #[Test]
    public function laterRegistrationOverridesEarlier(): void
    {
        $resolver = new ClientAdapterResolver();
        $resolver->register('rest', SymfonyHttpClientAdapter::class);
        $resolver->register('rest', ResolverTestCustomAdapter::class);

        // Project adapter wins over built-in
        self::assertSame(ResolverTestCustomAdapter::class, $resolver->resolve('rest'));
    }

    #[Test]
    public function allReturnsFullMap(): void
    {
        $resolver = new ClientAdapterResolver();
        $resolver->register('rest', SymfonyHttpClientAdapter::class);
        $resolver->register('graphql', GraphQLClientAdapter::class);

        $all = $resolver->all();

        self::assertArrayHasKey('rest', $all);
        self::assertArrayHasKey('graphql', $all);
        self::assertCount(2, $all);
    }

    #[Test]
    public function resolveOnEmptyResolverThrowsWithNoneMessage(): void
    {
        $resolver = new ClientAdapterResolver();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Registered types: none/');

        $resolver->resolve('rest');
    }

    #[Test]
    public function customAdapterIsRegisteredAndResolved(): void
    {
        $resolver = new ClientAdapterResolver();
        $resolver->register('soap', ResolverTestCustomAdapter::class);

        self::assertSame(ResolverTestCustomAdapter::class, $resolver->resolve('soap'));
    }
}

// ── Custom adapter fixture ────────────────────

final readonly class ResolverTestCustomAdapter implements ClientAdapterInterface
{
    public static function getClientType(): string  { return 'custom'; }
    public static function requiresPath(): bool     { return false; }
    public static function requiresMethod(): bool   { return false; }

    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        return [];
    }
}