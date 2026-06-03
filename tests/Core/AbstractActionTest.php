<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ActionContextInterface;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class AbstractActionTest extends TestCase
{
    #[Test]
    public function getMethodReturnsConstructedMethod(): void
    {
        $action = AbstractActionTestFixture::create(method: 'POST', path: '/test');

        self::assertSame('POST', $action->getMethod());
    }

    #[Test]
    public function getPathReturnsStaticPath(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/hello');

        self::assertSame('/hello', $action->getPath());
    }

    #[Test]
    public function getPathResolvesPlaceholderFromContext(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/orders/{id}');

        $context = new class implements ActionContextInterface {
            public static function create(array $data): self
            {
                return new self();
            }

            public function toArray(): array
            {
                return ['id' => '42'];
            }
        };

        self::assertSame('/orders/42', $action->withContext($context)->getPath());
    }

    #[Test]
    public function getPathThrowsForMissingPlaceholder(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/orders/{id}');

        $context = new class implements ActionContextInterface {
            public static function create(array $data): self
            {
                return new self();
            }

            public function toArray(): array
            {
                return [];
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Missing path parameter/');

        $action->withContext($context)->getPath();
    }

    #[Test]
    public function getPathThrowsForNonScalarPlaceholderValue(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/orders/{id}');

        $context = new class implements ActionContextInterface {
            public static function create(array $data): self
            {
                return new self();
            }

            public function toArray(): array
            {
                return ['id' => ['not', 'scalar']];
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/must be a scalar/');

        $action->withContext($context)->getPath();
    }

    #[Test]
    public function withContextReturnsCloneNotSameInstance(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/hello');

        $context = new class implements ActionContextInterface {
            public static function create(array $data): self
            {
                return new self();
            }

            public function toArray(): array
            {
                return [];
            }
        };

        self::assertNotSame($action, $action->withContext($context));
    }

    #[Test]
    public function getActionContextReturnsNullByDefault(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/hello');

        self::assertNull($action->getActionContext());
    }

    #[Test]
    public function getActionContextReturnsInjectedContext(): void
    {
        $context = new class implements ActionContextInterface {
            public static function create(array $data): self
            {
                return new self();
            }

            public function toArray(): array
            {
                return [];
            }
        };

        $action = AbstractActionTestFixture::create(method: 'GET', path: '/hello')->withContext($context);

        self::assertSame($context, $action->getActionContext());
    }

    #[Test]
    public function getAuthorizationReturnsNullByDefault(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/hello');

        self::assertNull($action->getAuthorization());
    }

    #[Test]
    public function getAuthorizationReturnsInjectedConfig(): void
    {
        $auth = new StaticAuthorizationConfig(type: 'bearer', params: ['token' => 'xyz']);
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/hello', authorization: $auth);

        self::assertSame($auth, $action->getAuthorization());
    }

    #[Test]
    public function customPathResolverIsUsedWhenProvided(): void
    {
        $action = AbstractActionWithResolver::create(method: 'GET', path: '/original');

        self::assertSame('/custom-resolved', $action->getPath());
    }

    #[Test]
    public function customPathResolverThrowsIfNotReturningString(): void
    {
        $action = AbstractActionWithBadResolver::create(method: 'GET', path: '/original');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/must return a string/');

        $action->getPath();
    }
}

// ──────────────────────────────────────────────
// Local fixtures
// ──────────────────────────────────────────────

final class AbstractActionTestFixture extends AbstractAction
{
    public static function getName(): string
    {
        return 'test_action';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): ?string
    {
        return null;
    }
}

final class AbstractActionWithResolver extends AbstractAction
{
    public static function getName(): string
    {
        return 'resolver_action';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): ?string
    {
        return null;
    }

    protected function resolvePathCallback(): ?callable
    {
        return static fn (string $path, mixed $ctx): string => '/custom-resolved';
    }
}

final class AbstractActionWithBadResolver extends AbstractAction
{
    public static function getName(): string
    {
        return 'bad_resolver_action';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): ?string
    {
        return null;
    }

    protected function resolvePathCallback(): ?callable
    {
        return static fn (): int => 42;
    }
}
