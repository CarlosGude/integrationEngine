<?php

declare(strict_types=1);

namespace IntegrationEngine\PHPStan\Rules;

use IntegrationEngine\Core\Registry\IntegrationName;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\MixedType;
use PHPStan\Type\TypeUtils;

/** @implements Rule<InClassMethodNode> */
final class IntegrationFacadeReturnTypeRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $method = $node->getMethodReflection();
        if (!$node->getClassReflection()->implementsInterface(IntegrationName::class) || !$method->isPublic() || \in_array(strtolower($method->getName()), ['__construct', '__destruct'], true)) {
            return [];
        }
        $type = $method->getVariants()[0]->getReturnType();
        foreach (TypeUtils::flattenTypes($type) as $member) {
            if (null === $node->getOriginalNode()->returnType || $member instanceof MixedType || ($member->isIterable()->yes() && !$member->getIterableValueType()->isObject()->yes())) {
                return [RuleErrorBuilder::message('Public integration facade methods must declare a return type; collections must contain objects.')->identifier('integrationEngine.facadeReturnType')->build()];
            }
        }

        return [];
    }
}
