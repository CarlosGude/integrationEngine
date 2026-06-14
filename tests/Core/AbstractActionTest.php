<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\PathResolvableContextInterface;
use IntegrationEngine\Core\Contract\Auth\StaticAuthorizationConfig;
use IntegrationEngine\Core\Exception\PathResolutionException;
use IntegrationEngine\Tests\Fake\FakeContext;
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

        $this->expectException(PathResolutionException::class);
        $this->expectExceptionMessageMatches('/Missing path parameter/');

        $action->getPath($context);
    }

    #[Test]
    public function getPathThrowsForNonScalarPlaceholderValue(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/orders/{id}');
        $context = FakeContext::create(['id' => ['not', 'scalar']]);

        $this->expectException(PathResolutionException::class);
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

    // ── context-driven path resolution ───────────────────────────────────────

    #[Test]
    public function contextCanOverridePathResolution(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/character');
        $context = ContextWithCustomResolver::create([]);

        self::assertSame('/custom-resolved', $action->getPath($context));
    }

    #[Test]
    public function contextResolutionReceivesRawPathFromYaml(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/character');
        $context = ContextThatAppendsQueryString::create(['name' => 'rick', 'status' => 'alive']);

        self::assertSame('/character?name=rick&status=alive', $action->getPath($context));
    }

    #[Test]
    public function contextReturningNullFallsBackToDefaultResolver(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/character/{id}');
        $context = FakeContext::create(['id' => '42']);

        self::assertSame('/character/42', $action->getPath($context));
    }

    #[Test]
    public function contextReturningEmptyStringThrows(): void
    {
        $action = AbstractActionTestFixture::create(method: 'GET', path: '/character');
        $context = ContextThatReturnsEmptyString::create([]);

        $this->expectException(PathResolutionException::class);
        $this->expectExceptionMessageMatches('/empty string/');

        $action->getPath($context);
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

final class ContextWithCustomResolver implements PathResolvableContextInterface
{
    private function __construct() {}

    public static function create(array $data): self
    {
        return new self();
    }

    public function toArray(): array
    {
        return [];
    }

    public function resolvePath(string $path): string
    {
        return '/custom-resolved';
    }
}

final class ContextThatAppendsQueryString implements PathResolvableContextInterface
{
    /** @param array<string, mixed> $data */
    private function __construct(private readonly array $data) {}

    /** @param array<string, mixed> $data */
    public static function create(array $data): self
    {
        return new self($data);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function resolvePath(string $path): ?string
    {
        $params = array_filter($this->data, static fn (mixed $v): bool => \is_scalar($v) && '' !== (string) $v);

        return empty($params) ? null : $path.'?'.http_build_query($params);
    }
}

final class ContextThatReturnsEmptyString implements PathResolvableContextInterface
{
    private function __construct() {}

    public static function create(array $data): self
    {
        return new self();
    }

    public function toArray(): array
    {
        return [];
    }

    public function resolvePath(string $path): string
    {
        return '';
    }
}
