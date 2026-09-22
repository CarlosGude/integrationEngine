<?php

declare(strict_types=1);

namespace IntegrationEngine\PHPStan\Rules;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Parser\Parser;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<InClassMethodNode> */
final class MapperActionRule implements Rule
{
    public function __construct(private readonly ReflectionProvider $reflectionProvider, private readonly Parser $parser) {}

    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        $method = $node->getOriginalNode();
        if (!$class->isSubclassOf(AbstractMapper::class) || 'getaction' !== strtolower($method->name->toString()) || $method->isAbstract()) {
            return [];
        }
        $name = $this->returnedClass($method);
        if (null === $name) {
            return [RuleErrorBuilder::message('Mapper getAction() must return a class constant extending AbstractAction.')->identifier('integrationEngine.mapperAction')->build()];
        }
        $actionName = $scope->resolveName($name);
        if (!$this->reflectionProvider->hasClass($actionName) || !$this->reflectionProvider->getClass($actionName)->isSubclassOf(AbstractAction::class)) {
            return [RuleErrorBuilder::message('Mapper getAction() must return a class constant extending AbstractAction.')->identifier('integrationEngine.mapperAction')->build()];
        }
        $action = $this->reflectionProvider->getClass($actionName);
        $declaringClass = $action->getNativeMethod('mapper')->getDeclaringClass();
        $file = $declaringClass->getFileName();
        if (null !== $file) {
            $finder = new NodeFinder();
            foreach ($finder->findInstanceOf($this->parser->parseFile($file), Class_::class) as $actionNode) {
                if ($actionNode->namespacedName?->toString() !== $declaringClass->getName()) {
                    continue;
                }
                $mapperMethod = $actionNode->getMethod('mapper');
                $mapper = null === $mapperMethod ? null : $this->returnedClass($mapperMethod);
                if ($mapper?->toString() === $class->getName()) {
                    return [];
                }
            }
        }

        return [RuleErrorBuilder::message('The action mapper() must return this mapper class constant.')->identifier('integrationEngine.mapperReciprocity')->build()];
    }

    private function returnedClass(ClassMethod $method): ?Name
    {
        $statements = $method->stmts;
        if (null === $statements || 1 !== \count($statements) || !$statements[0] instanceof Return_) {
            return null;
        }
        $value = $statements[0]->expr;
        if (!$value instanceof ClassConstFetch || !$value->class instanceof Name || !$value->name instanceof Identifier || 'class' !== strtolower($value->name->toString())) {
            return null;
        }

        return $value->class;
    }
}
