<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter;
use IntegrationEngine\Tests\Fake\FakeFormBody;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class GraphQLFormBodyTest extends TestCase
{
    public function testFormBodyIsRejectedWithExplicitGraphqlContract(): void
    {
        $this->expectException(RequestResponseException::class);
        $this->expectExceptionMessage('GraphQLBodyInterface');
        (new GraphQLClientAdapter(new MockHttpClient(), 'https://example.com'))->send(FakePathAction::create('POST', '/', FakeFormBody::create([])));
    }
}
