<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\Generator;

use IntegrationEngine\Bundle\Generator\IntegrationContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IntegrationContextTest extends TestCase
{
    #[Test]
    public function buildsNamespacesFromBaseNamespaceNameAndAction(): void
    {
        $ctx = $this->context();

        self::assertSame('App\Infrastructure\Integrations\MyApi', $ctx->integrationNamespace());
        self::assertSame('App\Infrastructure\Integrations\MyApi\GetEmployees', $ctx->actionNamespace());
        self::assertSame('App\Infrastructure\Integrations\MyApi\GetEmployees\Request', $ctx->requestNamespace());
        self::assertSame('App\Infrastructure\Integrations\MyApi\GetEmployees\Response', $ctx->responseNamespace());
    }

    #[Test]
    public function hasBodyOnlyForWriteMethodsOnRestAdapters(): void
    {
        self::assertFalse($this->context(method: 'GET')->hasBody());
        self::assertFalse($this->context(method: 'DELETE')->hasBody());
        self::assertTrue($this->context(method: 'POST')->hasBody());
        self::assertTrue($this->context(method: 'PUT')->hasBody());
        self::assertTrue($this->context(method: 'patch')->hasBody());
    }

    #[Test]
    public function hasResponseForEveryMethodExceptDelete(): void
    {
        self::assertTrue($this->context(method: 'GET')->hasResponse());
        self::assertTrue($this->context(method: 'POST')->hasResponse());
        self::assertFalse($this->context(method: 'delete')->hasResponse());
    }

    #[Test]
    public function adaptersWithoutMethodAlwaysHaveBodyAndResponse(): void
    {
        $ctx = $this->context(method: 'DELETE', adapterRequiresPath: false, adapterRequiresMethod: false);

        self::assertTrue($ctx->hasBody());
        self::assertTrue($ctx->hasResponse());
    }

    #[Test]
    public function graphQlBodyHintOnlyWhenAdapterNeedsNeitherPathNorMethod(): void
    {
        self::assertFalse($this->context()->needsGraphQLBodyHint());
        self::assertFalse($this->context(adapterRequiresMethod: false)->needsGraphQLBodyHint());
        self::assertTrue($this->context(adapterRequiresPath: false, adapterRequiresMethod: false)->needsGraphQLBodyHint());
    }

    #[Test]
    public function withOverridesOnlyActionMethodAndPath(): void
    {
        $ctx = $this->context();

        $derived = $ctx->with(action: 'DeleteEmployee', method: 'DELETE', path: '/employees/{id}');

        self::assertSame('DeleteEmployee', $derived->action);
        self::assertSame('DELETE', $derived->method);
        self::assertSame('/employees/{id}', $derived->path);
        // Everything else is preserved.
        self::assertSame($ctx->name, $derived->name);
        self::assertSame($ctx->baseNamespace, $derived->baseNamespace);
        self::assertSame($ctx->basePath, $derived->basePath);
        self::assertSame($ctx->clientType, $derived->clientType);
    }

    #[Test]
    public function withWithoutArgumentsReturnsAnEquivalentContext(): void
    {
        $ctx = $this->context();

        $copy = $ctx->with();

        // with() always builds a new instance — equivalence, not identity.
        self::assertNotSame($ctx, $copy);
        self::assertSame($ctx->name, $copy->name);
        self::assertSame($ctx->action, $copy->action);
        self::assertSame($ctx->method, $copy->method);
        self::assertSame($ctx->path, $copy->path);
        self::assertSame($ctx->baseNamespace, $copy->baseNamespace);
        self::assertSame($ctx->basePath, $copy->basePath);
        self::assertSame($ctx->clientType, $copy->clientType);
        self::assertSame($ctx->adapterRequiresPath, $copy->adapterRequiresPath);
        self::assertSame($ctx->adapterRequiresMethod, $copy->adapterRequiresMethod);
    }

    private function context(
        string $method = 'GET',
        bool $adapterRequiresPath = true,
        bool $adapterRequiresMethod = true,
    ): IntegrationContext {
        return new IntegrationContext(
            name: 'MyApi',
            action: 'GetEmployees',
            method: $method,
            path: '/employees',
            baseNamespace: 'App\Infrastructure\Integrations',
            basePath: '/tmp/project/src/Infrastructure/Integrations/MyApi',
            adapterRequiresPath: $adapterRequiresPath,
            adapterRequiresMethod: $adapterRequiresMethod,
        );
    }
}
