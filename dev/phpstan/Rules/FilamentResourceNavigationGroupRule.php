<?php

declare(strict_types=1);

namespace Stan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Class_>
 */
final readonly class FilamentResourceNavigationGroupRule implements Rule
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @param Class_ $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        if (!$this->isResource($scope)) {
            return [];
        }

        $navigationGroupGetter = $this->getNavigationGroupGetterMethod($node);
        if ($navigationGroupGetter !== null) {
            $errors[] = RuleErrorBuilder::message('Resource classes must NOT define getNavigationGroup() method. Instead, define public static $navigationGroup referencing NavigationGroup enum case.')
                ->identifier('filament.resource.navigationGroup.getter')
                ->build();
        }

        $navigationGroupExpr = $this->getNavigationGroupExpression($node);
        if ($navigationGroupExpr === null) {
            $errors[] = RuleErrorBuilder::message('Resource classes must define public static $navigationGroup referencing NavigationGroup enum case.')
                ->identifier('filament.resource.navigationGroup.missing')
                ->build();

            return $errors;
        }

        if (!$navigationGroupExpr instanceof Node\Expr\ClassConstFetch) {
            $errors[] = RuleErrorBuilder::message('Resource $navigationGroup must reference NavigationGroup enum case, e.g., NavigationGroup::Customers.')
                ->identifier('filament.resource.navigationGroup.type')
                ->build();

            return $errors;
        }

        if ($navigationGroupExpr->class instanceof Node\Name && $navigationGroupExpr->class->name !== 'NavigationGroup') {
            $errors[] = RuleErrorBuilder::message('Resource $navigationGroup must reference NavigationGroup enum case, e.g., NavigationGroup::Customers.')
                ->identifier('filament.resource.navigationGroup.enum')
                ->build();

            return $errors;
        }

        return $errors;
    }

    private function getNavigationGroupExpression(Class_ $node): ?Node\Expr
    {
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Property) {
                foreach ($stmt->props as $prop) {
                    if ($prop->name->name === 'navigationGroup' && $stmt->isStatic()) {
                        return $prop->default;
                    }
                }
            }
        }

        return null;
    }

    private function getNavigationGroupGetterMethod(Class_ $node): ?Node\Stmt\ClassMethod
    {
        return array_find($node->stmts, fn ($stmt) => $stmt instanceof Node\Stmt\ClassMethod && $stmt->name->name === 'getNavigationGroup');
    }

    private function isResource(Scope $scope): bool
    {
        $filePath = $scope->getFile();
        if (!str_contains($filePath, '/app/Filament/Resources/')) {
            return false;
        }

        $className = $scope->getClassReflection()?->getName();
        if ($className === null) {
            return false;
        }

        $reflection = $this->reflectionProvider->getClass($className);

        return array_any($reflection->getAncestors(), static fn (ClassReflection $ancestor) => str_ends_with($ancestor->getName(), '\\Resource'));
    }
}
