<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\Generator;

use IntegrationEngine\Bundle\Generator\IntegrationContext;
use IntegrationEngine\Bundle\Generator\TemplateRenderer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TemplateRendererTest extends TestCase
{
    #[Test]
    public function integrationTemplateDeclaresSnakeCaseNameConstant(): void
    {
        $code = $this->renderer()->integration();

        self::assertStringContainsString('namespace App\Infrastructure\Integrations\MyApi;', $code);
        self::assertStringContainsString('final class MyApiIntegration implements IntegrationName', $code);
        self::assertStringContainsString("public const string NAME = 'my_api';", $code);
    }

    #[Test]
    public function actionTemplateWithResponseReferencesItsMapper(): void
    {
        $code = $this->renderer()->action();

        self::assertStringContainsString('final class GetEmployeesAction extends AbstractAction', $code);
        self::assertStringContainsString("return 'GetEmployees';", $code);
        self::assertStringContainsString('return true;', $code);
        self::assertStringContainsString('return GetEmployeesMapper::class;', $code);
        self::assertStringContainsString(
            'use App\Infrastructure\Integrations\MyApi\GetEmployees\Response\GetEmployeesMapper;',
            $code
        );
    }

    #[Test]
    public function actionTemplateForDeleteHasNoResponseNorMapper(): void
    {
        $code = $this->renderer(method: 'DELETE')->action();

        self::assertStringContainsString('return false;', $code);
        self::assertStringContainsString('return null;', $code);
        self::assertStringNotContainsString('GetEmployeesMapper', $code);
    }

    #[Test]
    public function actionTemplateForGraphQlIncludesBodyHint(): void
    {
        $code = $this->renderer(adapterRequiresPath: false, adapterRequiresMethod: false)->action();

        self::assertStringContainsString('use IntegrationEngine\Core\Contract\GraphQLBodyInterface;', $code);
        self::assertStringContainsString('// Attach a GraphQLBodyInterface implementation when calling send().', $code);
    }

    #[Test]
    public function mapperTemplatePairsWithItsActionClass(): void
    {
        $code = $this->renderer()->mapper();

        self::assertStringContainsString('final class GetEmployeesMapper extends AbstractMapper', $code);
        self::assertStringContainsString('return GetEmployeesAction::class;', $code);
        self::assertStringContainsString('return new GetEmployeesResponse();', $code);
        self::assertStringContainsString(
            'use App\Infrastructure\Integrations\MyApi\GetEmployees\Request\GetEmployeesAction;',
            $code
        );
    }

    #[Test]
    public function responseTemplateImplementsResponseInterface(): void
    {
        $code = $this->renderer()->response();

        self::assertStringContainsString('final readonly class GetEmployeesResponse implements ResponseInterface', $code);
        self::assertStringContainsString('public function toArray(): array', $code);
    }

    #[Test]
    public function yamlEntryForRestIncludesMethodAndPath(): void
    {
        $entry = $this->renderer()->yamlEntry();

        $expected = "GetEmployees:\n"
            ."    action: App\\Infrastructure\\Integrations\\MyApi\\GetEmployees\\Request\\GetEmployeesAction\n"
            ."    method: GET\n"
            ."    path: /employees\n";

        self::assertSame($expected, $entry);
    }

    #[Test]
    public function yamlEntryForGraphQlOmitsMethodAndPath(): void
    {
        $entry = $this->renderer(adapterRequiresPath: false, adapterRequiresMethod: false)->yamlEntry();

        $expected = "GetEmployees:\n"
            ."    action: App\\Infrastructure\\Integrations\\MyApi\\GetEmployees\\Request\\GetEmployeesAction\n";

        self::assertSame($expected, $entry);
    }

    #[Test]
    public function generatedTemplatesAreValidPhp(): void
    {
        $renderer = $this->renderer();

        foreach ([$renderer->integration(), $renderer->action(), $renderer->mapper(), $renderer->response()] as $code) {
            self::assertNotFalse(
                token_get_all($code, TOKEN_PARSE),
                'Generated template must be syntactically valid PHP.'
            );
        }
    }

    private function renderer(
        string $method = 'GET',
        bool $adapterRequiresPath = true,
        bool $adapterRequiresMethod = true,
    ): TemplateRenderer {
        return new TemplateRenderer(new IntegrationContext(
            name: 'MyApi',
            action: 'GetEmployees',
            method: $method,
            path: '/employees',
            baseNamespace: 'App\Infrastructure\Integrations',
            basePath: '/tmp/project/src/Infrastructure/Integrations/MyApi',
            adapterRequiresPath: $adapterRequiresPath,
            adapterRequiresMethod: $adapterRequiresMethod,
        ));
    }
}
