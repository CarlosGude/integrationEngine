<?php

declare(strict_types=1);

namespace IntegrationEngine\PHPStan\Rules;

use IntegrationEngine\Core\Contract\Response\ResponseInterface;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<InClassNode> */
final class ResponseClassModifiersRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        if ($class->isAbstract() || $class->isAnonymous() || !$class->isClass()) {
            return [];
        }
        if (!$class->implementsInterface(ResponseInterface::class) && !$class->implementsInterface(WebhookEventInterface::class)) {
            return [];
        }
        if ($class->isFinal() && $class->isReadOnly()) {
            return [];
        }

        return [RuleErrorBuilder::message('Response and webhook event classes must be final and readonly.')->identifier('integrationEngine.responseModifiers')->build()];
    }
}
