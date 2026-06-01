<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator\Swagger;

use IntegrationEngine\Bundle\Generator\IntegrationContext;
use IntegrationEngine\Bundle\Generator\IntegrationFileGenerator;

final class SwaggerIntegrationGenerator
{
    public function __construct(
        private readonly SwaggerParser $parser,
        private readonly OpenApiToIntegrationMapper $mapper,
        private readonly IntegrationFileGenerator $fileGenerator,
    ) {}

    public function generate(IntegrationContext $baseCtx, string $source): void
    {
        $spec = $this->parser->parse($source);

        foreach ($spec->operations as $operation) {
            $ctx = $this->mapper->mapOperationToContext($baseCtx, $operation);

            foreach ($this->fileGenerator->generateActionFiles($ctx) as $file => $content) {
                $this->fileGenerator->writeFile($file, $content);
            }

            if (!$this->fileGenerator->integrationExists($ctx)) {
                foreach ($this->fileGenerator->generateIntegrationFiles($ctx) as $file => $content) {
                    $this->fileGenerator->writeFile($file, $content);
                }
            }

            $this->fileGenerator->appendActionToConfig($ctx);
        }
    }
}
