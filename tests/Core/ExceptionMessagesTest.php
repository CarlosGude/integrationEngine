<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Exception\ActionNotFoundException;
use IntegrationEngine\Core\Exception\BatchMapperActionMismatchException;
use IntegrationEngine\Core\Exception\DynamicAuthException;
use IntegrationEngine\Core\Exception\IntegrationNotFoundException;
use IntegrationEngine\Core\Exception\MapperActionMismatchException;
use IntegrationEngine\Core\Exception\NotMappedActionException;
use IntegrationEngine\Core\Exception\PathResolutionException;
use IntegrationEngine\Core\Exception\RequestResponseException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that every exception constructs the correct message.
 * These tests exist to kill MethodCallRemoval and Concat mutants
 * that escape when callers only assert the exception type.
 */
final class ExceptionMessagesTest extends TestCase
{
    // ── ActionNotFoundException ───────────────────────────────────────────────

    #[Test]
    public function actionNotFoundExceptionContainsActionName(): void
    {
        $e = new ActionNotFoundException('my_action');

        self::assertSame('Action [my_action] not found', $e->getMessage());
    }

    // ── NotMappedActionException ──────────────────────────────────────────────

    #[Test]
    public function notMappedActionExceptionContainsActionName(): void
    {
        $e = new NotMappedActionException('some_action');

        self::assertSame('Action "some_action" requires a mapper but none was defined.', $e->getMessage());
    }

    // ── BatchMapperActionMismatchException ────────────────────────────────────

    #[Test]
    public function batchMapperActionMismatchExceptionContainsAllFourParts(): void
    {
        $e = new BatchMapperActionMismatchException(
            mapperClass: 'MyBatchMapper',
            expectedActionClass: 'ExpectedAction',
            key: 'item_key',
            actualActionClass: 'ActualAction',
        );

        self::assertSame(
            'Batch mapper "MyBatchMapper" expects action "ExpectedAction" but key "item_key" has action "ActualAction".',
            $e->getMessage()
        );
    }

    // ── MapperActionMismatchException ─────────────────────────────────────────

    #[Test]
    public function mapperActionMismatchExceptionContainsAllThreeClasses(): void
    {
        $e = new MapperActionMismatchException('MyMapper', 'ExpectedAction', 'ActualAction');

        self::assertSame(
            'Mapper "MyMapper" expects action "ExpectedAction" but received "ActualAction".',
            $e->getMessage()
        );
    }

    // ── IntegrationNotFoundException ──────────────────────────────────────────

    #[Test]
    public function integrationNotFoundExceptionContainsIntegrationName(): void
    {
        $e = new IntegrationNotFoundException('GithubIntegration');

        self::assertStringContainsString('GithubIntegration', $e->getMessage());
        self::assertStringContainsString('integration_engine.integrations', $e->getMessage());
    }

    // ── DynamicAuthException ──────────────────────────────────────────────────

    #[Test]
    public function missingTokenFieldContainsActionAndFieldName(): void
    {
        $e = DynamicAuthException::missingTokenField('fetch_token', 'access_token');

        self::assertSame(
            'Dynamic auth action "fetch_token" response does not contain field "access_token".',
            $e->getMessage()
        );
    }

    #[Test]
    public function nonScalarTokenFieldContainsFieldName(): void
    {
        $e = DynamicAuthException::nonScalarTokenField('access_token');

        self::assertSame('Token field "access_token" must be a scalar value.', $e->getMessage());
    }

    // ── PathResolutionException ───────────────────────────────────────────────

    #[Test]
    public function missingParameterContainsKeyAndPath(): void
    {
        $e = PathResolutionException::missingParameter('id', '/orders/{id}');

        self::assertSame('Missing path parameter "id" for path "/orders/{id}".', $e->getMessage());
    }

    #[Test]
    public function nonScalarParameterContainsKey(): void
    {
        $e = PathResolutionException::nonScalarParameter('id');

        self::assertSame('Path parameter "id" must be a scalar value.', $e->getMessage());
    }

    #[Test]
    public function resolverReturnedEmptyPathHasCorrectMessage(): void
    {
        $e = PathResolutionException::resolverReturnedEmptyPath();

        self::assertSame('Path resolver returned an empty string; return null to fall back to placeholder resolution.', $e->getMessage());
    }

    // ── RequestResponseException ──────────────────────────────────────────────

    #[Test]
    public function requestResponseExceptionMessageContainsErrorConstantAndContext(): void
    {
        $e = new RequestResponseException(statusCode: 404, context: 'resource not found');

        self::assertSame('REQUEST_RESPONSE_ERROR: resource not found', $e->getMessage());
    }

    #[Test]
    public function requestResponseExceptionExposesStatusCode(): void
    {
        $e = new RequestResponseException(statusCode: 503, context: 'service unavailable');

        self::assertSame(503, $e->statusCode);
    }

    #[Test]
    public function requestResponseExceptionExposesContext(): void
    {
        $e = new RequestResponseException(statusCode: 500, context: 'internal error');

        self::assertSame('internal error', $e->context);
    }

    #[Test]
    public function requestResponseExceptionCodeIsZero(): void
    {
        $e = new RequestResponseException(statusCode: 200, context: 'ok');

        self::assertSame(0, $e->getCode());
    }

    // ── DynamicAuthorizationConfig parent::__construct ────────────────────────

    #[Test]
    public function dynamicAuthorizationConfigHasDynamicType(): void
    {
        $config = new DynamicAuthorizationConfig(
            action: 'fetch_token',
            tokenField: 'access_token',
            ttl: 60,
        );

        self::assertSame('dynamic', $config->type);
    }
}
