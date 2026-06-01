<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator\Swagger;

final class SwaggerParser
{
    public function parse(string $source): SwaggerSpec
    {
        $raw = $this->load($source);

        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return SwaggerSpec::fromArray($data);
    }

    private function load(string $source): string
    {
        if (str_starts_with($source, 'http')) {
            return file_get_contents($source);
        }

        return file_get_contents($source);
    }
}
