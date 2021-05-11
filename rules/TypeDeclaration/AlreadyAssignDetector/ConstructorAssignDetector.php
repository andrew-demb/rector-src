<?php

declare(strict_types=1);

namespace Rector\TypeDeclaration\AlreadyAssignDetector;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeTraverser;
use PHPStan\Type\ObjectType;
use Rector\Core\ValueObject\MethodName;
use Rector\NodeTypeResolver\NodeTypeResolver;
use Rector\TypeDeclaration\Matcher\PropertyAssignMatcher;
use Symplify\Astral\NodeTraverser\SimpleCallableNodeTraverser;

final class ConstructorAssignDetector
{
    /**
     * @var string
     */
    private const IS_FIRST_LEVEL_STATEMENT = 'first_level_stmt';

    public function __construct(
        private NodeTypeResolver $nodeTypeResolver,
        private PropertyAssignMatcher $propertyAssignMatcher,
        private SimpleCallableNodeTraverser $simpleCallableNodeTraverser
    ) {
    }

    public function isPropertyAssigned(ClassLike $classLike, string $propertyName): bool
    {
        $initializeClassMethod = $this->matchInitializeClassMethod($classLike);
        if (! $initializeClassMethod instanceof ClassMethod) {
            return false;
        }

        $isAssignedInConstructor = false;

        $this->decorateFirstLevelStatementAttribute($initializeClassMethod);

        $this->simpleCallableNodeTraverser->traverseNodesWithCallable((array) $initializeClassMethod->stmts, function (
            Node $node
        ) use ($propertyName, &$isAssignedInConstructor): ?int {
            $expr = $this->matchAssignExprToPropertyName($node, $propertyName);
            if (! $expr instanceof Expr) {
                return null;
            }

            /** @var Assign $assign */
            $assign = $node;
            $isFirstLevelStatement = $assign->getAttribute(self::IS_FIRST_LEVEL_STATEMENT);

            // cannot be nested
            if ($isFirstLevelStatement !== true) {
                return null;
            }

            $isAssignedInConstructor = true;

            return NodeTraverser::DONT_TRAVERSE_CURRENT_AND_CHILDREN;
        });

        return $isAssignedInConstructor;
    }

    private function matchAssignExprToPropertyName(Node $node, string $propertyName): ?Expr
    {
        if (! $node instanceof Assign) {
            return null;
        }

        return $this->propertyAssignMatcher->matchPropertyAssignExpr($node, $propertyName);
    }

    private function decorateFirstLevelStatementAttribute(ClassMethod $classMethod): void
    {
        foreach ((array) $classMethod->stmts as $methodStmt) {
            $methodStmt->setAttribute(self::IS_FIRST_LEVEL_STATEMENT, true);

            if ($methodStmt instanceof Expression) {
                $methodStmt->expr->setAttribute(self::IS_FIRST_LEVEL_STATEMENT, true);
            }
        }
    }

    private function matchInitializeClassMethod(ClassLike $classLike): ?ClassMethod
    {
        $constructClassMethod = $classLike->getMethod(MethodName::CONSTRUCT);
        if ($constructClassMethod instanceof ClassMethod) {
            return $constructClassMethod;
        }

        $testCaseObjectType = new ObjectType('PHPUnit\Framework\TestCase');
        if (! $this->nodeTypeResolver->isObjectType($classLike, $testCaseObjectType)) {
            return null;
        }

        return $classLike->getMethod(MethodName::SET_UP) ?? $classLike->getMethod(MethodName::SET_UP_BEFORE_CLASS);
    }
}
