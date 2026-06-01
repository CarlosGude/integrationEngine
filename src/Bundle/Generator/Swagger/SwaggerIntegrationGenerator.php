<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator\Swagger;

use IntegrationEngine\Bundle\Generator\IntegrationContext;
use IntegrationEngine\Bundle\Generator\IntegrationFileGenerator;

final readonly class SwaggerIntegrationGenerator
{
    public function __construct(
        private SwaggerParser              $parser,
        private OpenApiToIntegrationMapper $mapper,
        private IntegrationFileGenerator   $generator,
    ) {}

    /**
     * @return iterable<array{string, string, IntegrationContext}>
     * @throws \JsonException
     */
    public function generate(IntegrationContext $baseCtx, string $source): iterable
    {
        $spec = $this->parser->parse($source);

        foreach ($spec->operations as $operation) {

            $ctx = $this->mapper->mapOperationToContext($baseCtx, $operation);

            foreach ($this->generator->generateActionFiles($ctx) as $file => $content) {
                yield [$file, $content, $ctx];
            }

            if (!$this->generator->integrationExists($ctx)) {
                foreach ($this->generator->generateIntegrationFiles($ctx) as $file => $content) {
                    yield [$file, $content, $ctx];
                }
            }

            $this->generator->appendActionToConfig($ctx);
        }
    }
}
