<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ActionContextInterface;
use IntegrationEngine\Tests\Fake\FakeContext;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AbstractActionTest extends TestCase
{
    // ── getMethod ────────────────────────────────────────────────────────────

    #[Test]
    public function getMethodReturnsConstructedMethod(): void
    {
        $action = AbstractActionTestFixture::create(method: 'POST', path: '/test');

        self::assertSame('POST', $action->getMethod());
    }

    // ── getPath — static ─────────────────────────────────────────────────────

    #[Test]
    public function getPathReturnsStaticPathWithoutContext(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/hello');

        self::assertSame('/hello', $action->getPath());
    }

    #[Test]
    public function getPathReturnsStaticPathWhenContextIsNull(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/hello');

        self::assertSame('/hello', $action->getPath(null));
    }

    // ── getPath — context resolution ─────────────────────────────────────────

    #[Test]
    public function getPathResolvesPlaceholderFromContext(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/orders/{id}');
        $context = FakeContext::create(['id' => '42']);

        self::assertSame('/orders/42', $action->getPath($context));
    }

    #[Test]
    public function getPathResolvesMultiplePlaceholders(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/users/{userId}/orders/{orderId}');
        $context = FakeContext::create(['userId' => '1', 'orderId' => '99']);

        self::assertSame('/users/1/orders/99', $action->getPath($context));
    }

    #[Test]
    public function getPathThrowsForMissingPlaceholder(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/orders/{id}');
        $context = FakeContext::create([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Missing path parameter/');

        $action->getPath($context);
    }

    #[Test]
    public function getPathThrowsForNonScalarPlaceholderValue(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/orders/{id}');
        $context = FakeContext::create(['id' => ['not', 'scalar']]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/must be a scalar/');

        $action->getPath($context);
    }

    // ── getPath — action is stateless (no context stored) ────────────────────

    #[Test]
    public function actionDoesNotStoreContext(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/orders/{id}');
        $context = FakeContext::create(['id' => '7']);

        // Primera llamada con context — resuelve correctamente
        self::assertSame('/orders/7', $action->getPath($context));

        // Segunda llamada con context distinto — resuelve con el nuevo, no con el anterior
        $context2 = FakeContext::create(['id' => '99']);

        self::assertSame('/orders/99', $action->getPath($context2));
    }

    #[Test]
    public function sameActionInstanceCanBeCalledWithDifferentContexts(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/orders/{id}');

        $ctx1 = FakeContext::create(['id' => '1']);
        $ctx2 = FakeContext::create(['id' => '2']);

        self::assertSame('/orders/1', $action->getPath($ctx1));
        self::assertSame('/orders/2', $action->getPath($ctx2));
    }

    // ── getAuthorization ──────────────────────────────────────────────────────

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

    // ── custom resolvePathCallback ────────────────────────────────────────────

    #[Test]
    public function customPathResolverIsUsedWhenProvided(): void
    {
        $action = AbstractActionWithResolver::create(method: 'GET', path: '/original');

        self::assertSame('/custom-resolved', $action->getPath());
    }

    #[Test]
    public function customPathResolverReceivesContext(): void
    {
        $action = AbstractActionWithContextAwareResolver::create(method: 'GET', path: '/base');
        $context = FakeContext::create(['suffix' => 'xyz']);

        self::assertSame('/base/xyz', $action->getPath($context));
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

    protected function resolvePathCallback(): callable
    {
        return static fn (string $path, mixed $ctx): string => '/custom-resolved';
    }
}

final class AbstractActionWithContextAwareResolver extends AbstractAction
{
    public static function getName(): string
    {
        return 'context_aware_resolver';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): ?string
    {
        return null;
    }

    protected function resolvePathCallback(): callable
    {
        return static function (string $path, ?ActionContextInterface $ctx): string {
            $data = $ctx?->toArray() ?? [];
            $suffix = $data['suffix'] ?? '';

            return $path.'/'.$suffix;
        };
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

    protected function resolvePathCallback(): callable
    {
        return static fn (): int => 42;
    }
}
